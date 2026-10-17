<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Support\Settings;
use Carbon\Carbon;
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
        'confirmed_at',
        'total_price',
        'notes',
        'cancelled_at',
        'cancel_reason',
        'check_in_code',
        'checked_in_at',
        'checked_in_by',
        'no_show_at',
        'payment_status',
        'payment_option',
        'payment_amount',
        'payment_code',
        'payment_due_at',
        'payment_reported_at',
        'paid_at',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'status' => BookingStatus::class,
        'total_price' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'no_show_at' => 'datetime',
        'payment_amount' => 'decimal:2',
        'payment_due_at' => 'datetime',
        'payment_reported_at' => 'datetime',
        'paid_at' => 'datetime',
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
        return 'CK-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
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

        return $this->confirmed_at->copy()->addHours(Settings::getInt('confirmed_cancel_window_hours', 2));
    }

    /** Tiền cọc: % tổng tiền theo cấu hình, làm tròn lên nghìn đồng và không vượt tổng tiền. */
    public function depositAmount(): float
    {
        $deposit = ceil((float) $this->total_price * Settings::getInt('deposit_percent', 30) / 100 / 1000) * 1000;

        return min($deposit, (float) $this->total_price);
    }

    public function canBeCancelled(): bool
    {
        return match ($this->status) {
            // Khách đã báo chuyển khoản thì phải chờ chủ sân đối chiếu, không tự huỷ khi tiền đang treo.
            BookingStatus::Pending => $this->payment_status !== 'reported',
            BookingStatus::Confirmed => now()->lt($this->cancelUntil() ?? now()) && $this->startsAt()->isFuture(),
            default => false,
        };
    }

    /** Huỷ lúc này có bị trừ điểm uy tín không: chỉ đơn đã duyệt mà sát giờ chơi. */
    public function isLateToCancel(): bool
    {
        return $this->status === BookingStatus::Confirmed
            && $this->startsAt()->lte(now()->addHours(Settings::getInt('late_cancel_hours', 4)));
    }
}
