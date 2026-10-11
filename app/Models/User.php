<?php

namespace App\Models;

use App\Notifications\ResetPasswordVi;
use App\Notifications\VerifyEmailVi;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'address',
    ];

    // role / is_active are privileged: set via forceFill only, never mass-assigned.

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

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailVi);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordVi($token));
    }

    // Role helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFieldOwner(): bool
    {
        return $this->role === 'field_owner';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    // Chủ sân được duyệt: vai trò suy ra từ trạng thái hồ sơ, không phải cột role.
    public function isApprovedOwner(): bool
    {
        return $this->fieldOwnerProfile?->verification_status === VerificationStatus::Approved;
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

    public function bookingWaitlists()
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
