<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;

class ReviewService
{
    public function create(User $user, int $bookingId, int $rating, string $comment): Review
    {
        $booking = Booking::query()
            ->where('user_id', $user->id)
            ->where('status', BookingStatus::Completed->value)
            ->with('review')
            ->findOrFail($bookingId);

        if ($booking->review) {
            throw new ConflictException('Bạn đã đánh giá lượt đặt sân này rồi.');
        }

        return Review::create([
            'user_id' => $user->id,
            'sports_field_id' => $booking->sports_field_id,
            'booking_id' => $booking->id,
            'rating' => $rating,
            'comment' => $comment,
            'is_visible' => true,
        ])->load(['user', 'sportsField']);
    }
}
