<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Lần thanh toán vào tài khoản nền tảng (thu hộ), gộp một hay nhiều đơn đặt sân. */
class Payment extends Model
{
    protected $fillable = [
        'user_id', 'code', 'option', 'total', 'amount', 'status', 'due_at', 'paid_at', 'source', 'bank_ref', 'confirmed_by',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'amount' => 'decimal:2',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
