<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cho model có khoảng giờ start_time/end_time (đơn đặt, hàng đợi).
 * Dữ liệu cũ chỉ có time_slot_id nên khi tạo mới sẽ tự chép giờ từ khung giờ.
 */
trait HasTimeRange
{
    public static function bootHasTimeRange(): void
    {
        static::creating(function ($model) {
            if ($model->start_time === null && $model->time_slot_id) {
                $slot = \App\Models\TimeSlot::find($model->time_slot_id);
                $model->start_time = $slot?->start_time;
                $model->end_time = $slot?->end_time;
            }

            // Chuẩn hoá "H:i" → "H:i:s" để so sánh chuỗi giờ luôn đúng.
            foreach (['start_time', 'end_time'] as $column) {
                if ($model->{$column} !== null) {
                    $model->{$column} = static::normalizeTime($model->{$column});
                }
            }
        });
    }

    public static function normalizeTime(string $time): string
    {
        return $time === '24:00' ? '24:00:00' : Carbon::parse($time)->format('H:i:s');
    }

    /** Các bản ghi có khoảng giờ giao với [start, end) — chạm đầu mút không tính là giao. */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('start_time', '<', static::normalizeTime($end))
            ->where('end_time', '>', static::normalizeTime($start));
    }
}
