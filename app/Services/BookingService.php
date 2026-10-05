<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
use App\Support\SafeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookingService
{
    public function customerBookings(User $user): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $user->id)
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
            throw new ConflictException('Đơn đặt sân này không còn đủ điều kiện để hủy.');
        }

        $isLate = $booking->startsAt()->diffInHours(now(), false) > -4;
        if ($isLate) {
            TrustScoreService::penalizeLateCancellation($user);
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
     * Chạy định kỳ: đơn chờ xác nhận quá giờ bắt đầu → từ chối; đơn đã xác nhận quá giờ kết thúc → hoàn thành.
     *
     * @return array{rejected:int, completed:int}
     */
    public function syncStatuses(): array
    {
        $count = ['rejected' => 0, 'completed' => 0];

        Booking::with(['timeSlot', 'customer', 'sportsField'])
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->whereDate('booking_date', '<=', today())
            ->lazyById()
            ->each(function (Booking $booking) use (&$count) {
                if ($booking->status === BookingStatus::Pending && $booking->startsAt()->isPast()) {
                    $booking->update([
                        'status' => BookingStatus::Rejected->value,
                        'cancelled_at' => now(),
                        'cancel_reason' => 'Quá hạn xác nhận.',
                    ]);
                    SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
                    app(WaitlistService::class)->handleSlotFreed(
                        $booking->sports_field_id,
                        $booking->time_slot_id,
                        $booking->booking_date->toDateString()
                    );
                    $count['rejected']++;
                } elseif ($booking->status === BookingStatus::Confirmed && $booking->endsAt()->isPast()) {
                    $booking->update(['status' => BookingStatus::Completed->value]);
                    $count['completed']++;
                }
            });

        return $count;
    }
}
