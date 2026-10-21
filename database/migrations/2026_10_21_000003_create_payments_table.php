<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Một lần thanh toán của khách vào tài khoản nền tảng, có thể gồm nhiều đơn (nhiều ô, nhiều sân, nhiều tuần).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique(); // nội dung chuyển khoản, vd SH12AB34
            $table->string('option', 10); // deposit | full
            $table->decimal('total', 12, 2); // tổng tiền các đơn
            $table->decimal('amount', 12, 2); // số tiền phải chuyển (cọc hoặc toàn bộ)
            $table->string('status', 20)->default('awaiting'); // awaiting | paid | expired | cancelled | refund_required
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('source', 10)->nullable(); // webhook | admin
            $table->string('bank_ref', 100)->nullable()->unique(); // mã giao dịch ngân hàng, chống ghi nhận 2 lần
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::dropIfExists('payments');
    }
};
