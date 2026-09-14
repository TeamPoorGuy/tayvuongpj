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

    /** @return list<string> */
    public static function blockingCases(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->blocksSlot())
        );
    }

    /** Field-owner-driven transitions: what a booking may become next. */
    public function canTransitionTo(self $next): bool
    {
        $allowed = match ($this) {
            self::Pending => [self::Confirmed, self::Rejected],
            self::Confirmed => [self::Completed, self::Cancelled],
            default => [],
        };

        return in_array($next, $allowed, true);
    }
}
