<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustScoreLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'change',
        'score_after',
        'reason',
        'booking_id',
    ];

    protected $casts = [
        'change' => 'integer',
        'score_after' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
