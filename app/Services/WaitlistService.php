<?php

namespace App\Services;

use App\Mail\NewBookingRequest;
use App\Mail\WaitlistSlotOffered;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\SafeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    /**
     * Khách hàng đăng ký vào hàng đợi của một slot đã kín.
     */
    public function join(User $user, int $fieldId, int $slotId, string $date): BookingWaitlist
    {
        $field = SportsField::approved()->findOrFail($fieldId);

        if ($field->field_owner_id === $user->id) {
            throw ValidationException::withMessages(['sports_field_id' => 'Bạn không thể đăng ký hàng đợi cho sân của chính mình.']);
        }

        $slot = TimeSlot::where('sports_field_id', $field->id)
            ->where('is_active', true)
            ->findOrFail($slotId);

        if (Carbon::parse($date.' '.$slot->start_time)->isPast()) {
            throw ValidationException::withMessages(['time_slot_id' => 'Khung giờ này đã qua. Vui lòng chọn ngày hoặc giờ khác.']);
        }

        $existing = BookingWaitlist::where('user_id', $user->id)
            ->where('sports_field_id', $fieldId)
            ->where('time_slot_id', $slotId)
            ->whereDate('booking_date', $date)
            ->whereIn('status', [BookingWaitlist::STATUS_WAITING, BookingWaitlist::STATUS_OFFERED])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['time_slot_id' => 'Bạn đã đăng ký trong hàng đợi cho khung giờ này rồi.']);
        }

        $activeWaitlistsCount = BookingWaitlist::where('user_id', $user->id)
            ->whereIn('status', [BookingWaitlist::STATUS_WAITING, BookingWaitlist::STATUS_OFFERED])
            ->count();

        if ($activeWaitlistsCount >= 5) {
            throw ValidationException::withMessages(['user_id' => 'Bạn chỉ có thể đăng ký tối đa 5 khung giờ trong hàng đợi cùng lúc.']);
        }

        return BookingWaitlist::create([
            'user_id' => $user->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'status' => BookingWaitlist::STATUS_WAITING,
            'priority_score' => $user->trust_score ?? 100,
        ])->load(['sportsField', 'timeSlot']);
    }

    /**
     * Kích hoạt tự động khi một slot được giải phóng (hủy hoặc từ chối đơn).
     */
    public function handleSlotFreed(int $fieldId, int $slotId, string $date): ?BookingWaitlist
    {
        $slotStart = TimeSlot::whereKey($slotId)->value('start_time');
        if ($slotStart && Carbon::parse($date.' '.$slotStart)->isPast()) {
            BookingWaitlist::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', [BookingWaitlist::STATUS_WAITING, BookingWaitlist::STATUS_OFFERED])
                ->update(['status' => BookingWaitlist::STATUS_EXPIRED]);

            return null;
        }

        return DB::transaction(function () use ($fieldId, $slotId, $date) {
            $isOccupied = Booking::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->whereIn('status', Booking::BLOCKING_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($isOccupied) {
                return null;
            }

            $activeOffer = BookingWaitlist::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->where('status', BookingWaitlist::STATUS_OFFERED)
                ->where('offer_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeOffer) {
                return $activeOffer;
            }

            $candidate = BookingWaitlist::where('sports_field_id', $fieldId)
                ->where('time_slot_id', $slotId)
                ->whereDate('booking_date', $date)
                ->where('status', BookingWaitlist::STATUS_WAITING)
                ->orderByDesc('priority_score')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (! $candidate) {
                return null;
            }

            $candidate->update([
                'status' => BookingWaitlist::STATUS_OFFERED,
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
    public function acceptOffer(User $user, int $waitlistId): Booking|string
    {
        $result = DB::transaction(function () use ($user, $waitlistId) {
            $waitlist = BookingWaitlist::where('id', $waitlistId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $waitlist) {
                return 'Hàng đợi không tồn tại.';
            }

            if ($waitlist->status !== BookingWaitlist::STATUS_OFFERED) {
                return 'Lời mời này không còn khả dụng hoặc đã kết thúc.';
            }

            if ($waitlist->offer_expires_at && now()->gt($waitlist->offer_expires_at)) {
                $waitlist->update(['status' => BookingWaitlist::STATUS_EXPIRED]);
                $this->handleSlotFreed($waitlist->sports_field_id, $waitlist->time_slot_id, $waitlist->booking_date->toDateString());
                return 'Rất tiếc, thời hạn 15 phút xác nhận lời mời đã hết.';
            }

            $isOccupied = Booking::where('sports_field_id', $waitlist->sports_field_id)
                ->where('time_slot_id', $waitlist->time_slot_id)
                ->whereDate('booking_date', $waitlist->booking_date)
                ->whereIn('status', Booking::BLOCKING_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($isOccupied) {
                $waitlist->update(['status' => BookingWaitlist::STATUS_EXPIRED]);
                return 'Khung giờ này đã được người khác xác nhận trước.';
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
                'status' => Booking::STATUS_PENDING,
                'total_price' => $totalPrice,
                'notes' => 'Đặt sân thông qua Hàng đợi thông minh (Smart Waitlist)',
            ]);

            $waitlist->update(['status' => BookingWaitlist::STATUS_ACCEPTED]);

            return $booking;
        });

        if (is_string($result)) {
            return $result;
        }

        $result->load(['sportsField.owner', 'timeSlot', 'customer']);
        if ($result->sportsField?->owner?->email) {
            SafeMail::queue($result->sportsField->owner->email, new NewBookingRequest($result));
        }

        return $result;
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

            $wasOffered = ($waitlist->status === BookingWaitlist::STATUS_OFFERED);
            $waitlist->update(['status' => BookingWaitlist::STATUS_CANCELLED]);

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

        BookingWaitlist::whereIn('status', [BookingWaitlist::STATUS_WAITING, BookingWaitlist::STATUS_OFFERED])
            ->whereDate('booking_date', '<=', today())
            ->with('timeSlot')
            ->get()
            ->each(function (BookingWaitlist $w) {
                if ($w->timeSlot && Carbon::parse($w->booking_date->toDateString().' '.$w->timeSlot->start_time)->isPast()) {
                    $w->update(['status' => BookingWaitlist::STATUS_EXPIRED]);
                }
            });

        $expiredWaitlists = BookingWaitlist::where('status', BookingWaitlist::STATUS_OFFERED)
            ->where('offer_expires_at', '<', now())
            ->get();

        foreach ($expiredWaitlists as $waitlist) {
            DB::transaction(function () use ($waitlist, &$expiredCount) {
                $w = BookingWaitlist::where('id', $waitlist->id)->lockForUpdate()->first();
                if ($w && $w->status === BookingWaitlist::STATUS_OFFERED && now()->gt($w->offer_expires_at)) {
                    $w->update(['status' => BookingWaitlist::STATUS_EXPIRED]);
                    $expiredCount++;
                    $this->handleSlotFreed($w->sports_field_id, $w->time_slot_id, $w->booking_date->toDateString());
                }
            });
        }

        return $expiredCount;
    }
}
