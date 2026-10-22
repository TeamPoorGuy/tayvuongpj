<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    protected $fillable = [
        'user_id',
        'booking_id',
        'subject_type',
        'subject_id',
        'category',
        'content',
        'status',
        'resolution',
        'refund_amount',
        'attendance_confirmed',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'attendance_confirmed' => 'boolean',
    ];

    /** Khiếu nại còn đang xử lý: đơn liên quan bị giữ lại khỏi kỳ chi trả cho tới khi đóng. */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'processing']);
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
