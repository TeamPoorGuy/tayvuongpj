<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lần thanh toán vào tài khoản nền tảng (thu hộ), gộp một hay nhiều đơn đặt sân. */
class Payment extends Model
{
    protected $fillable = [
        'user_id', 'purpose', 'code', 'option', 'total', 'amount', 'wallet_amount', 'status', 'due_at', 'paid_at', 'source', 'bank_ref', 'confirmed_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'amount' => 'decimal:2',
        'wallet_amount' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** Số còn phải chuyển khoản sau khi đã trừ ví. */
    public function dueAmount(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->wallet_amount);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
