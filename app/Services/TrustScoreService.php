<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TrustScoreLog;
use App\Models\User;

class TrustScoreService
{
    /** Tăng điểm tin cậy khi khách đến sân và hoàn tất check-in thành công (+10 điểm, tối đa 150). */
    public static function rewardCheckIn(User $user, ?Booking $booking = null): void
    {
        self::apply($user, 10, 'Hoàn tất check-in đúng hẹn (+10 điểm)', $booking);
    }

    /** Trừ điểm khi hủy sát giờ (< 4h trước giờ chơi) (-15 điểm). */
    public static function penalizeLateCancellation(User $user, ?Booking $booking = null): void
    {
        self::apply($user, -15, 'Hủy đặt sân sát giờ chơi (< 4h) (-15 điểm)', $booking);
    }

    /** Trừ điểm khi vắng mặt không đến sân (No-show) (-25 điểm). */
    public static function penalizeNoShow(User $user, ?Booking $booking = null): void
    {
        self::apply($user, -25, 'Không đến nhận sân và không hủy trước (No-show) (-25 điểm)', $booking);
    }

    /** Trả điểm khi khiếu nại được chấp nhận: khách thực tế đã đến sân nên không phải vắng mặt (+25 điểm). */
    public static function restoreNoShow(User $user, ?Booking $booking = null): void
    {
        self::apply($user, 25, 'Khiếu nại được chấp nhận: gỡ ghi nhận vắng mặt (+25 điểm)', $booking);
    }


    /** Cập nhật điểm trong khoảng 0–150 và ghi log theo mức thay đổi thực tế (không phải mức danh nghĩa). */
    private static function apply(User $user, int $delta, string $reason, ?Booking $booking): void
    {
        $newScore = max(0, min(150, $user->trust_score + $delta));
        $actual = $newScore - $user->trust_score;
        if ($actual !== $delta) {
            $reason .= $actual === 0
                ? ' — điểm đang ở giới hạn nên không thay đổi'
                : " — chỉ áp dụng {$actual} điểm do giới hạn 0–150";
        }
        $user->forceFill(['trust_score' => $newScore])->save();

        TrustScoreLog::create([
            'user_id' => $user->id,
            'change' => $actual,
            'score_after' => $newScore,
            'reason' => $reason,
            'booking_id' => $booking?->id,
        ]);
    }
}
