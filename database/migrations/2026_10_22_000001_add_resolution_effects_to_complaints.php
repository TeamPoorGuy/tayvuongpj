<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // Kết quả thực tế khi admin chấp nhận khiếu nại: số tiền hoàn thêm cho khách và việc gỡ ghi nhận vắng mặt.
            $table->decimal('refund_amount', 12, 2)->nullable()->after('resolution');
            $table->boolean('attendance_confirmed')->default(false)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'attendance_confirmed']);
        });
    }
};
