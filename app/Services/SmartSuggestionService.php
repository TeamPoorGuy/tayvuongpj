<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Carbon\Carbon;

class SmartSuggestionService
{
    private const MAX_SUGGESTIONS = 5;

    /**
     * Gợi ý khung giờ/sân thay thế khi một slot đã kín.
     *
     * @return list<array<string, mixed>>
     */
    public function getSuggestions(SportsField $field, TimeSlot $targetSlot, string $dateString): array
    {
        $date = Carbon::parse($dateString)->startOfDay();
        $tomorrow = $date->copy()->addDay();
        $suggestions = [];

        // 1. Cùng sân, cùng ngày, khung giờ khác còn trống.
        $busyToday = $this->busySlots([$field->id], $date);
        $targetPrice = $targetSlot->price_per_hour ?? $field->price_per_hour;

        $sameDay = TimeSlot::where('sports_field_id', $field->id)
            ->where('id', '!=', $targetSlot->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get()
            ->filter(fn (TimeSlot $s) => ! $this->isPast($date, $s) && ! isset($busyToday[$field->id.':'.$s->id]))
            ->take(2);

        foreach ($sameDay as $slot) {
            $price = $slot->price_per_hour ?? $field->price_per_hour;
            $badge = $price < $targetPrice
                ? 'Tiết kiệm '.round((($targetPrice - $price) / $targetPrice) * 100).'% chi phí'
                : 'Cùng sân, cùng ngày';
            $suggestions[] = $this->item('same_field_today', $field, $slot, $date, $price, $badge);
        }

        // 2. Cùng sân, cùng khung giờ, ngày hôm sau (tính từ ngày đang xem).
        $busyTomorrow = $this->busySlots([$field->id], $tomorrow);
        if (! isset($busyTomorrow[$field->id.':'.$targetSlot->id])) {
            $suggestions[] = $this->item('same_slot_tomorrow', $field, $targetSlot, $tomorrow, $targetPrice, 'Cùng khung giờ ngày hôm sau');
        }

        // 3. Sân khác cùng loại, cùng giờ bắt đầu, gần nhất trước.
        $others = SportsField::approved()
            ->where('field_type_id', $field->field_type_id)
            ->where('id', '!=', $field->id)
            ->with('timeSlots')
            ->limit(20)
            ->get();

        $busyOthers = $this->busySlots($others->pluck('id')->all(), $date);
        $start = substr($targetSlot->start_time, 0, 5);

        $candidates = [];
        foreach ($others as $other) {
            $slot = $other->timeSlots->where('is_active', true)
                ->first(fn ($s) => substr($s->start_time, 0, 5) === $start);
            if (! $slot || $this->isPast($date, $slot) || isset($busyOthers[$other->id.':'.$slot->id])) {
                continue;
            }
            $candidates[] = [$other, $slot, $this->distanceKm($field, $other)];
        }
        usort($candidates, fn ($a, $b) => ($a[2] ?? INF) <=> ($b[2] ?? INF));

        foreach (array_slice($candidates, 0, 2) as [$other, $slot, $km]) {
            $item = $this->item('other_field_today', $other, $slot, $date, $slot->price_per_hour ?? $other->price_per_hour,
                $km !== null ? 'Sân khác cùng bộ môn · cách '.number_format($km, 1).' km' : 'Sân khác cùng bộ môn');
            $item['distance_km'] = $km !== null ? round($km, 1) : null;
            $suggestions[] = $item;
        }

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /** Các cặp "sân:slot" đã có đơn hoặc đang được giữ chỗ trong ngày (2 truy vấn cho cả danh sách sân). */
    private function busySlots(array $fieldIds, Carbon $date): array
    {
        if ($fieldIds === []) {
            return [];
        }

        $busy = [];
        Booking::whereIn('sports_field_id', $fieldIds)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['sports_field_id', 'time_slot_id'])
            ->each(function ($b) use (&$busy) {
                $busy[$b->sports_field_id.':'.$b->time_slot_id] = true;
            });

        BookingWaitlist::whereIn('sports_field_id', $fieldIds)
            ->whereDate('booking_date', $date)
            ->where('status', 'offered')
            ->where('offer_expires_at', '>', now())
            ->get(['sports_field_id', 'time_slot_id'])
            ->each(function ($w) use (&$busy) {
                $busy[$w->sports_field_id.':'.$w->time_slot_id] = true;
            });

        return $busy;
    }

    private function isPast(Carbon $date, TimeSlot $slot): bool
    {
        return Carbon::parse($date->toDateString().' '.$slot->start_time)->isPast();
    }

    private function distanceKm(SportsField $a, SportsField $b): ?float
    {
        if ($a->latitude === null || $a->longitude === null || $b->latitude === null || $b->longitude === null) {
            return null;
        }

        $lat1 = deg2rad((float) $a->latitude);
        $lat2 = deg2rad((float) $b->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b->longitude - (float) $a->longitude);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    private function dateLabel(Carbon $date): string
    {
        $short = $date->format('d/m');
        if ($date->isToday()) {
            return "Hôm nay ($short)";
        }
        if ($date->isTomorrow()) {
            return "Ngày mai ($short)";
        }
        $weekday = $date->dayOfWeekIso === 7 ? 'Chủ nhật' : 'Thứ '.($date->dayOfWeekIso + 1);

        return "$weekday ($short)";
    }

    private function item(string $type, SportsField $field, TimeSlot $slot, Carbon $date, mixed $price, string $badge): array
    {
        return [
            'type' => $type,
            'field_id' => $field->id,
            'field_name' => $field->name,
            'field_slug' => $field->slug,
            'time_slot_id' => $slot->id,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'date' => $date->toDateString(),
            'date_label' => $this->dateLabel($date),
            'price_per_hour' => (float) $price,
            'badge' => $badge,
        ];
    }
}
