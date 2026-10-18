<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Hoàn tiền: null = không thuộc diện hoàn, pending = chờ xử lý hoàn tiền, done = đã hoàn, waived = miễn/bỏ qua hoàn.
            $table->string('refund_status', 20)->nullable()->index()->after('paid_at');
            $table->decimal('refund_amount', 12, 2)->nullable()->after('refund_status');
            $table->timestamp('refunded_at')->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_status', 'refund_amount', 'refunded_at']);
        });
    }
};
