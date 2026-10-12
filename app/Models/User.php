<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CUSTOMER = 'customer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'address',
        'is_active',
        'trust_score',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'trust_score' => 'integer',
        ];
    }

    // Role helper methods
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    // Chủ sân được duyệt: vai trò suy ra từ trạng thái hồ sơ, không phải cột role.
    public function isApprovedOwner(): bool
    {
        return $this->fieldOwnerProfile?->verification_status === FieldOwnerProfile::STATUS_APPROVED;
    }

    public function trustScoreLabel(): string
    {
        return match (true) {
            $this->trust_score >= 120 => 'Rất uy tín',
            $this->trust_score >= 90 => 'Bình thường',
            $this->trust_score >= 60 => 'Cảnh báo',
            default => 'Rất thấp (Hạn chế)',
        };
    }

    // Relationships
    public function fieldOwnerProfile()
    {
        return $this->hasOne(FieldOwnerProfile::class);
    }

    public function ownedFields()
    {
        return $this->hasMany(SportsField::class, 'field_owner_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function waitlists()
    {
        return $this->hasMany(BookingWaitlist::class);
    }

    public function matchRequests()
    {
        return $this->hasMany(MatchRequest::class);
    }

    public function matchParticipations()
    {
        return $this->hasMany(MatchParticipant::class);
    }

    public function trustScoreLogs()
    {
        return $this->hasMany(TrustScoreLog::class)->latest();
    }
}
