<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\SafeMail;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingService
{
    public function customerBookings(User $user, ?string $status = null): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(Booking::resourceRelations())
            ->latest()
            ->paginate(10);
    }

    public function create(User $user, CreateBookingData $data): Booking
    {
        $booking = DB::transaction(function () use ($user, $data) {
            $field = SportsField::query()->approved()->lockForUpdate()->find($data->sportsFieldId);

            if (! $field) {
                throw new ConflictException('Sân chưa được duyệt hoặc đang dừng hoạt động.');
            }

            $slot = TimeSlot::query()
                ->where('sports_field_id', $field->id)
                ->where('is_active', true)
                ->findOrFail($data->timeSlotId);

            if ($field->field_owner_id === $user->id) {
                throw new ForbiddenException('Bạn không thể đặt sân của chính mình.');
            }

            if (Carbon::parse($data->bookingDate)->toDateString().' '.$slot->start_time <= now()->format('Y-m-d H:i:s')) {
                throw new ConflictException('Khung giờ này đã qua. Vui lòng chọn giờ khác.');
            }

            $isBooked = Booking::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data->bookingDate)
                ->whereIn('status', BookingStatus::blockingCases())
                ->lockForUpdate()
                ->exists();

            if ($isBooked) {
                throw new ConflictException('Khung giờ này đã có người đặt. Vui lòng chọn giờ khác.');
            }

            // Slot đang được giữ 15 phút cho người đứng đầu hàng đợi: không cho người khác chen ngang.
            $offer = BookingWaitlist::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data->bookingDate)
                ->where('status', 'offered')
                ->where('offer_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($offer && $offer->user_id !== $user->id) {
                throw new ConflictException('Khung giờ đang được giữ cho người trong hàng đợi đến '.$offer->offer_expires_at->format('H:i').'. Vui lòng thử lại sau.');
            }
            $offer?->update(['status' => 'accepted']);

            return Booking::create([
                'user_id' => $user->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $data->bookingDate,
                'status' => BookingStatus::Pending->value,
                'total_price' => ($slot->price_per_hour ?? $field->price_per_hour) * $slot->durationInHours(),
                'notes' => $data->notes,
            ])->load([...Booking::resourceRelations(withCustomer: true), 'sportsField.owner']);
        });

        SafeMail::queue($booking->sportsField->owner->email, new NewBookingRequest($booking));

        return $booking;
    }

    public function cancel(User $user, Booking $booking, ?string $reason): Booking
    {
        Gate::forUser($user)->authorize('cancel', $booking);

        if (! $booking->canBeCancelled()) {
            throw new ConflictException(
                $booking->status === BookingStatus::Confirmed
                    ? 'Đơn đã được chủ sân xác nhận quá '.Settings::getInt('confirmed_cancel_window_hours', 2).' giờ nên không thể tự hủy. Vui lòng liên hệ chủ sân.'
                    : 'Đơn đặt sân này không còn đủ điều kiện để hủy.'
            );
        }

        if ($booking->isLateToCancel()) {
            TrustScoreService::penalizeLateCancellation($user, $booking);
        }

        $booking->update([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason ?: 'Khách hàng tự hủy đơn.',
        ]);

        app(WaitlistService::class)->handleSlotFreed(
            $booking->sports_field_id,
            $booking->time_slot_id,
            $booking->booking_date->toDateString()
        );

        return $booking->refresh()->load(Booking::resourceRelations());
    }

    /**
     * Chạy định kỳ: đơn chờ xác nhận quá giờ bắt đầu hoặc quá lâu chủ sân không phản hồi → từ chối;
     * đơn đã xác nhận quá giờ kết thúc → hoàn thành.
     *
     * @return array{rejected:int, completed:int}
     */
    public function syncStatuses(): array
    {
        $count = ['rejected' => 0, 'completed' => 0];

        $expireBefore = now()->subHours(Settings::getInt('pending_expire_hours', 24));

        Booking::with(['timeSlot', 'customer', 'sportsField'])
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->where(fn ($query) => $query->whereDate('booking_date', '<=', today())
                ->orWhere(fn ($query) => $query->where('status', BookingStatus::Pending->value)
                    ->where(fn ($query) => $query->where('created_at', '<=', $expireBefore)->orWhere('payment_due_at', '<=', now()))))
            ->lazyById()
            ->each(function (Booking $booking) use (&$count, $expireBefore) {
                $reason = $booking->status === BookingStatus::Pending ? $this->expiryReason($booking, $expireBefore) : null;
                if ($reason) {
                    $booking->update([
                        // Khách không chuyển khoản đúng hạn là lỗi phía khách → "đã huỷ"; còn lại là chủ sân không xử lý → "từ chối".
                        'status' => ($booking->payment_status === 'awaiting' ? BookingStatus::Cancelled : BookingStatus::Rejected)->value,
                        'cancelled_at' => now(),
                        'cancel_reason' => $reason,
                    ]);
                    SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
                    app(WaitlistService::class)->handleSlotFreed(
                        $booking->sports_field_id,
                        $booking->time_slot_id,
                        $booking->booking_date->toDateString()
                    );
                    $count['rejected']++;
                } elseif ($booking->status === BookingStatus::Confirmed && $booking->endsAt()->isPast()) {
                    $attributes = ['status' => BookingStatus::Completed->value];
                    // Đã xác nhận nhưng không check-in: ghi nhận vắng mặt và trừ điểm uy tín đúng 1 lần.
                    if ($booking->checked_in_at === null && $booking->no_show_at === null) {
                        $attributes['no_show_at'] = now();
                        TrustScoreService::penalizeNoShow($booking->customer, $booking);
                    }
                    $booking->update($attributes);
                    $count['completed']++;
                }
            });

        return $count;
    }

    /** Lý do tự đóng một đơn đang chờ, hoặc null nếu đơn còn hạn. */
    private function expiryReason(Booking $booking, Carbon $expireBefore): ?string
    {
        $started = $booking->startsAt()->isPast();

        return match ($booking->payment_status) {
            // Đã duyệt, chờ khách chuyển khoản.
            'awaiting' => $booking->payment_due_at?->isPast() || $started ? 'Quá hạn thanh toán nên đơn tự huỷ.' : null,
            // Khách đã báo chuyển: không huỷ theo hạn vì tiền có thể đã đi, chỉ đóng khi tới giờ chơi.
            'reported' => $started
                ? 'Chủ sân chưa xác nhận nhận tiền trước giờ chơi. Nếu bạn đã chuyển khoản, hãy liên hệ chủ sân hoặc quản trị viên để được hoàn tiền.'
                : null,
            default => match (true) {
                $started => 'Quá giờ chơi mà chủ sân chưa duyệt đơn.',
                $booking->created_at->lte($expireBefore) => 'Chủ sân không phản hồi trong '.Settings::getInt('pending_expire_hours', 24).' giờ nên đơn tự huỷ.',
                default => null,
            },
        };
    }
}
