<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thu hộ: đối soát nay là SportHub chi trả cho chủ sân (tiền đã thu − hoàn trả − hoa hồng).
        Schema::table('owner_statements', function (Blueprint $table) {
            $table->decimal('collected_amount', 12, 2)->default(0)->after('gmv');
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('collected_amount');
            $table->decimal('payout_amount', 12, 2)->default(0)->after('commission_amount');
            $table->string('payout_ref', 100)->nullable()->after('transfer_code');
        });

        // Kỳ cũ (chủ sân nộp phí cho nền tảng) đã báo chuyển hoặc được miễn thì coi như đã xong.
        DB::table('owner_statements')->whereIn('status', ['reported', 'waived'])->update(['status' => 'paid']);
    }

    public function down(): void
    {
        Schema::table('owner_statements', function (Blueprint $table) {
            $table->dropColumn(['collected_amount', 'refunded_amount', 'payout_amount', 'payout_ref']);
        });
    }
};
