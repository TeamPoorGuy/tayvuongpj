<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sports_fields', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('address')->index();
            $table->string('district', 100)->nullable()->after('city')->index();
            // Tên + địa chỉ + quận + thành phố đã bỏ dấu, viết thường — để tìm "dong da" ra "Đống Đa".
            $table->text('search_text')->nullable()->after('district');
        });

        // Điền cho sân hiện có: "số nhà đường, quận, thành phố" → 2 đoạn cuối là quận và thành phố.
        DB::table('sports_fields')->orderBy('id')->each(function ($field) {
            $parts = array_values(array_filter(array_map('trim', explode(',', (string) $field->address))));
            $city = count($parts) >= 2 ? end($parts) : null;
            $district = count($parts) >= 3 ? $parts[count($parts) - 2] : null;

            DB::table('sports_fields')->where('id', $field->id)->update([
                'city' => $city,
                'district' => $district,
                'search_text' => Str::lower(Str::ascii(implode(' ', array_filter([$field->name, $field->address, $district, $city])))),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('sports_fields', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropIndex(['district']);
            $table->dropColumn(['city', 'district', 'search_text']);
        });
    }
};
