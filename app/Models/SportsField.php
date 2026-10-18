<?php

namespace App\Models;

use App\Enums\FieldStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportsField extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'field_owner_id',
        'field_type_id',
        'name',
        'slug',
        'address',
        'latitude',
        'longitude',
        'description',
        'price_per_hour',
        'status',
        'rejection_reason',
        'is_active',
    ];

    protected $casts = [
        'status' => FieldStatus::class,
        'price_per_hour' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'field_owner_id');
    }

    public function fieldType()
    {
        return $this->belongsTo(FieldType::class);
    }

    public function images()
    {
        return $this->hasMany(FieldImage::class)->orderBy('sort_order', 'asc');
    }

    public function primaryImage()
    {
        return $this->hasOne(FieldImage::class)->where('is_primary', true);
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function closures()
    {
        return $this->hasMany(FieldClosure::class)->orderBy('start_date', 'asc');
    }

    public function isClosedOn(string|\Carbon\CarbonInterface $date): bool
    {
        $d = is_string($date) ? $date : $date->toDateString();

        return $this->closures()
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->exists();
    }

    public function closureOn(string|\Carbon\CarbonInterface $date): ?FieldClosure
    {
        $d = is_string($date) ? $date : $date->toDateString();

        return $this->closures()
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->first();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function visibleReviews()
    {
        return $this->hasMany(Review::class)->where('is_visible', true);
    }

    public function scopeWithRating($query)
    {
        return $query->withAvg('visibleReviews', 'rating')->withCount('visibleReviews');
    }

    public function averageRating()
    {
        return $this->reviews()->where('is_visible', true)->avg('rating') ?: 0;
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->where('is_active', true)
            // Chủ sân bị thu hồi (suspended) hoặc bị khoá tài khoản thì sân tự ẩn khỏi danh sách công khai.
            ->whereHas('owner', fn ($owner) => $owner->where('is_active', true)
                ->whereHas('fieldOwnerProfile', fn ($q) => $q->where('verification_status', 'approved')));
    }
}
