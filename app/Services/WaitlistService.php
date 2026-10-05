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
        $field = SportsField::where('is_active', true)->where('status', 'approved')->findOrFail($fieldId);
        $slot = TimeSlot::where('sports_field_id', $fieldId)->where('is_active', true)->findOrFail($slotId);

        $parsedDate = Carbon::parse($date)->startOfDay();
        if ($parsedDate->isPast() && ! $parsedDate->isToday()) {
            throw new DomainException('Không thể đăng ký hàng đợi cho ngày trong quá khứ.');
        }

        // Kiểm tra xem slot có thực sự bị đặt chưa
        $isBooked = Booking::where('sports_field_id', $fieldId)
            ->where('time_slot_id', $slotId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->exists();

        if (! $isBooked) {
            throw new ConflictException('Khung giờ này hiện đang còn trống, bạn có thể đặt trực tiếp mà không cần vào hàng đợi.');
        }

        // Kiểm tra user đã có booking cho slot này chưa
        $userHasBooking = Booking::where('user_id', $user->id)
            ->where('sports_field_id', $fieldId)
            ->where('time_slot_id', $slotId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->exists();

        if ($userHasBooking) {
            throw new ConflictException('Bạn đã có đơn đặt sân cho khung giờ này.');
        }

        // Kiểm tra user đã có trong waitlist đang active chưa
        $existing = BookingWaitlist::where('user_id', $user->id)
            ->where('sports_field_id', $fieldId)
            ->where('time_slot_id', $slotId)
            ->whereDate('booking_date', $parsedDate)
            ->whereIn('status', ['waiting', 'offered'])
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

    /**
     * Kích hoạt tự động khi một slot được giải phóng (hủy hoặc từ chối đơn).
     */
    public function handleSlotFreed(int $fieldId, int $slotId, string $date): ?BookingWaitlist
    {
        return DB::transaction(function () use ($fieldId, $slotId, $date) {
            // Kiểm tra xem hiện tại slot có bị đơn nào khác giữ không
            $isOccupied = Booking::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->lockForUpdate()
                ->exists();

            if ($isOccupied) {
                return null;
            }

            // Kiểm tra xem đã có ai đang được offer mà chưa hết hạn không
            $activeOffer = BookingWaitlist::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->where('status', 'offered')
                ->where('offer_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeOffer) {
                return $activeOffer;
            }

            // Tìm ứng viên kế tiếp có điểm ưu tiên cao nhất
            $candidate = BookingWaitlist::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->where('status', 'waiting')
                ->orderByDesc('priority_score')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

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
        return DB::transaction(function () use ($user, $waitlistId) {
            $waitlist = BookingWaitlist::where('id', $waitlistId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($waitlist->status !== 'offered') {
                throw new ConflictException('Lời mời này không còn khả dụng hoặc đã kết thúc.');
            }

            if ($waitlist->offer_expires_at && now()->gt($waitlist->offer_expires_at)) {
                $waitlist->update(['status' => 'expired']);
                $this->handleSlotFreed($waitlist->sports_field_id, $waitlist->time_slot_id, $waitlist->booking_date->toDateString());
                throw new ConflictException('Rất tiếc, thời hạn 15 phút xác nhận lời mời đã hết.');
            }

            // Kiểm tra phòng ngừa tranh chấp slot đồng thời
            $isOccupied = Booking::where('sports_field_id', $waitlist->sports_field_id)
                ->where('time_slot_id', $waitlist->time_slot_id)
                ->whereDate('booking_date', $waitlist->booking_date)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->lockForUpdate()
                ->exists();

            if ($isOccupied) {
                $waitlist->update(['status' => 'expired']);
                throw new ConflictException('Khung giờ này đã được người khác xác nhận trước.');
            }

            $slot = $waitlist->timeSlot;
            $field = $waitlist->sportsField;
            $pricePerHour = $slot->price_per_hour ?? $field->price_per_hour;
            $totalPrice = $pricePerHour * $slot->durationInHours();

            $booking = Booking::create([
                'user_id' => $user->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $waitlist->booking_date,
                'status' => BookingStatus::Pending->value,
                'total_price' => $totalPrice,
                'notes' => 'Đặt sân thông qua Hàng đợi thông minh (Smart Waitlist)',
            ]);

            $waitlist->update(['status' => 'accepted']);

            return $booking->load([...Booking::resourceRelations(withCustomer: true), 'sportsField.owner']);
        });
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
                $this->handleSlotFreed($waitlist->sports_field_id, $waitlist->time_slot_id, $waitlist->booking_date->toDateString());
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
        $expiredWaitlists = BookingWaitlist::where('status', 'offered')
            ->where('offer_expires_at', '<', now())
            ->get();

        foreach ($expiredWaitlists as $waitlist) {
            DB::transaction(function () use ($waitlist, &$expiredCount) {
                $w = BookingWaitlist::where('id', $waitlist->id)->lockForUpdate()->first();
                if ($w && $w->status === 'offered' && now()->gt($w->offer_expires_at)) {
                    $w->update(['status' => 'expired']);
                    $expiredCount++;
                    $this->handleSlotFreed($w->sports_field_id, $w->time_slot_id, $w->booking_date->toDateString());
                }
            });
        }

        return $expiredCount;
    }
}
