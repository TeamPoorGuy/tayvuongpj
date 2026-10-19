<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->string('bank_bin', 20)->nullable()->after('bank_name');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_proof_path', 500)->nullable()->after('payment_reported_at');
        });
    }

    public function down(): void
    {
        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->dropColumn('bank_bin');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('payment_proof_path');
        });
    }
};
