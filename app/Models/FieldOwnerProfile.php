<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldOwnerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'owner_name',
        'owner_id_number',
        'business_name',
        'business_address',
        'business_phone',
        'business_license',
        'description',
        'verification_status',
        'owner_id_hash',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'terms_version',
        'terms_accepted_at',
        'terms_ip',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'owner_id_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        // Hash tất định để chặn một CCCD đăng ký nhiều tài khoản (cột gốc đã mã hoá nên không so sánh được).
        static::saving(function (self $profile) {
            if ($profile->isDirty('owner_id_number') && $profile->owner_id_number) {
                $profile->owner_id_hash = self::hashIdNumber($profile->owner_id_number);
            }
        });
    }

    public static function hashIdNumber(string $idNumber): string
    {
        return hash_hmac('sha256', $idNumber, config('app.key'));
    }

    public function documents()
    {
        return $this->hasMany(OwnerDocument::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
