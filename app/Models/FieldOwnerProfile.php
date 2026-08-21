<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldOwnerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_address',
        'business_phone',
        'business_license',
        'description',
        'verification_status',
        'verification_documents',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
