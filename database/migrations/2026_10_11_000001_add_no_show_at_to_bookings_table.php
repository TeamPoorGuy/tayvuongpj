<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Đơn đã xác nhận nhưng khách không đến (không check-in) khi hết giờ.
            $table->timestamp('no_show_at')->nullable()->after('checked_in_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('no_show_at');
        });
    }
};
