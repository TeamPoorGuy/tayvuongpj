<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('user_id');
            $table->string('owner_id_number')->nullable()->after('owner_name');
            $table->text('rejection_reason')->nullable()->after('verification_documents');
            $table->timestamp('verified_at')->nullable()->after('rejection_reason');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->string('business_name')->nullable()->change();
            $table->string('business_address')->nullable()->change();
            $table->string('business_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['owner_name', 'owner_id_number', 'rejection_reason', 'verified_at']);
            $table->string('business_name')->nullable(false)->change();
            $table->string('business_address')->nullable(false)->change();
            $table->string('business_phone')->nullable(false)->change();
        });
    }
};
