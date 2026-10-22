<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'user_id',
        'direction',
        'against_user_id',
        'booking_id',
        'subject_type',
        'subject_id',
        'category',
        'content',
        'response',
        'responded_at',
        'respond_by',
        'status',
        'resolution',
        'refund_amount',
        'attendance_confirmed',
        'trust_penalty',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'responded_at' => 'datetime',
        'respond_by' => 'datetime',
        'attendance_confirmed' => 'boolean',
    ];

    /** Khiếu nại còn đang xử lý: đơn liên quan bị giữ lại khỏi kỳ chi trả cho tới khi đóng. */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'processing']);
    }

    /** Khiếu nại của khách về chủ sân còn mở: đơn liên quan bị giữ khỏi kỳ chi trả (khiếu nại của chính chủ sân thì không). */
    public function scopeHoldingPayout($query)
    {
        return $query->active()->where('direction', 'customer_to_owner');
    }

    public function against(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
