<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bảng ngày nghỉ / đóng sân
        Schema::create('field_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sports_field_id')->constrained('sports_fields')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 255);
            $table->timestamps();

            $table->index(['sports_field_id', 'start_date', 'end_date']);
        });

        // 2. Thêm cột phản hồi của chủ sân vào bảng reviews
        Schema::table('reviews', function (Blueprint $table) {
            $table->text('owner_reply')->nullable()->after('comment');
            $table->timestamp('owner_replied_at')->nullable()->after('owner_reply');
        });

        // 3. Thêm cột nhóm đặt cố định vào bảng bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('recurring_group_id')->nullable()->index()->after('statement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_closures');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['owner_reply', 'owner_replied_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('recurring_group_id');
        });
    }
};
