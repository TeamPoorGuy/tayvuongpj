<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisualScheduleAndLocationTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithFields(): array
    {
        $owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'owner_name' => 'Chủ Sân Thể Thao',
            'owner_id_number' => '001099009999',
            'business_name' => 'CLB Vận Hành Lịch Trực Quan',
            'business_address' => '456 Hoàng Hoa Thám, Ba Đình, Hà Nội',
            'business_phone' => '0988888888',
            'business_license' => 'GPKD-99999',
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);

        $cat = SportCategory::firstOrCreate(
            ['slug' => 'bong-da'],
            ['name' => 'Bóng đá']
        );

        $type = FieldType::firstOrCreate(
            ['slug' => 'san-7-schedule'],
            ['sport_category_id' => $cat->id, 'name' => 'Sân 7 người']
        );

        $field1 = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân A1 - Hoàng Hoa Thám',
            'slug' => 'san-a1-hoang-hoa-tham',
            'address' => '456 Hoàng Hoa Thám, Ba Đình, Hà Nội',
            'latitude' => 21.04212300,
            'longitude' => 105.81987600,
            'price_per_hour' => 250000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $field2 = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân A2 - Hoàng Hoa Thám',
            'slug' => 'san-a2-hoang-hoa-tham',
            'address' => '456 Hoàng Hoa Thám, Ba Đình, Hà Nội',
            'latitude' => 21.04215000,
            'longitude' => 105.81990000,
            'price_per_hour' => 300000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $slot1 = TimeSlot::create([
            'sports_field_id' => $field1->id,
            'start_time' => '17:30',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        $slot2 = TimeSlot::create([
            'sports_field_id' => $field2->id,
            'start_time' => '19:00',
            'end_time' => '20:30',
            'is_active' => true,
        ]);

        return [$owner, $field1, $field2, $slot1, $slot2];
    }

    public function test_public_field_endpoint_includes_latitude_and_longitude(): void
    {
        [$owner, $field1] = $this->createOwnerWithFields();

        $response = $this->getJson("/api/v1/fields/{$field1->slug}");

        $response->assertOk()
            ->assertJsonPath('data.latitude', '21.04212300')
            ->assertJsonPath('data.longitude', '105.81987600')
            ->assertJsonPath('data.address', '456 Hoàng Hoa Thám, Ba Đình, Hà Nội');
    }

    public function test_owner_can_filter_bookings_by_date_and_week_range_for_visual_schedule(): void
    {
        [$owner, $field1, $field2, $slot1, $slot2] = $this->createOwnerWithFields();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $date1 = '2026-10-12';
        $date2 = '2026-10-14';

        // Đơn 1 ở Field 1 ngày 12/10
        Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field1->id,
            'time_slot_id' => $slot1->id,
            'booking_date' => $date1,
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 375000,
        ]);

        // Đơn 2 ở Field 2 ngày 14/10
        Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field2->id,
            'time_slot_id' => $slot2->id,
            'booking_date' => $date2,
            'status' => BookingStatus::Pending->value,
            'total_price' => 450000,
        ]);

        Sanctum::actingAs($owner);

        // Lọc theo ngày đơn lẻ (Day View)
        $dayResponse = $this->getJson("/api/v1/field-owner/bookings?date={$date1}&per_page=100");
        $dayResponse->assertOk();
        $this->assertCount(1, $dayResponse->json('data'));
        $this->assertSame($field1->id, $dayResponse->json('data.0.field.id'));

        // Lọc theo tuần (Week View range)
        $weekResponse = $this->getJson("/api/v1/field-owner/bookings?start_date=2026-10-12&end_date=2026-10-18&per_page=200");
        $weekResponse->assertOk();
        $this->assertCount(2, $weekResponse->json('data'));

        // Lọc theo field_id cụ thể
        $fieldFilterResponse = $this->getJson("/api/v1/field-owner/bookings?field_id={$field2->id}&per_page=100");
        $fieldFilterResponse->assertOk();
        $this->assertCount(1, $fieldFilterResponse->json('data'));
        $this->assertSame($field2->id, $fieldFilterResponse->json('data.0.field.id'));

        // Lọc theo status
        $statusFilterResponse = $this->getJson('/api/v1/field-owner/bookings?status=confirmed&per_page=100');
        $statusFilterResponse->assertOk();
        $this->assertCount(1, $statusFilterResponse->json('data'));
        $this->assertSame('confirmed', $statusFilterResponse->json('data.0.status'));
    }

    public function test_available_slots_endpoint_shows_is_booked_flag_for_customer(): void
    {
        [$owner, $field1, $field2, $slot1] = $this->createOwnerWithFields();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $date = '2026-10-20';

        // Đặt slot 1
        Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field1->id,
            'time_slot_id' => $slot1->id,
            'booking_date' => $date,
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 375000,
        ]);

        $response = $this->getJson("/api/v1/fields/{$field1->id}/available-slots?date={$date}");
        $response->assertOk();

        $slots = $response->json('data.slots');
        $this->assertNotEmpty($slots);
        $targetSlot = collect($slots)->firstWhere('id', $slot1->id);
        $this->assertNotNull($targetSlot);
        $this->assertTrue($targetSlot['is_booked']);
    }
}
