<?php

namespace App\Services;

use App\Models\User;

class TrustScoreService
{
    /**
     * Tăng điểm tin cậy khi khách đến sân và hoàn tất check-in thành công (+10 điểm).
     */
    public static function rewardCheckIn(User $user): void
    {
        $newScore = min(150, $user->trust_score + 10);
        $user->forceFill(['trust_score' => $newScore])->save();
    }

    /**
     * Trừ điểm khi hủy sát giờ (< 4h trước giờ chơi) (-15 điểm).
     */
    public static function penalizeLateCancellation(User $user): void
    {
        $newScore = max(0, $user->trust_score - 15);
        $user->forceFill(['trust_score' => $newScore])->save();
    }

    /**
     * Trừ điểm khi vắng mặt không đến sân (No-show) (-25 điểm).
     */
    public static function penalizeNoShow(User $user): void
    {
        $newScore = max(0, $user->trust_score - 25);
        $user->forceFill(['trust_score' => $newScore])->save();
    }
}
