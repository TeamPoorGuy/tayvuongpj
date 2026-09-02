<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function blocksSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
