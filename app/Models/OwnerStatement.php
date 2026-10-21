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
        'collected_amount',
        'refunded_amount',
        'commission_amount',
        'payout_amount',
        'status',
        'due_at',
        'transfer_code',
        'payout_ref',
        'reported_at',
        'paid_at',
        'confirmed_by',
        'note',
    ];

    protected $casts = [
        'booking_count' => 'integer',
        'gmv' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
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
        // SportHub chậm chi trả cho chủ sân so với hạn cam kết.
        return $this->status === 'unpaid'
            && (float) $this->payout_amount > 0
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
