<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    use HasFactory;

    /** Default schedule applied to a newly created field. @var list<array{0:string,1:string}> */
    // Khung giờ mở cửa mặc định; khách chọn tự do theo ô 30 phút bên trong. Chủ sân chỉnh giá giờ cao điểm sau.
    public const DEFAULT_SCHEDULE = [
        ['06:00', '16:00'],
        ['16:00', '22:00'],
    ];

    protected $fillable = [
        'sports_field_id',
        'start_time',
        'end_time',
        'price_per_hour',
        'days_of_week',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price_per_hour' => 'decimal:2',
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

    /** Ngày (ISO 1–7) mà khung giờ này áp dụng; rỗng = mọi ngày. */
    public function days(): array
    {
        return $this->days_of_week ? array_map('intval', explode(',', $this->days_of_week)) : [];
    }

    public function appliesTo(string|Carbon $date): bool
    {
        $days = $this->days();

        return $days === [] || in_array(Carbon::parse($date)->dayOfWeekIso, $days, true);
    }

    public function durationInHours(): float
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return $start->floatDiffInHours($end, absolute: true);
    }
}
