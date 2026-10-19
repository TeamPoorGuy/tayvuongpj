<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Thanh toán chuyển khoản: null = chưa duyệt, awaiting = chờ khách chuyển,
            // reported = khách báo đã chuyển, paid = chủ sân xác nhận đã nhận tiền.
            $table->string('payment_status', 20)->nullable()->index()->after('confirmed_at');
            $table->string('payment_option', 10)->nullable()->after('payment_status'); // deposit | full
            $table->decimal('payment_amount', 12, 2)->nullable()->after('payment_option');
            $table->string('payment_code', 30)->nullable()->unique()->after('payment_amount'); // nội dung chuyển khoản
            $table->timestamp('payment_due_at')->nullable()->after('payment_code');
            $table->timestamp('payment_reported_at')->nullable()->after('payment_due_at');
            $table->timestamp('paid_at')->nullable()->after('payment_reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['payment_code']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'payment_option', 'payment_amount', 'payment_code', 'payment_due_at', 'payment_reported_at', 'paid_at']);
        });
    }
};
