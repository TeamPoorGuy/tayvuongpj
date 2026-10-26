<?php

namespace Tests\Feature\Api;

use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tìm sân: không dấu, nhiều từ, lọc theo quận/thành phố. */
class FieldSearchTest extends TestCase
{
    use RefreshDatabase;

    private function field(string $name, string $address, array $extra = []): SportsField
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ Sân', 'owner_id_number' => (string) random_int(100000000000, 999999999999),
            'business_name' => 'CLB', 'business_address' => 'Hà Nội', 'business_phone' => '0900000009',
            'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $cat = SportCategory::firstOrCreate(['slug' => 'cau-long'], ['name' => 'Cầu lông']);
        $type = FieldType::firstOrCreate(['slug' => 'san-don'], ['sport_category_id' => $cat->id, 'name' => 'Sân đơn']);

        return SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => $name,
            'slug' => str()->slug($name).'-'.str()->random(4), 'address' => $address,
            'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ] + $extra);
    }

    private function names(string $query): array
    {
        return collect($this->getJson('/api/v1/fields?'.$query)->assertOk()->json('data'))->pluck('name')->all();
    }

    public function test_city_and_district_are_derived_from_address(): void
    {
        $field = $this->field('Sân A', '12 Đường Cầu Giấy, Cầu Giấy, Hà Nội');

        $this->assertSame('Hà Nội', $field->city);
        $this->assertSame('Cầu Giấy', $field->district);
    }

    public function test_search_ignores_accents_including_d_stroke(): void
    {
        $this->field('Sân Đống Đa', '1 Tây Sơn, Đống Đa, Hà Nội');
        $this->field('Sân Cầu Giấy', '88 Cầu Giấy, Cầu Giấy, Hà Nội');

        $this->assertSame(['Sân Đống Đa'], $this->names('keyword=dong+da'));
        $this->assertSame(['Sân Cầu Giấy'], $this->names('keyword=cau+giay'));
    }

    public function test_search_matches_words_spread_across_name_and_address(): void
    {
        $this->field('Cầu lông Tây Vương', '123 Lê Văn Lương, Thanh Xuân, Hà Nội');
        $this->field('Cầu lông Sao Mai', '5 Nguyễn Trãi, Hà Đông, Hà Nội');

        $this->assertSame(['Cầu lông Tây Vương'], $this->names('keyword=cau+long+thanh+xuan'));
    }

    public function test_filter_by_district_and_city(): void
    {
        $this->field('Sân Q1', '1 Lê Lợi, Quận 1, TP Hồ Chí Minh');
        $this->field('Sân Thanh Xuân', '2 Nguyễn Trãi, Thanh Xuân, Hà Nội');

        $this->assertSame(['Sân Q1'], $this->names('district='.urlencode('Quận 1')));
        $this->assertSame(['Sân Thanh Xuân'], $this->names('city='.urlencode('Hà Nội')));
    }

    public function test_locations_endpoint_groups_districts_by_city(): void
    {
        $this->field('Sân Q1', '1 Lê Lợi, Quận 1, TP Hồ Chí Minh');
        $this->field('Sân TX', '2 Nguyễn Trãi, Thanh Xuân, Hà Nội');
        $this->field('Sân Cầu Giấy', '88 Cầu Giấy, Cầu Giấy, Hà Nội');

        $data = collect($this->getJson('/api/v1/fields/locations')->assertOk()->json('data'))->keyBy('city');

        $this->assertSame(['Cầu Giấy', 'Thanh Xuân'], $data['Hà Nội']['districts']);
        $this->assertSame(['Quận 1'], $data['TP Hồ Chí Minh']['districts']);
    }
}
