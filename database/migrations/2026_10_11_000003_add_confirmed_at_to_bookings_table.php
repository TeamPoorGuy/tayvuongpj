<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Thời điểm chủ sân duyệt đơn: khách chỉ được huỷ trong một khoảng ngắn sau đó.
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });

        DB::table('bookings')->whereIn('status', ['confirmed', 'completed'])->update(['confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
