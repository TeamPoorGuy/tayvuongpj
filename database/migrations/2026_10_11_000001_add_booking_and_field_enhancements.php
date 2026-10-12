<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');
            $table->timestamp('no_show_at')->nullable()->after('checked_in_by');
        });

        Schema::table('sports_fields', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
        });

        Schema::table('time_slots', function (Blueprint $table) {
            $table->decimal('price_per_hour', 12, 2)->nullable()->after('end_time');
        });

        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('business_license');
            $table->string('bank_account_number', 30)->nullable()->after('bank_name');
            $table->string('bank_account_holder')->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder']);
        });

        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropColumn('price_per_hour');
        });

        Schema::table('sports_fields', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'no_show_at']);
        });
    }
};
