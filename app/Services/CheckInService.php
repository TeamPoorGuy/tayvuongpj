<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    /** Check-in theo mã đơn (dùng khi khách đọc mã hoặc quét QR). */
    public function checkIn(User $owner, string $code): Booking
    {
        $booking = Booking::where('check_in_code', strtoupper(trim($code)))->first();

        if (! $booking) {
            throw ValidationException::withMessages(['code' => 'Mã check-in không tồn tại hoặc không hợp lệ.']);
        }

        return $this->checkInBooking($owner, $booking);
    }

    /**
     * Chủ sân xác nhận khách đã đến sân ngay trên đơn đặt.
     */
    public function checkInBooking(User $owner, Booking $booking): Booking
    {
        return DB::transaction(function () use ($owner, $booking) {
            $booking = Booking::with(['customer', 'sportsField.owner', 'timeSlot'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->sportsField->field_owner_id !== $owner->id && ! $owner->isAdmin()) {
                throw ValidationException::withMessages(['booking' => 'Đơn đặt sân này không thuộc cụm sân của bạn.']);
            }

            if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages(['booking' => 'Đơn đặt sân này không còn hiệu lực để check-in.']);
            }

            // Chỉ check-in trong khoảng từ 60 phút trước giờ bắt đầu đến hết giờ kết thúc.
            if (now()->lt($booking->startsAt()->subMinutes(60))) {
                throw ValidationException::withMessages([
                    'booking' => 'Chưa đến giờ check-in. Có thể check-in từ '.$booking->startsAt()->subMinutes(60)->format('H:i d/m').'.',
                ]);
            }
            if (now()->gt($booking->endsAt())) {
                throw ValidationException::withMessages(['booking' => 'Đã quá giờ chơi của đơn này, không thể check-in.']);
            }

            if ($booking->checked_in_at !== null) {
                throw ValidationException::withMessages([
                    'booking' => "Đơn này đã được check-in vào lúc {$booking->checked_in_at->format('H:i d/m/Y')}.",
                ]);
            }

            $booking->update([
                'checked_in_at' => now(),
                'checked_in_by' => $owner->id,
                'status' => Booking::STATUS_CONFIRMED,
                'confirmed_at' => $booking->confirmed_at ?? now(),
            ]);

            // Cộng 10 điểm tin cậy cho khách hàng vì đã có mặt đúng hẹn
            if ($booking->customer) {
                TrustScoreService::rewardCheckIn($booking->customer, $booking);
            }

            return $booking->refresh();
        });
    }
}
