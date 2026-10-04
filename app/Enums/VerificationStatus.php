<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /** Admin-driven transitions. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Suspended],
            self::Suspended => [self::Approved],
            default => [],
        }, true);
    }
}
