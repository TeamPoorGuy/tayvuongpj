<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_profile_id',
        'period',
        'booking_count',
        'gmv',
        'commission_amount',
        'status',
        'due_at',
        'transfer_code',
        'reported_at',
        'paid_at',
        'confirmed_by',
        'note',
    ];

    protected $casts = [
        'booking_count' => 'integer',
        'gmv' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'due_at' => 'datetime',
        'reported_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function ownerProfile()
    {
        return $this->belongsTo(FieldOwnerProfile::class, 'owner_profile_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'statement_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'unpaid'
            && (float) $this->commission_amount > 0
            && $this->due_at
            && $this->due_at->isPast();
    }

    public static function generateTransferCode(string $period, int $profileId): string
    {
        $cleanPeriod = str_replace('-', '', $period);
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        return "ST{$cleanPeriod}P{$profileId}{$random}";
    }
}
