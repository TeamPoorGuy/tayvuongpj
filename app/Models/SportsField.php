<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportsField extends Model
{
    use HasFactory;

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
        'is_active',
    ];

    protected $casts = [
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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function averageRating()
    {
        return $this->reviews()->where('is_visible', true)->avg('rating') ?: 0;
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->where('is_active', true);
    }
}
