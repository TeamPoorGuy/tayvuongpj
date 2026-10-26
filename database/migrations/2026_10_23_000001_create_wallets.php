<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Số dư ví SportHub; mọi thay đổi đi qua WalletService và có dòng ledger tương ứng.
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('trust_score');
            // Tài khoản nhận tiền rút của khách (chủ sân dùng tài khoản trong hồ sơ cơ sở).
            $table->string('bank_bin', 10)->nullable()->after('wallet_balance');
            $table->string('bank_account_number', 30)->nullable()->after('bank_bin');
            $table->string('bank_account_holder', 50)->nullable()->after('bank_account_number');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('bank_bin', 10);
            $table->string('bank_name', 100);
            $table->string('account_number', 30);
            $table->string('account_holder', 50);
            $table->string('status', 20)->default('pending'); // pending, paid, rejected
            $table->boolean('auto')->default(false);
            $table->string('reference', 100)->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // refund, topup, booking_payment, booking_payment_return, withdrawal, withdrawal_return, owner_earning, owner_adjustment
            $table->string('type', 30);
            $table->decimal('amount', 12, 2); // dương = cộng, âm = trừ
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 255);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['booking_id', 'type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('purpose', 20)->default('booking')->after('user_id'); // booking, topup
            $table->decimal('wallet_amount', 12, 2)->default(0)->after('amount'); // phần đã trừ từ ví
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Phần chủ sân được hưởng đã cộng vào ví (sau hoàn và hoa hồng) và thời điểm cộng.
            $table->decimal('earning_amount', 12, 2)->nullable()->after('commission_amount');
            $table->timestamp('earning_released_at')->nullable()->after('earning_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['earning_amount', 'earning_released_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'wallet_amount']);
        });
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('withdrawals');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance', 'bank_bin', 'bank_account_number', 'bank_account_holder']);
        });
    }
};
