<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // Chiều khiếu nại và bên bị khiếu nại (được báo và có quyền phản hồi một lần trước khi admin quyết).
            $table->string('direction', 30)->default('customer_to_owner')->after('user_id'); // customer_to_owner, owner_to_customer
            $table->foreignId('against_user_id')->nullable()->after('direction')->constrained('users')->nullOnDelete();
            $table->text('response')->nullable()->after('content');
            $table->timestamp('responded_at')->nullable()->after('response');
            $table->timestamp('respond_by')->nullable()->after('responded_at');
            // Điểm uy tín trừ khách khi khiếu nại của chủ sân được chấp nhận.
            $table->unsignedSmallInteger('trust_penalty')->nullable()->after('attendance_confirmed');

            $table->index(['against_user_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Ai huỷ đơn (customer, field_owner, admin, system) để thống kê chủ sân huỷ đơn đã thanh toán.
            $table->string('cancelled_by', 20)->nullable()->after('cancel_reason');
        });

        // Khiếu nại cũ đều do khách gửi: bên bị khiếu nại là chủ sân của đơn.
        DB::statement('UPDATE complaints SET against_user_id = (
            SELECT sf.field_owner_id FROM bookings b JOIN sports_fields sf ON sf.id = b.sports_field_id WHERE b.id = complaints.booking_id
        ) WHERE booking_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancelled_by');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['against_user_id']);
            $table->dropIndex(['against_user_id', 'status']);
            $table->dropColumn(['direction', 'against_user_id', 'response', 'responded_at', 'respond_by', 'trust_penalty']);
        });
    }
};
