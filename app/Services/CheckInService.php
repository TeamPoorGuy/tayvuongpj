<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /** Check-in theo mã đơn (dùng khi khách đọc mã). */
    public function checkIn(User $owner, string $code): Booking
    {
        $booking = Booking::where('check_in_code', strtoupper(trim($code)))->first();

        if (! $booking) {
            throw new DomainException('Mã check-in không tồn tại hoặc không hợp lệ.');
        }

        return $this->checkInBooking($owner, $booking);
    }

    /**
     * Chủ sân xác nhận khách đã đến sân ngay trên đơn đặt (web không cần quét mã).
     */
    public function checkInBooking(User $owner, Booking $booking): Booking
    {
        return DB::transaction(function () use ($owner, $booking) {
            $booking = Booking::with(['customer', 'sportsField.owner', 'timeSlot'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Kiểm tra quyền: phải là chủ của sân hoặc admin
            if ($booking->sportsField->field_owner_id !== $owner->id && ! $owner->isAdmin()) {
                throw new DomainException('Đơn đặt sân này không thuộc cụm sân của bạn.');
            }

            if ($booking->status === BookingStatus::Pending) {
                throw new DomainException('Đơn chưa thanh toán xác nhận, không thể check-in.');
            }

            if ($booking->status !== BookingStatus::Confirmed) {
                throw new DomainException('Đơn đặt sân này không còn hiệu lực để check-in.');
            }

            // Chỉ check-in trong khoảng từ 60 phút trước giờ bắt đầu đến hết giờ kết thúc.
            if (now()->lt($booking->startsAt()->subMinutes(60))) {
                throw new DomainException('Chưa đến giờ check-in. Có thể check-in từ '.$booking->startsAt()->subMinutes(60)->format('H:i d/m').'.');
            }
            if (now()->gt($booking->endsAt())) {
                throw new DomainException('Đã quá giờ chơi của đơn này, không thể check-in.');
            }

            if ($booking->checked_in_at !== null) {
                throw new DomainException(
                    "Đơn này đã được check-in vào lúc {$booking->checked_in_at->format('H:i d/m/Y')}."
                );
            }

            $booking->update([
                'checked_in_at' => now(),
                'checked_in_by' => $owner->id,
            ]);

            // Cộng điểm tin cậy cho khách hàng vì đã có mặt đúng hẹn
            if ($booking->customer) {
                TrustScoreService::rewardCheckIn($booking->customer, $booking);
            }

            return $booking->refresh();
        });
    }
}
