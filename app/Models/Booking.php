<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sports_field_id',
        'time_slot_id',
        'booking_date',
        'status',
        'total_price',
        'notes',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'status' => BookingStatus::class,
        'total_price' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Relations BookingResource needs to render without omitting a field.
     *
     * @return list<string>
     */
    public static function resourceRelations(bool $withCustomer = false): array
    {
        $relations = ['sportsField.primaryImage', 'sportsField.reviews', 'timeSlot', 'review'];
        if ($withCustomer) {
            $relations[] = 'customer';
        }

        return $relations;
    }

    public function canBeCancelled(): bool
    {
        return $this->status->blocksSlot() && $this->booking_date->isFuture();
    }
}
