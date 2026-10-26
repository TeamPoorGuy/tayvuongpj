<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'booking_waitlists'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->time('start_time')->nullable()->after('booking_date');
                $table->time('end_time')->nullable()->after('start_time');
            });

            // Đơn cũ chỉ có time_slot_id: chép giờ từ khung giờ để so sánh theo khoảng thời gian.
            DB::statement("UPDATE {$tableName} SET
                start_time = (SELECT start_time FROM time_slots WHERE time_slots.id = {$tableName}.time_slot_id),
                end_time = (SELECT end_time FROM time_slots WHERE time_slots.id = {$tableName}.time_slot_id)");

            Schema::table($tableName, function (Blueprint $table) {
                // Đơn theo khoảng giờ tự do (lưới 30 phút) không gắn với khung giờ nào.
                $table->unsignedBigInteger('time_slot_id')->nullable()->change();
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['sports_field_id', 'booking_date', 'start_time'], 'idx_bookings_field_date_start');
        });

        Schema::table('time_slots', function (Blueprint $table) {
            // "1,2,3,4,5" theo ISO (1 = thứ 2 … 7 = chủ nhật); null = áp dụng mọi ngày.
            $table->string('days_of_week', 20)->nullable()->after('price_per_hour');
        });

        Schema::table('field_closures', function (Blueprint $table) {
            // Cả hai null = đóng cả ngày; có giờ = chỉ khoá khoảng giờ đó mỗi ngày trong khoảng ngày.
            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('field_closures', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropColumn('days_of_week');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_field_date_start');
        });

        foreach (['booking_waitlists', 'bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['start_time', 'end_time']);
            });
        }
    }
};
