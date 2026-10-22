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
        'payment_id',
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
        'cancelled_by',
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

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
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
        // Khiếu nại được chấp nhận có thể hoàn tiền cho đơn đã hoàn tất: hoa hồng chỉ tính trên phần còn lại.
        if ($this->status === BookingStatus::Completed && in_array($this->refund_status, ['pending', 'done'], true)) {
            return max(0.0, $this->baseBeforeRefund() - (float) ($this->refund_amount ?? $this->payment_amount));
        }

        return $this->baseBeforeRefund();
    }

    private function baseBeforeRefund(): float
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

        // Đơn huỷ đã thanh toán: phần nền tảng giữ lại sau hoàn tiền (huỷ muộn hoàn một phần hoặc không hoàn).
        if ($this->status === BookingStatus::Cancelled && $this->payment_status === 'paid') {
            // Không ghi số tiền hoàn = hoàn toàn bộ.
            $refunded = in_array($this->refund_status, ['pending', 'done'], true) ? (float) ($this->refund_amount ?? $this->payment_amount) : 0.0;

            return max(0.0, (float) $this->payment_amount - $refunded);
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
            'payment',
            'review',
            'complaints.attachments',
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

    /** Hạn chót khách huỷ đơn đã thanh toán mà vẫn được hoàn 100%; null nếu đã qua hoặc không áp dụng. */
    public function cancelUntil(): ?Carbon
    {
        if ($this->status !== BookingStatus::Confirmed) {
            return null;
        }

        $deadline = $this->startsAt()->subHours(Settings::getInt('refund_full_hours', 24));

        return $deadline->isFuture() ? $deadline : null;
    }

    /**
     * Số tiền hoàn nếu khách huỷ lúc này (bậc theo số giờ còn lại trước giờ chơi):
     * ≥ refund_full_hours → 100%; ≥ refund_partial_hours → refund_partial_percent%; ít hơn → 0.
     */
    public function refundAmountIfCancelledNow(): float
    {
        $paid = (float) ($this->payment_amount ?? 0);
        $hoursLeft = now()->diffInMinutes($this->startsAt(), false) / 60;

        return match (true) {
            $hoursLeft >= Settings::getInt('refund_full_hours', 24) => $paid,
            $hoursLeft >= Settings::getInt('refund_partial_hours', 6) => round($paid * Settings::getInt('refund_partial_percent', 50) / 100),
            default => 0.0,
        };
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
            // Đơn kiểu cũ: khách đã báo chuyển khoản thì phải chờ đối chiếu, không tự huỷ khi tiền đang treo.
            BookingStatus::Pending => $this->payment_status !== 'reported',
            BookingStatus::Confirmed => $this->startsAt()->isFuture(),
            default => false,
        };
    }

    /** Huỷ không bị trừ điểm uy tín: đơn chưa thanh toán, hoặc còn từ free_cancel_hours trở lên trước giờ chơi. */
    public function isFreeCancel(): bool
    {
        if ($this->status !== BookingStatus::Confirmed) {
            return true;
        }

        return $this->startsAt()->gte(now()->addHours(Settings::getInt('free_cancel_hours', Settings::getInt('late_cancel_hours', 4))));
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
        $refundAmount = null;

        // Tiền đang do nền tảng giữ: khách tự huỷ hoàn theo bậc giờ; chủ sân/admin/hệ thống huỷ hoàn đủ.
        if (in_array($this->payment_status, ['reported', 'paid'], true)) {
            $refundAmount = $by === 'customer' ? $this->refundAmountIfCancelledNow() : (float) $this->payment_amount;
        }

        $this->update([
            'status' => $targetStatus->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'cancelled_by' => $by,
            'refund_status' => $refundAmount > 0 ? 'pending' : null,
            'refund_amount' => $refundAmount > 0 ? $refundAmount : null,
        ]);

        return $this;
    }
}
