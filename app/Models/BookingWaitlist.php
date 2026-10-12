<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingWaitlist extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'sports_field_id',
        'time_slot_id',
        'booking_date',
        'status',
        'priority_score',
        'offered_at',
        'offer_expires_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'priority_score' => 'integer',
        'offered_at' => 'datetime',
        'offer_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function isOffered(): bool
    {
        return $this->status === self::STATUS_OFFERED
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isFuture();
    }
}
