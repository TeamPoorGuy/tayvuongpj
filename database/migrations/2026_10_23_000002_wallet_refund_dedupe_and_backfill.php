<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Mã giao dịch ngân hàng của khoản tiền vào ví, chống cộng trùng khi ngân hàng gửi webhook lặp.
            $table->string('bank_ref', 100)->nullable()->unique()->after('withdrawal_id');
        });

        // Các khoản hoàn đang chờ admin chuyển tay nay vào thẳng ví của khách.
        DB::table('bookings')->where('refund_status', 'pending')->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $amount = (float) ($booking->refund_amount ?? $booking->payment_amount ?? 0);
                $user = DB::table('users')->where('id', $booking->user_id)->first();
                if ($amount <= 0 || ! $user) {
                    continue;
                }

                $after = (float) $user->wallet_balance + $amount;
                DB::table('users')->where('id', $user->id)->update(['wallet_balance' => $after]);
                DB::table('wallet_transactions')->insert([
                    'user_id' => $user->id,
                    'type' => 'refund',
                    'amount' => $amount,
                    'balance_after' => $after,
                    'booking_id' => $booking->id,
                    'description' => "Hoàn tiền đơn #BK-{$booking->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('bookings')->where('id', $booking->id)->update(['refund_status' => 'done', 'refund_amount' => $amount, 'refunded_at' => now()]);
            }
        });

        DB::table('payments')->where('status', 'refund_required')->update(['status' => 'refunded']);
    }

    public function down(): void
    {
        // Dữ liệu hoàn tiền đã vào ví không đảo ngược được; chỉ bỏ cột.
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique(['bank_ref']);
            $table->dropColumn('bank_ref');
        });
    }
};
