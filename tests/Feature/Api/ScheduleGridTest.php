<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Lưới 30 phút: gom sân cùng cụm, chờ/gợi ý/đặt cố định theo khoảng giờ. */
class ScheduleGridTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FieldType $type;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->approvedOwner();
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $this->type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->date = today()->addDays(2)->toDateString();
    }

    private function approvedOwner(): User
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => (string) random_int(100000000000, 999999999999),
            'business_name' => 'CLB', 'business_address' => 'HN', 'business_phone' => '0900000009',
            'verification_status' => 'approved', 'verified_at' => now(),
        ]);

        return $owner;
    }

    private function court(string $name, string $address = '1 Đường Trần Thái Tông, Cầu Giấy, Hà Nội', ?User $owner = null): SportsField
    {
        $field = SportsField::create([
            'field_owner_id' => ($owner ?? $this->owner)->id, 'field_type_id' => $this->type->id, 'name' => $name,
            'slug' => str()->slug($name).'-'.str()->random(4), 'address' => $address,
            'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
        foreach (TimeSlot::DEFAULT_SCHEDULE as [$start, $end]) {
            TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => $start, 'end_time' => $end, 'is_active' => true]);
        }

        return $field;
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_schedule_groups_courts_of_the_same_owner_and_address_with_the_viewed_one_first(): void
    {
        $c1 = $this->court('C.Lông 1');
        $c2 = $this->court('C.Lông 2', '  1 đường trần thái tông, cầu giấy, hà nội ');
        $this->court('Sân khác địa chỉ', '9 Xuân Thuỷ, Cầu Giấy, Hà Nội');
        $this->court('Sân chủ khác', '1 Đường Trần Thái Tông, Cầu Giấy, Hà Nội', $this->approvedOwner());

        $fields = $this->getJson("/api/v1/fields/{$c2->id}/schedule?date={$this->date}")->assertOk()->json('data.fields');

        $this->assertSame([$c2->id, $c1->id], array_column($fields, 'id'));
        // 06:00–22:00 = 32 ô 30 phút
        $this->assertCount(32, $fields[0]['cells']);
        $this->assertEquals(['start' => '06:00', 'end' => '06:30', 'price_per_hour' => 100000, 'state' => 'free'], $fields[0]['cells'][0]);
    }

    public function test_booked_cells_show_up_after_checkout(): void
    {
        $court = $this->court('C.Lông 1');
        Sanctum::actingAs($this->customer());
        $this->postJson('/api/v1/customer/bookings', ['booking_date' => $this->date, 'items' => [
            ['sports_field_id' => $court->id, 'start_time' => '18:00', 'end_time' => '19:30'],
        ]])->assertCreated();

        $states = collect($this->getJson("/api/v1/fields/{$court->id}/schedule?date={$this->date}")->json('data.fields.0.cells'))
            ->pluck('state', 'start');

        $this->assertSame(['booked', 'booked', 'booked', 'free'], [$states['18:00'], $states['18:30'], $states['19:00'], $states['19:30']]);
    }

    public function test_new_field_gets_wide_opening_windows(): void
    {
        $this->assertSame([['06:00', '16:00'], ['16:00', '22:00']], TimeSlot::DEFAULT_SCHEDULE);
    }

    public function test_customer_can_join_the_waitlist_for_a_booked_range(): void
    {
        $court = $this->court('C.Lông 1');
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $court->id, 'booking_date' => $this->date,
            'start_time' => '18:00', 'end_time' => '20:00', 'status' => 'confirmed', 'total_price' => 1]);

        Sanctum::actingAs($this->customer());
        $this->postJson('/api/v1/customer/waitlist', [
            'sports_field_id' => $court->id, 'booking_date' => $this->date, 'start_time' => '18:00', 'end_time' => '20:00',
        ])->assertCreated()->assertJsonPath('data.time_slot.start_time', '18:00');

        // Khoảng còn trống thì không cần chờ.
        $this->postJson('/api/v1/customer/waitlist', [
            'sports_field_id' => $court->id, 'booking_date' => $this->date, 'start_time' => '20:00', 'end_time' => '21:00',
        ])->assertStatus(409);
        $this->assertSame(1, BookingWaitlist::count());
    }

    public function test_suggestions_for_a_booked_range_offer_free_ranges_of_the_same_length(): void
    {
        $court = $this->court('C.Lông 1');
        $other = $this->court('Sân khác', '9 Xuân Thuỷ, Cầu Giấy, Hà Nội');
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $court->id, 'booking_date' => $this->date,
            'start_time' => '18:00', 'end_time' => '20:00', 'status' => 'confirmed', 'total_price' => 1]);

        $data = collect($this->getJson("/api/v1/fields/{$court->slug}/suggestions?start_time=18:00&end_time=20:00&date={$this->date}")
            ->assertOk()->json('data'));

        $sameDay = $data->where('type', 'same_field_today');
        $this->assertSame(['16:00', '20:00'], $sameDay->pluck('start_time')->sort()->values()->all());
        $this->assertTrue($sameDay->every(fn ($s) => strtotime($s['end_time']) - strtotime($s['start_time']) === 7200));
        $this->assertSame($other->id, $data->firstWhere('type', 'other_field_today')['field_id']);
        $this->assertNotNull($data->firstWhere('type', 'same_slot_tomorrow'));
    }

    public function test_recurring_booking_by_time_range_creates_one_payment(): void
    {
        $court = $this->court('C.Lông 1');
        Sanctum::actingAs($this->customer());

        $res = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $court->id, 'start_time' => '18:00', 'end_time' => '20:00',
            'start_date' => $this->date, 'weeks' => 3,
        ])->assertCreated();

        $this->assertSame(3, Booking::count());
        $this->assertTrue(Booking::get()->every(fn ($b) => $b->start_time === '18:00:00' && $b->end_time === '20:00:00'));
        $this->assertEquals(600000, Payment::findOrFail($res->json('data.payment.id'))->total);
    }

    public function test_match_room_on_a_field_accepts_any_half_hour_range_inside_opening_hours(): void
    {
        $court = $this->court('C.Lông 1');
        Sanctum::actingAs($this->customer());
        $payload = [
            'sport_category_id' => $this->type->sport_category_id, 'sports_field_id' => $court->id, 'title' => 'Kèo tối',
            'play_date' => $this->date, 'start_time' => '18:00', 'end_time' => '19:30', 'skill_level' => 'any',
            'players_needed' => 2, 'contact_phone' => '0912345678',
        ];

        $this->postJson('/api/v1/customer/matches', $payload)->assertCreated();
        // Ngoài giờ mở cửa (sân đóng 22:00) thì bị từ chối.
        $this->postJson('/api/v1/customer/matches', ['start_time' => '21:30', 'end_time' => '22:30'] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors('start_time');
    }
}
