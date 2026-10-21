<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\SportsField;
use Carbon\Carbon;

class SmartSuggestionService
{
    private const MAX_SUGGESTIONS = 5;

    /**
     * Gợi ý khoảng giờ/sân thay thế khi khoảng giờ khách muốn đã kín (cùng thời lượng).
     *
     * @return list<array<string, mixed>>
     */
    public function getSuggestions(SportsField $field, string $start, string $end, string $dateString): array
    {
        $date = Carbon::parse($dateString)->startOfDay();
        $tomorrow = $date->copy()->addDay();
        $start = substr($start, 0, 5);
        $end = substr($end, 0, 5);
        $length = $this->minutes($end) - $this->minutes($start);
        $targetPrice = $this->pricePerHour($field, $date, $start, $end);
        $suggestions = [];

        // 1. Cùng sân, cùng ngày: khoảng cùng thời lượng còn trống, gần giờ muốn nhất.
        $sameDay = collect($this->freeRanges($field, $date, $length))
            ->reject(fn ($range) => $range[0] === $start)
            ->sortBy(fn ($range) => abs($this->minutes($range[0]) - $this->minutes($start)))
            ->take(2);

        foreach ($sameDay as [$from, $to]) {
            $price = $this->pricePerHour($field, $date, $from, $to);
            $badge = $targetPrice && $price < $targetPrice
                ? 'Tiết kiệm '.round((($targetPrice - $price) / $targetPrice) * 100).'% chi phí'
                : 'Cùng sân, cùng ngày';
            $suggestions[] = $this->item('same_field_today', $field, $from, $to, $date, $price, $badge);
        }

        // 2. Cùng sân, cùng giờ, ngày hôm sau (tính từ ngày đang xem).
        if ($this->isFree($field, $tomorrow, $start, $end)) {
            $suggestions[] = $this->item('same_slot_tomorrow', $field, $start, $end, $tomorrow, $this->pricePerHour($field, $tomorrow, $start, $end), 'Cùng khung giờ ngày hôm sau');
        }

        // 3. Sân khác cùng loại, cùng khoảng giờ, gần nhất trước.
        $candidates = SportsField::approved()
            ->where('field_type_id', $field->field_type_id)
            ->where('id', '!=', $field->id)
            ->limit(20)
            ->get()
            ->filter(fn (SportsField $other) => $this->isFree($other, $date, $start, $end))
            ->map(fn (SportsField $other) => [$other, $this->distanceKm($field, $other)])
            ->sortBy(fn ($pair) => $pair[1] ?? INF)
            ->take(2);

        foreach ($candidates as [$other, $km]) {
            $item = $this->item('other_field_today', $other, $start, $end, $date, $this->pricePerHour($other, $date, $start, $end),
                $km !== null ? 'Sân khác cùng bộ môn · cách '.number_format($km, 1).' km' : 'Sân khác cùng bộ môn');
            $item['distance_km'] = $km !== null ? round($km, 1) : null;
            $suggestions[] = $item;
        }

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS);
    }

    /** Các khoảng [bắt đầu, kết thúc] dài $length phút gồm toàn ô trống liền nhau. */
    private function freeRanges(SportsField $field, Carbon $date, int $length): array
    {
        $cells = $field->cellsFor($date->toDateString());
        $need = max(1, intdiv($length, 30));
        $ranges = [];

        for ($i = 0; $i + $need <= count($cells); $i++) {
            $run = array_slice($cells, $i, $need);
            if (collect($run)->every(fn ($cell) => $cell['state'] === 'free')) {
                $ranges[] = [$run[0]['start'], end($run)['end']];
            }
        }

        return $ranges;
    }

    private function isFree(SportsField $field, Carbon $date, string $start, string $end): bool
    {
        $cells = collect($field->cellsFor($date->toDateString()))
            ->filter(fn ($cell) => $cell['start'] >= $start && $cell['end'] <= $end);

        return $cells->count() === intdiv($this->minutes($end) - $this->minutes($start), 30)
            && $cells->every(fn ($cell) => $cell['state'] === 'free');
    }

    /** Giá trung bình mỗi giờ của khoảng (khung giá có thể khác nhau trong khoảng). */
    private function pricePerHour(SportsField $field, Carbon $date, string $start, string $end): ?float
    {
        try {
            return $field->priceFor($date->toDateString(), $start, $end) / (($this->minutes($end) - $this->minutes($start)) / 60);
        } catch (DomainException) {
            return null;
        }
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
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

    private function item(string $type, SportsField $field, string $start, string $end, Carbon $date, ?float $price, string $badge): array
    {
        return [
            'type' => $type,
            'field_id' => $field->id,
            'field_name' => $field->name,
            'field_slug' => $field->slug,
            'start_time' => substr($start, 0, 5),
            'end_time' => substr($end, 0, 5),
            'date' => $date->toDateString(),
            'date_label' => $this->dateLabel($date),
            'price_per_hour' => $price !== null ? round($price) : null,
            'badge' => $badge,
        ];
    }
}
