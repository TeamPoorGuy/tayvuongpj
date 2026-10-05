<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\User;

class CheckInService
{
    /**
     * Chủ sân thực hiện check-in cho khách qua mã QR Code.
     */
    public function checkIn(User $owner, string $code): Booking
    {
        $code = strtoupper(trim($code));

        $booking = Booking::with(['customer', 'sportsField.owner', 'timeSlot'])
            ->where('check_in_code', $code)
            ->first();

        if (! $booking) {
            throw new DomainException('Mã check-in không tồn tại hoặc không hợp lệ.');
        }

        // Kiểm tra quyền: phải là chủ của sân hoặc admin
        if ($booking->sportsField->field_owner_id !== $owner->id && ! $owner->isAdmin()) {
            throw new DomainException('Đơn đặt sân này không thuộc cụm sân của bạn.');
        }

        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Rejected], true)) {
            throw new DomainException('Đơn đặt sân này đã bị huỷ hoặc từ chối, không thể check-in.');
        }

        if ($booking->checked_in_at !== null) {
            throw new DomainException(
                "Đơn này đã được check-in vào lúc {$booking->checked_in_at->format('H:i d/m/Y')}."
            );
        }

        $booking->update([
            'checked_in_at' => now(),
            'checked_in_by' => $owner->id,
            // Nếu đơn đang pending mà khách đến sân check-in, tự động xác nhận
            'status' => BookingStatus::Confirmed->value,
        ]);

        // Cộng điểm tin cậy cho khách hàng vì đã có mặt đúng hẹn
        if ($booking->customer) {
            TrustScoreService::rewardCheckIn($booking->customer);
        }

        return $booking->refresh();
    }
}
