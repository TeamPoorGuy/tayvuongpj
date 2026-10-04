<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['sports_field_id', 'booking_date', 'status'], 'bookings_field_date_status_idx');
            $table->index(['user_id', 'created_at'], 'bookings_user_created_idx');
        });

        Schema::table('sports_fields', function (Blueprint $table) {
            $table->index(['status', 'is_active'], 'fields_status_active_idx');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->softDeletes();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['sports_field_id', 'is_visible'], 'reviews_field_visible_idx');
        });

        Schema::table('field_types', function (Blueprint $table) {
            $table->unique(['sport_category_id', 'slug']);
        });

        Schema::table('time_slots', function (Blueprint $table) {
            // Giá/giờ riêng cho khung giờ (giờ cao điểm); null = dùng giá của sân.
            $table->decimal('price_per_hour', 12, 2)->nullable()->after('end_time');
        });

        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->text('owner_id_number')->nullable()->change(); // chứa dữ liệu đã mã hoá
            $table->string('verification_status', 20)->default('pending')->change(); // thêm 'suspended'
            $table->string('owner_id_hash', 64)->nullable()->unique()->after('owner_id_number');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_ip', 45)->nullable();
        });

        Schema::create('owner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_owner_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // id_front | id_back | business_license | land_right
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
            $table->unique(['field_owner_profile_id', 'type']);
        });

        // Dữ liệu cũ: mã hoá CCCD, tạo hash, chuyển file giấy phép sang bảng owner_documents.
        foreach (DB::table('field_owner_profiles')->get() as $row) {
            $update = [];
            if ($row->owner_id_number) {
                $update['owner_id_number'] = Crypt::encryptString($row->owner_id_number);
                $update['owner_id_hash'] = hash_hmac('sha256', $row->owner_id_number, config('app.key'));
            }
            if ($row->verification_documents) {
                DB::table('owner_documents')->insert([
                    'field_owner_profile_id' => $row->id,
                    'type' => 'business_license',
                    'path' => $row->verification_documents,
                    'original_name' => basename($row->verification_documents),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($update) {
                DB::table('field_owner_profiles')->where('id', $row->id)->update($update);
            }
        }

        Schema::table('field_owner_profiles', function (Blueprint $table) {
            $table->dropColumn('verification_documents');
        });
    }

    public function down(): void {}
};
