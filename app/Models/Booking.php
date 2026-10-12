<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    public const BLOCKING_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

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

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Các quan hệ thường dùng khi hiển thị booking trên Blade.
     *
     * @return list<string>
     */
    public static function displayRelations(bool $withCustomer = false): array
    {
        $relations = ['sportsField.primaryImage', 'sportsField.reviews', 'timeSlot', 'review'];
        if ($withCustomer) {
            $relations[] = 'customer';
        }

        return $relations;
    }

    public function startsAt(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->booking_date->toDateString().' '.$this->timeSlot->start_time);
    }

    public function endsAt(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->booking_date->toDateString().' '.$this->timeSlot->end_time);
    }

    /** Hạn chót khách tự huỷ đơn đã duyệt; null nếu đơn không thuộc diện này. */
    public function cancelUntil(): ?\Carbon\Carbon
    {
        if ($this->status !== self::STATUS_CONFIRMED || $this->confirmed_at === null) {
            return null;
        }

        $hours = (int) config('booking.confirmed_cancel_window_hours', 2);
        return $this->confirmed_at->copy()->addHours($hours);
    }

    public function canBeCancelled(): bool
    {
        return match ($this->status) {
            self::STATUS_PENDING => $this->startsAt()->isFuture(),
            self::STATUS_CONFIRMED => (
                ($this->cancelUntil() === null || now()->lt($this->cancelUntil()))
                && $this->startsAt()->isFuture()
            ),
            default => false,
        };
    }

    /** Huỷ lúc này có bị trừ điểm uy tín không: chỉ đơn đã duyệt mà sát giờ chơi. */
    public function isLateToCancel(): bool
    {
        $lateHours = (int) config('booking.late_cancel_hours', 4);
        return $this->status === self::STATUS_CONFIRMED
            && $this->startsAt()->lte(now()->addHours($lateHours));
    }

    public function canCheckIn(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
            && $this->checked_in_at === null
            && now()->gte($this->startsAt()->subMinutes(60))
            && now()->lte($this->endsAt());
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        $allowed = match ($this->status) {
            self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_REJECTED],
            self::STATUS_CONFIRMED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            default => [],
        };

        return in_array($nextStatus, $allowed, true);
    }
}
