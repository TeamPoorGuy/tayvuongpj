<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /** Chủ sân của sân được đặt. */
    public function manage(User $user, Booking $booking): bool
    {
        return $user->id === $booking->sportsField->field_owner_id;
    }

    /** Khách đã đặt đơn. */
    public function cancel(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id;
    }
}
