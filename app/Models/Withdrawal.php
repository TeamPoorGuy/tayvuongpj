<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lệnh rút tiền từ ví SportHub về tài khoản ngân hàng. */
class Withdrawal extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'bank_bin', 'bank_name', 'account_number', 'account_holder',
        'status', 'auto', 'reference', 'reason', 'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'auto' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
