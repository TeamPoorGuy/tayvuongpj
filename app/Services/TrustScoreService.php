<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TrustScoreLog;
use App\Models\User;

class TrustScoreService
{
    /**
     * Tăng điểm tin cậy khi khách đến sân và hoàn tất check-in thành công (+10 điểm).
     */
    public static function rewardCheckIn(User $user, ?Booking $booking = null): void
    {
        $newScore = min(150, $user->trust_score + 10);
        $user->forceFill(['trust_score' => $newScore])->save();

        TrustScoreLog::create([
            'user_id' => $user->id,
            'change' => 10,
            'score_after' => $newScore,
            'reason' => 'Hoàn tất check-in đúng hẹn (+10 điểm)',
            'booking_id' => $booking?->id,
        ]);
    }

    /**
     * Trừ điểm khi hủy sát giờ (< 4h trước giờ chơi) (-15 điểm).
     */
    public static function penalizeLateCancellation(User $user, ?Booking $booking = null): void
    {
        $newScore = max(0, $user->trust_score - 15);
        $user->forceFill(['trust_score' => $newScore])->save();

        TrustScoreLog::create([
            'user_id' => $user->id,
            'change' => -15,
            'score_after' => $newScore,
            'reason' => 'Hủy đặt sân sát giờ chơi (< 4h) (-15 điểm)',
            'booking_id' => $booking?->id,
        ]);
    }

    /**
     * Trừ điểm khi vắng mặt không đến sân (No-show) (-25 điểm).
     */
    public static function penalizeNoShow(User $user, ?Booking $booking = null): void
    {
        $newScore = max(0, $user->trust_score - 25);
        $user->forceFill(['trust_score' => $newScore])->save();

        TrustScoreLog::create([
            'user_id' => $user->id,
            'change' => -25,
            'score_after' => $newScore,
            'reason' => 'Không đến nhận sân và không hủy trước (No-show) (-25 điểm)',
            'booking_id' => $booking?->id,
        ]);
    }
}
