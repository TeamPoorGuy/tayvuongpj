<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(8.00)->after('paid_at');
            $table->decimal('commission_amount', 12, 2)->nullable()->after('commission_rate');
            $table->foreignId('statement_id')->nullable()->after('commission_amount')->constrained('owner_statements')->nullOnDelete();

            $table->index('statement_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['statement_id']);
            $table->dropColumn(['commission_rate', 'commission_amount', 'statement_id']);
        });
    }
};
