<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\FieldStatus;
use App\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SportsField extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'field_owner_id',
        'field_type_id',
        'name',
        'slug',
        'address',
        'city',
        'district',
        'search_text',
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

    protected static function booted(): void
    {
        static::saving(function (self $field) {
            // Chủ sân không nhập quận/thành phố thì suy ra từ 2 đoạn cuối của địa chỉ.
            if ($field->isDirty('address') && ! $field->city && ! $field->district) {
                $parts = array_values(array_filter(array_map('trim', explode(',', (string) $field->address))));
                $field->city = count($parts) >= 2 ? end($parts) : null;
                $field->district = count($parts) >= 3 ? $parts[count($parts) - 2] : null;
            }

            $field->search_text = static::normalizeForSearch(implode(' ', array_filter([
                $field->name, $field->address, $field->district, $field->city,
            ])));
        });
    }

    /** Bỏ dấu, viết thường: "Đống Đa" → "dong da". */
    public static function normalizeForSearch(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

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

    /** Sân đóng cả ngày (lịch đóng có khoảng giờ chỉ khoá một phần, xem closureBlocking). */
    public function isClosedOn(string|\Carbon\CarbonInterface $date): bool
    {
        return $this->closureOn($date) !== null;
    }

    public function closureOn(string|\Carbon\CarbonInterface $date): ?FieldClosure
    {
        $d = is_string($date) ? $date : $date->toDateString();

        return $this->closures()
            ->whereNull('start_time')
            ->where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->first();
    }

    /** Lịch đóng (cả ngày hoặc theo giờ) đang chặn khoảng giờ [start, end) của ngày đó. */
    public function closureBlocking(string $date, string $start, string $end): ?FieldClosure
    {
        return $this->closures()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where(fn ($q) => $q->whereNull('start_time')
                ->orWhere(fn ($q) => $q->where('start_time', '<', Booking::normalizeTime($end))->where('end_time', '>', Booking::normalizeTime($start))))
            ->first();
    }

    /**
     * Các sân cùng chủ, cùng địa chỉ và cùng môn (cụm sân: Sân 1, Sân 2…) để hiện chung một lưới đặt; sân đang xem đứng đầu.
     * ponytail: gom theo địa chỉ gõ trùng (không phân biệt hoa thường, khoảng trắng hai đầu); gõ lệch thì tách hàng — thêm bảng cụm sân nếu cần.
     *
     * @return Collection<int, SportsField>
     */
    public function siblings(): Collection
    {
        return static::approved()
            ->where('field_owner_id', $this->field_owner_id)
            ->whereHas('fieldType', fn ($q) => $q->where('sport_category_id', $this->fieldType?->sport_category_id))
            ->orderBy('name')
            ->get()
            // So địa chỉ trong PHP: LOWER của SQLite không hạ chữ có dấu (Đ, Ư…).
            ->filter(fn (self $field) => mb_strtolower(trim($field->address)) === mb_strtolower(trim($this->address)))
            ->sortBy(fn (self $field) => $field->id === $this->id ? 0 : 1)
            ->values();
    }

    /** @return Collection<int, TimeSlot> khung giờ đang hoạt động và áp dụng cho ngày đó */
    public function slotsFor(string $date): Collection
    {
        return $this->timeSlots()->where('is_active', true)->orderBy('start_time')->get()
            ->filter(fn (TimeSlot $slot) => $slot->appliesTo($date))->values();
    }

    /**
     * Khoảng giờ đã bị chiếm trong ngày: đơn đang giữ chỗ, lời mời waitlist còn hạn, lịch khoá sân.
     *
     * @return array{booked: list<array{0:string,1:string}>, held: list<array{0:string,1:string}>, locked: list<array{0:string,1:string}>}
     */
    public function occupancyOn(string $date): array
    {
        $pair = fn ($row) => [$row->start_time, $row->end_time];

        return [
            'booked' => Booking::where('sports_field_id', $this->id)->whereDate('booking_date', $date)
                ->whereIn('status', BookingStatus::blockingCases())->get(['start_time', 'end_time'])->map($pair)->all(),
            'held' => BookingWaitlist::where('sports_field_id', $this->id)->whereDate('booking_date', $date)
                ->where('status', 'offered')->where('offer_expires_at', '>', now())
                ->get(['start_time', 'end_time'])->map($pair)->all(),
            'locked' => $this->closures()->where('start_date', '<=', $date)->where('end_date', '>=', $date)->get()
                ->map(fn (FieldClosure $c) => $c->isWholeDay() ? ['00:00:00', '24:00:00'] : [$c->start_time, $c->end_time])->all(),
        ];
    }

    /** @param list<array{0:string,1:string}> $ranges */
    public static function overlapsAny(array $ranges, string $start, string $end): bool
    {
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($rangeStart < $end && $rangeEnd > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tiền thuê khoảng [start, end): cộng từng bước 30 phút theo khung giờ áp dụng cho ngày đó.
     *
     * @throws DomainException nếu có bước nằm ngoài giờ mở cửa
     */
    public function priceFor(string $date, string $start, string $end): float
    {
        $slots = $this->slotsFor($date);
        $from = static::minutesOf($start);
        $to = static::minutesOf($end);
        $total = 0.0;

        for ($m = $from; $m < $to; $m += 30) {
            $slot = $slots->first(fn (TimeSlot $s) => static::minutesOf($s->start_time) <= $m && static::minutesOf($s->end_time) >= $m + 30);
            if (! $slot) {
                throw new DomainException('Khung giờ nằm ngoài giờ mở cửa của sân.');
            }
            $total += (float) ($slot->price_per_hour ?? $this->price_per_hour) / 2;
        }

        return $total;
    }

    /**
     * Lưới ô 30 phút của ngày: free | booked | held | locked | past (locked gồm cả giờ ngoài khung mở cửa).
     *
     * @return list<array{start:string,end:string,price_per_hour:?float,state:string}>
     */
    public function cellsFor(string $date): array
    {
        $slots = $this->slotsFor($date);
        if ($slots->isEmpty()) {
            return [];
        }

        $occupancy = $this->occupancyOn($date);
        $open = static::minutesOf($slots->min('start_time'));
        $close = static::minutesOf($slots->max('end_time'));
        $cells = [];

        for ($m = $open; $m < $close; $m += 30) {
            $start = static::timeOf($m);
            $end = static::timeOf($m + 30);
            $slot = $slots->first(fn (TimeSlot $s) => static::minutesOf($s->start_time) <= $m && static::minutesOf($s->end_time) >= $m + 30);

            $state = match (true) {
                ! $slot || static::overlapsAny($occupancy['locked'], $start, $end) => 'locked',
                static::overlapsAny($occupancy['booked'], $start, $end) => 'booked',
                static::overlapsAny($occupancy['held'], $start, $end) => 'held',
                Carbon::parse("$date $start")->isPast() => 'past',
                default => 'free',
            };

            $cells[] = [
                'start' => substr($start, 0, 5),
                'end' => substr($end, 0, 5),
                'price_per_hour' => $slot ? (float) ($slot->price_per_hour ?? $this->price_per_hour) : null,
                'state' => $state,
            ];
        }

        return $cells;
    }

    private static function minutesOf(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private static function timeOf(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
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
