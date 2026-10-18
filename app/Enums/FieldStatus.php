<?php

namespace App\Enums;

enum FieldStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inactive = 'inactive';

    /** Admin-driven transitions. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Rejected],
            self::Rejected => [self::Approved],
            default => [],
        }, true);
    }
}
