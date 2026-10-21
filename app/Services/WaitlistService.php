<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Mail\WaitlistSlotOffered;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\SafeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WaitlistService
{
    /**
     * Tham gia hàng đợi thông minh khi slot đã có người đặt.
     */
    public function join(User $user, int $fieldId, int $slotId, string $date): BookingWaitlist
    {
        $field = SportsField::approved()->findOrFail($fieldId);

        if ($field->field_owner_id === $user->id) {
            throw new DomainException('Bạn không thể vào hàng đợi sân của chính mình.');
        }

        $activeCount = BookingWaitlist::where('user_id', $user->id)->whereIn('status', ['waiting', 'offered'])->count();
        if ($activeCount >= 5) {
            throw new ConflictException('Bạn chỉ có thể xếp hàng chờ tối đa 5 khung giờ cùng lúc.');
        }
        $slot = TimeSlot::where('sports_field_id', $fieldId)->where('is_active', true)->findOrFail($slotId);

        $parsedDate = Carbon::parse($date)->startOfDay();
        if (! $slot->appliesTo($parsedDate)) {
            throw new DomainException('Khung giờ này không áp dụng cho ngày đã chọn.');
        }
        if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
            throw new DomainException('Không thể đăng ký hàng đợi cho ngày trong quá khứ.');
        }
        if ($parsedDate->isToday() && Carbon::parse($date.' '.$slot->start_time)->isPast()) {
            throw new DomainException('Không thể đăng ký hàng đợi cho khung giờ đã qua.');
        }

        // Kiểm tra xem slot có thực sự bị đặt chưa
        $isBooked = Booking::where('sports_field_id', $fieldId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->overlapping($slot->start_time, $slot->end_time)
            ->exists();

        if (! $isBooked) {
            throw new ConflictException('Khung giờ này hiện đang còn trống, bạn có thể đặt trực tiếp mà không cần vào hàng đợi.');
        }

        // Kiểm tra user đã có booking cho slot này chưa
        $userHasBooking = Booking::where('user_id', $user->id)
            ->where('sports_field_id', $fieldId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->overlapping($slot->start_time, $slot->end_time)
            ->exists();

        if ($userHasBooking) {
            throw new ConflictException('Bạn đã có đơn đặt sân cho khung giờ này.');
        }

        // Kiểm tra user đã có trong waitlist đang active chưa
        $existing = BookingWaitlist::where('user_id', $user->id)
            ->where('sports_field_id', $fieldId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', ['waiting', 'offered'])
            ->overlapping($slot->start_time, $slot->end_time)
            ->first();

        if ($existing) {
            throw new ConflictException('Bạn đã đăng ký trong danh sách chờ cho khung giờ này.');
        }

        return BookingWaitlist::create([
            'user_id' => $user->id,
            'sports_field_id' => $fieldId,
            'time_slot_id' => $slotId,
            'booking_date' => $parsedDate->toDateString(),
            'status' => 'waiting',
            'priority_score' => $user->trust_score ?? 100,
        ])->load(['sportsField', 'timeSlot']);
    }

    /** Kích hoạt khi một đơn bị huỷ/từ chối: mời người chờ cho khoảng giờ vừa trống. */
    public function handleBookingFreed(Booking $booking): ?BookingWaitlist
    {
        return $this->handleSlotFreed(
            $booking->sports_field_id,
            $booking->booking_date->toDateString(),
            $booking->start_time,
            $booking->end_time,
        );
    }

    /**
     * Kích hoạt tự động khi một khoảng giờ được giải phóng (hủy hoặc từ chối đơn).
     * Chỉ mời người chờ mà toàn bộ khoảng giờ họ muốn nay đã trống.
     */
    public function handleSlotFreed(int $fieldId, string $date, string $start, string $end): ?BookingWaitlist
    {
        if (Carbon::parse($date.' '.$start)->isPast()) {
            // Đã qua giờ bắt đầu: không còn ý nghĩa để mời ai nữa.
            BookingWaitlist::where('sports_field_id', $fieldId)->whereDate('booking_date', $date)
                ->overlapping($start, $end)->whereIn('status', ['waiting', 'offered'])
                ->update(['status' => 'expired']);

            return null;
        }

        return DB::transaction(function () use ($fieldId, $date, $start, $end) {
            // Khoá dòng sân để serialize cùng khoá với BookingService::create và acceptOffer
            $field = SportsField::query()->lockForUpdate()->find($fieldId);
            if (! $field) {
                return null;
            }

            // Đã có người đang được offer mà chưa hết hạn thì giữ nguyên.
            $activeOffer = BookingWaitlist::where('sports_field_id', $fieldId)
                ->whereDate('booking_date', $date)
                ->where('status', 'offered')
                ->where('offer_expires_at', '>', now())
                ->overlapping($start, $end)
                ->lockForUpdate()
                ->first();

            if ($activeOffer) {
                return $activeOffer;
            }

            // Ứng viên kế tiếp có điểm ưu tiên cao nhất và khoảng giờ muốn đặt đang trống hoàn toàn.
            $candidates = BookingWaitlist::where('sports_field_id', $fieldId)
                ->whereDate('booking_date', $date)
                ->where('status', 'waiting')
                ->overlapping($start, $end)
                ->orderByDesc('priority_score')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $candidate = $candidates->first(fn (BookingWaitlist $w) => ! Booking::where('sports_field_id', $fieldId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->overlapping($w->start_time, $w->end_time)
                ->lockForUpdate()
                ->exists());

            if (! $candidate) {
                return null;
            }

            $candidate->update([
                'status' => 'offered',
                'offered_at' => now(),
                'offer_expires_at' => now()->addMinutes(15),
            ]);

            $candidate->load(['user', 'sportsField', 'timeSlot']);
            SafeMail::queue($candidate->user->email, new WaitlistSlotOffered($candidate));

            return $candidate;
        });
    }

    /**
     * Khách hàng chấp nhận lời mời giữ chỗ khi có offer.
     */
    public function acceptOffer(User $user, int $waitlistId): Booking
    {
        $booking = DB::transaction(function () use ($user, $waitlistId) {
            // Các nhánh hết hạn/bị chiếm vẫn phải ghi trạng thái; ném lỗi trong transaction sẽ rollback chúng,
            // nên trả message ra ngoài và ném sau khi commit.
            $waitlist = BookingWaitlist::where('id', $waitlistId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($waitlist->status !== 'offered') {
                throw new ConflictException('Lời mời này không còn khả dụng hoặc đã kết thúc.');
            }

            // Khoá dòng sân để serialize cùng khoá với BookingService::create
            $field = SportsField::query()->approved()->lockForUpdate()->find($waitlist->sports_field_id);
            if (! $field || ! $field->is_active) {
                $waitlist->update(['status' => 'expired']);
                return 'Sân này hiện không còn nhận đặt.';
            }

            if ($field->field_owner_id === $user->id) {
                return 'Bạn không thể đặt sân của chính mình.';
            }

            if ($waitlist->offer_expires_at && now()->gt($waitlist->offer_expires_at)) {
                $waitlist->update(['status' => 'expired']);
                $this->handleSlotFreed($waitlist->sports_field_id, $waitlist->booking_date->toDateString(), $waitlist->start_time, $waitlist->end_time);
                return 'Rất tiếc, thời hạn 15 phút xác nhận lời mời đã hết.';
            }

            // Kiểm tra phòng ngừa tranh chấp slot đồng thời
            $isOccupied = Booking::where('sports_field_id', $waitlist->sports_field_id)
                ->whereDate('booking_date', $waitlist->booking_date)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->overlapping($waitlist->start_time, $waitlist->end_time)
                ->lockForUpdate()
                ->exists();

            if ($isOccupied) {
                $waitlist->update(['status' => 'expired']);
                return 'Khung giờ này đã được người khác xác nhận trước.';
            }

            $maxPending = \App\Support\Settings::getInt('max_pending_bookings', 3);
            $pendingCount = Booking::where('user_id', $user->id)
                ->where('status', BookingStatus::Pending->value)
                ->count();

            if ($pendingCount >= $maxPending) {
                return "Bạn đang có {$pendingCount} đơn chờ xử lý. Tối đa chỉ được có {$maxPending} đơn cùng lúc.";
            }

            // Khung giờ bị tắt/khoá trong lúc chờ thì lời mời không còn giá trị.
            $dateStr = $waitlist->booking_date->toDateString();
            try {
                if ($field->closureBlocking($dateStr, $waitlist->start_time, $waitlist->end_time)) {
                    throw new DomainException('Sân đang khoá khung giờ này.');
                }
                $totalPrice = $field->priceFor($dateStr, $waitlist->start_time, $waitlist->end_time);
            } catch (DomainException) {
                $waitlist->update(['status' => 'expired']);
                return 'Khung giờ này hiện không còn nhận đặt.';
            }

            $booking = Booking::create([
                'user_id' => $user->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $waitlist->time_slot_id,
                'booking_date' => $waitlist->booking_date,
                'start_time' => $waitlist->start_time,
                'end_time' => $waitlist->end_time,
                'status' => BookingStatus::Pending->value,
                'total_price' => $totalPrice,
                'notes' => 'Đặt sân thông qua Hàng đợi thông minh (Smart Waitlist)',
            ]);

            $waitlist->update(['status' => 'accepted']);
            // Nhận lời mời = giữ chỗ như đặt thường: chờ khách chuyển khoản vào tài khoản nền tảng.
            app(PaymentService::class)->createFor($user, [$booking], 'deposit');

            return $booking->refresh()->load([...Booking::resourceRelations(withCustomer: true), 'sportsField.owner']);
        });

        if (is_string($booking)) {
            throw new ConflictException($booking);
        }

        return $booking;
    }

    /**
     * Khách hàng hủy chờ hoặc từ chối offer.
     */
    public function cancel(User $user, int $waitlistId): BookingWaitlist
    {
        return DB::transaction(function () use ($user, $waitlistId) {
            $waitlist = BookingWaitlist::where('id', $waitlistId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasOffered = ($waitlist->status === 'offered');
            $waitlist->update(['status' => 'cancelled']);

            // Nếu người này từ chối khi đang được offer -> ngay lập tức chuyển cơ hội cho người tiếp theo!
            if ($wasOffered) {
                $this->handleSlotFreed($waitlist->sports_field_id, $waitlist->booking_date->toDateString(), $waitlist->start_time, $waitlist->end_time);
            }

            return $waitlist;
        });
    }

    /**
     * Quét và xử lý các offer đã quá hạn 15 phút.
     */
    public function expireOffers(): int
    {
        $expiredCount = 0;

        // Dọn hàng đợi của khung giờ đã bắt đầu (waiting/offered quá giờ) để không treo vô thời hạn.
        BookingWaitlist::whereIn('status', ['waiting', 'offered'])
            ->whereDate('booking_date', '<=', today())
            ->get()
            ->each(function (BookingWaitlist $w) {
                if (Carbon::parse($w->booking_date->toDateString().' '.$w->start_time)->isPast()) {
                    $w->update(['status' => 'expired']);
                }
            });
        $expiredWaitlists = BookingWaitlist::where('status', 'offered')
            ->where('offer_expires_at', '<', now())
            ->get();

        foreach ($expiredWaitlists as $waitlist) {
            DB::transaction(function () use ($waitlist, &$expiredCount) {
                $w = BookingWaitlist::where('id', $waitlist->id)->lockForUpdate()->first();
                if ($w && $w->status === 'offered' && now()->gt($w->offer_expires_at)) {
                    $w->update(['status' => 'expired']);
                    $expiredCount++;
                    $this->handleSlotFreed($w->sports_field_id, $w->booking_date->toDateString(), $w->start_time, $w->end_time);
                }
            });
        }

        return $expiredCount;
    }
}
