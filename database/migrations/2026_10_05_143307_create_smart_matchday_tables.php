<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Thêm trust_score cho users
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('trust_score')->default(100)->after('is_active');
        });

        // 2. Thêm trường check-in cho bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('check_in_code', 32)->nullable()->unique()->after('status');
            $table->timestamp('checked_in_at')->nullable()->after('check_in_code');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
        });

        // 3. Hàng đợi thông minh (Smart Slot Exchange & Waitlist)
        Schema::create('booking_waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sports_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date');
            $table->string('status', 20)->default('waiting'); // waiting, offered, accepted, expired, cancelled
            $table->integer('priority_score')->default(100);
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamps();

            $table->index(['sports_field_id', 'time_slot_id', 'booking_date', 'status'], 'idx_waitlist_slot_search');
            $table->index(['user_id', 'status'], 'idx_waitlist_user_status');
        });

        // 4. Cộng đồng ghép đội / tìm đối thủ (Matchmaking)
        Schema::create('match_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sports_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('play_date');
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->string('skill_level', 20)->default('intermediate'); // beginner, intermediate, advanced, any
            $table->unsignedSmallInteger('players_needed')->default(1);
            $table->unsignedSmallInteger('joined_count')->default(1);
            $table->text('description')->nullable();
            $table->string('contact_phone', 20);
            $table->string('status', 20)->default('open'); // open, full, cancelled, completed
            $table->timestamps();

            $table->index(['sport_category_id', 'status', 'play_date'], 'idx_matches_category_status');
        });

        // 5. Thành viên đăng ký tham gia ghép đội
        Schema::create('match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending, accepted, rejected
            $table->string('message', 255)->nullable();
            $table->timestamps();

            $table->unique(['match_request_id', 'user_id'], 'uniq_match_participant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_participants');
        Schema::dropIfExists('match_requests');
        Schema::dropIfExists('booking_waitlists');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['checked_in_by']);
            $table->dropColumn(['check_in_code', 'checked_in_at', 'checked_in_by']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('trust_score');
        });
    }
};
