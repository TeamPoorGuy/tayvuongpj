<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phòng tìm đối cũ cho nhập giờ tự do (vd 18:00–19:30) trong khi sân chỉ có khung 18:30–20:00.
     * Đưa giờ của các phòng còn mở về khung giờ thật của sân trùng nhiều phút nhất.
     */
    public function up(): void
    {
        $minutes = fn (string $time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);

        $matches = DB::table('match_requests')
            ->whereNotNull('sports_field_id')
            ->whereIn('status', ['open', 'full'])
            ->get();

        foreach ($matches as $match) {
            $slots = DB::table('time_slots')
                ->where('sports_field_id', $match->sports_field_id)
                ->where('is_active', true)
                ->get();

            $start = $minutes($match->start_time);
            $end = $minutes($match->end_time);

            $best = $slots->sortByDesc(fn ($slot) => min($end, $minutes($slot->end_time)) - max($start, $minutes($slot->start_time)))->first();

            if ($best) {
                DB::table('match_requests')->where('id', $match->id)->update([
                    'start_time' => substr($best->start_time, 0, 5),
                    'end_time' => substr($best->end_time, 0, 5),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Không khôi phục được giờ nhập tay cũ.
    }
};
