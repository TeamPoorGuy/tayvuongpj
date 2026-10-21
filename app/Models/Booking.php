<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\HasTimeRange;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory, HasTimeRange;

    protected $fillable = [
        'user_id',
        'sports_field_id',
        'time_slot_id',
        'booking_date',
        'start_time',
        'end_time',
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
        'payment_proof_path',
        'paid_at',
        'refund_status',
        'refund_amount',
        'refunded_at',
        'commission_rate',
        'commission_amount',
        'statement_id',
        'recurring_group_id',
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
        'refund_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            if (empty($booking->check_in_code)) {
                $booking->check_in_code = static::generateCheckInCode();
            }
            if ($booking->commission_rate === null) {
                $booking->commission_rate = Settings::getInt('commission_percent', 8);
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

    public function statement()
    {
        return $this->belongsTo(OwnerStatement::class, 'statement_id');
    }

    public function recurringGroup()
    {
        return $this->hasMany(Booking::class, 'recurring_group_id', 'recurring_group_id');
    }

    /**
     * Cơ sở tính hoa hồng cho nền tảng (Agoda model):
     * - Hoàn thành và đã check-in: 100% total_price
     * - No-show: payment_amount (chủ sân giữ tiền cọc)
     * - Khách huỷ trễ (late cancel): payment_amount (chủ sân giữ tiền cọc)
     * - Các trường hợp khác (hoàn tiền, từ chối, huỷ đúng hạn): 0
     */
    public function commissionBase(): float
    {
        if ($this->status === BookingStatus::Completed && $this->checked_in_at !== null) {
            return (float) $this->total_price;
        }

        // Đơn đã xác nhận (chưa đến giờ check-in nhưng đã giữ chỗ/cọc): cơ sở hoa hồng ước tính trên tổng đơn
        if ($this->status === BookingStatus::Confirmed) {
            return (float) $this->total_price;
        }

        if ($this->no_show_at !== null) {
            return (float) ($this->payment_amount ?? $this->depositAmount());
        }

        // Đơn huỷ mà chủ sân được giữ tiền (khách huỷ muộn / phạt cọc, không hoàn tiền)
        if ($this->status === BookingStatus::Cancelled && $this->payment_status === 'paid' && in_array($this->refund_status, [null, 'waived'], true)) {
            return (float) ($this->payment_amount ?? $this->depositAmount());
        }

        return 0.0;
    }

    /**
     * Tính toán thuần túy hoa hồng (không ghi vào DB).
     */
    public function computeCommission(): float
    {
        $base = $this->commissionBase();
        $rate = (float) ($this->commission_rate ?? Settings::getInt('commission_percent', 8));

        return round($base * $rate / 100, 2);
    }

    /**
     * Tính toán và lưu giá trị commission_amount cho booking.
     */
    public function calculateCommission(): float
    {
        $amount = $this->computeCommission();

        $this->update(['commission_amount' => $amount]);

        return $amount;
    }

    /**
     * Relations BookingResource needs to render without omitting a field.
     *
     * @return list<string>
     */
    public static function resourceRelations(bool $withCustomer = false): array
    {
        $relations = [
            'sportsField.primaryImage',
            'sportsField.owner.fieldOwnerProfile',
            'review',
        ];
        if ($withCustomer) {
            $relations[] = 'customer';
        }

        return $relations;
    }

    public function startsAt(): Carbon
    {
        return Carbon::parse($this->booking_date->toDateString().' '.$this->start_time);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse($this->booking_date->toDateString().' '.$this->end_time);
    }

    /**
     * Hạn chót khách tự huỷ đơn đã duyệt mà vẫn được miễn phí (hoàn cọc, không trừ điểm).
     * Trả về null nếu đơn không thuộc diện này hoặc đã hết hạn huỷ miễn phí.
     */
    public function cancelUntil(): ?Carbon
    {
        if ($this->status !== BookingStatus::Confirmed) {
            return null;
        }

        $freeHours = Settings::getInt('free_cancel_hours', Settings::getInt('late_cancel_hours', 4));
        $freeDeadline = $this->startsAt()->subHours($freeHours);

        $graceDeadline = $this->confirmed_at
            ? $this->confirmed_at->copy()->addHours(Settings::getInt('confirmed_cancel_window_hours', 2))->min($this->startsAt()->subHour())
            : null;

        if ($graceDeadline && $graceDeadline->gt($freeDeadline)) {
            return $graceDeadline;
        }

        return $freeDeadline->isFuture() ? $freeDeadline : null;
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
            // Đơn confirmed huỷ được bất kỳ lúc nào trước giờ chơi
            BookingStatus::Confirmed => $this->startsAt()->isFuture(),
            default => false,
        };
    }

    /**
     * Kiểm tra xem đơn có được huỷ miễn phí (không trừ điểm, được hoàn cọc) hay không.
     */
    public function isFreeCancel(): bool
    {
        if ($this->status !== BookingStatus::Confirmed) {
            return true;
        }

        $freeCancelHours = Settings::getInt('free_cancel_hours', Settings::getInt('late_cancel_hours', 4));
        $startsAt = $this->startsAt();
        $now = now();

        // 1. Huỷ trước giờ chơi từ free_cancel_hours trở lên
        if ($startsAt->gte($now->copy()->addHours($freeCancelHours))) {
            return true;
        }

        // 2. Hoặc trong 2 giờ ân hạn sau khi được chủ sân duyệt, nhưng vẫn phải trước giờ chơi ít nhất 1 giờ
        $graceWindowHours = Settings::getInt('confirmed_cancel_window_hours', 2);
        if ($this->confirmed_at && $now->lte($this->confirmed_at->copy()->addHours($graceWindowHours))) {
            if ($startsAt->gte($now->copy()->addHours(1))) {
                return true;
            }
        }

        return false;
    }

    /** Huỷ lúc này có bị trừ điểm uy tín không: chỉ đơn đã duyệt mà bị tính là huỷ muộn. */
    public function isLateToCancel(): bool
    {
        if ($this->status !== BookingStatus::Confirmed) {
            return false;
        }

        return ! $this->isFreeCancel();
    }

    /**
     * Huỷ đơn đặt sân từ bất kỳ nguồn nào và tự động tính toán hoàn tiền theo chính sách.
     */
    public function markCancelled(string $by, ?string $reason = null, BookingStatus $targetStatus = BookingStatus::Cancelled): self
    {
        $refundStatus = null;
        $refundAmount = null;

        $hasPaidOrReported = in_array($this->payment_status, ['reported', 'paid'], true);

        if ($hasPaidOrReported) {
            if ($by === 'customer') {
                if ($this->status === BookingStatus::Confirmed && ! $this->isLateToCancel()) {
                    $refundStatus = 'pending';
                    $refundAmount = $this->payment_amount ?? $this->depositAmount();
                } else {
                    $refundStatus = null;
                    $refundAmount = null;
                }
            } else {
                $refundStatus = 'pending';
                $refundAmount = $this->payment_amount ?? $this->depositAmount();
            }
        }

        $this->update([
            'status' => $targetStatus->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'refund_status' => $refundStatus,
            'refund_amount' => $refundAmount,
        ]);

        return $this;
    }
}
