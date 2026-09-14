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
        'verification_documents',
        'rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
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
