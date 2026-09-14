<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    use HasFactory;

    /** Default schedule applied to a newly created field. @var list<array{0:string,1:string}> */
    public const DEFAULT_SCHEDULE = [
        ['06:00', '07:30'],
        ['07:30', '09:00'],
        ['09:00', '10:30'],
        ['14:00', '15:30'],
        ['15:30', '17:00'],
        ['17:00', '18:30'],
        ['18:30', '20:00'],
        ['20:00', '21:30'],
    ];

    protected $fillable = [
        'sports_field_id',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getFormattedSlotAttribute(): string
    {
        return substr($this->start_time, 0, 5) . ' - ' . substr($this->end_time, 0, 5);
    }

    public function durationInHours(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $start->floatDiffInHours($end, absolute: true);
    }
}
