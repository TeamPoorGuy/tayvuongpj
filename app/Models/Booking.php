<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
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
        'confirmed_at',
        'total_price',
        'notes',
        'cancelled_at',
        'cancel_reason',
        'check_in_code',
        'checked_in_at',
        'checked_in_by',
        'no_show_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'status' => BookingStatus::class,
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'no_show_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->check_in_code)) {
                $booking->check_in_code = static::generateCheckInCode();
            }
        });
    }

    public static function generateCheckInCode(): string
    {
        return 'CK-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class)->withTrashed();
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
        $relations = ['sportsField.primaryImage', 'timeSlot', 'review'];
        if ($withCustomer) {
            $relations[] = 'customer';
        }

        return $relations;
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->booking_date->toDateString().' '.$this->timeSlot->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->booking_date->toDateString().' '.$this->timeSlot->end_time);
    }

    /** Hạn chót khách tự huỷ đơn đã duyệt; null nếu đơn không thuộc diện này. */
    public function cancelUntil(): ?Carbon
    {
        if ($this->status !== BookingStatus::Confirmed || $this->confirmed_at === null) {
            return null;
        }

        return $this->confirmed_at->copy()->addHours((int) config('booking.confirmed_cancel_window_hours'));
    }

    public function canBeCancelled(): bool
    {
        return match ($this->status) {
            BookingStatus::Pending => true,
            BookingStatus::Confirmed => now()->lt($this->cancelUntil() ?? now()) && $this->startsAt()->isFuture(),
            default => false,
        };
    }

    /** Huỷ lúc này có bị trừ điểm uy tín không: chỉ đơn đã duyệt mà sát giờ chơi. */
    public function isLateToCancel(): bool
    {
        return $this->status === BookingStatus::Confirmed
            && $this->startsAt()->lte(now()->addHours((int) config('booking.late_cancel_hours')));
    }
}
