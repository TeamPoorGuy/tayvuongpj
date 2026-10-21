<?php

namespace Tests\Feature\Api;

use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Đơn theo khoảng giờ, giá theo thứ, khoá sân theo giờ, lưới ô 30 phút. */
class TimeRangeBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SportsField $field;

    private string $weekday;

    private string $saturday;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);

        // Ngày thường: thứ Hai kế tiếp; cuối tuần: thứ Bảy kế tiếp (đều trong tương lai).
        $this->weekday = now()->next('Monday')->toDateString();
        $this->saturday = now()->next('Saturday')->toDateString();
    }

    private function slot(string $start, string $end, ?int $price = null, ?string $days = null): TimeSlot
    {
        return TimeSlot::create(['sports_field_id' => $this->field->id, 'start_time' => $start, 'end_time' => $end,
            'price_per_hour' => $price, 'days_of_week' => $days, 'is_active' => true]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
    }

    public function test_booking_stores_its_own_start_and_end_time(): void
    {
        $slot = $this->slot('17:30', '19:00');
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id, 'time_slot_id' => $slot->id, 'booking_date' => $this->weekday,
        ])->assertCreated()->assertJsonPath('data.time_slot.start_time', '17:30')->assertJsonPath('data.time_slot.end_time', '19:00');

        $booking = Booking::first();
        $this->assertSame('17:30:00', $booking->start_time);
        $this->assertSame('19:00:00', $booking->end_time);
    }

    public function test_overlap_is_checked_by_time_range_not_by_slot_id(): void
    {
        $slot = $this->slot('18:00', '19:30');
        // Đơn theo khoảng giờ tự do (không có khung giờ) vẫn phải chặn khung giờ giao nhau.
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $this->field->id, 'booking_date' => $this->weekday,
            'start_time' => '17:00', 'end_time' => '18:30', 'status' => 'confirmed', 'total_price' => 1]);
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id, 'time_slot_id' => $slot->id, 'booking_date' => $this->weekday,
        ])->assertStatus(409);

        $slots = $this->getJson("/api/v1/fields/{$this->field->id}/available-slots?date={$this->weekday}")->json('data.slots');
        $this->assertTrue($slots[0]['is_booked']);
    }

    public function test_adjacent_ranges_do_not_overlap(): void
    {
        $slot = $this->slot('19:00', '20:30');
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $this->field->id, 'booking_date' => $this->weekday,
            'start_time' => '17:30', 'end_time' => '19:00', 'status' => 'confirmed', 'total_price' => 1]);
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id, 'time_slot_id' => $slot->id, 'booking_date' => $this->weekday,
        ])->assertCreated();
    }

    public function test_weekend_slot_has_its_own_price_and_only_shows_on_its_days(): void
    {
        $weekdaySlot = $this->slot('18:00', '19:30', 100000, '1,2,3,4,5');
        $weekendSlot = $this->slot('18:00', '19:30', 200000, '6,7');

        $ids = fn ($date) => collect($this->getJson("/api/v1/fields/{$this->field->id}/available-slots?date={$date}")->json('data.slots'))->pluck('id')->all();
        $this->assertSame([$weekdaySlot->id], $ids($this->weekday));
        $this->assertSame([$weekendSlot->id], $ids($this->saturday));

        Sanctum::actingAs($this->customer());
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id, 'time_slot_id' => $weekendSlot->id, 'booking_date' => $this->saturday,
        ])->assertCreated();
        $this->assertEquals(300000, Booking::first()->total_price);

        // Khung cuối tuần không đặt được vào ngày thường.
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id, 'time_slot_id' => $weekendSlot->id, 'booking_date' => $this->weekday,
        ])->assertStatus(409);
    }

    public function test_owner_cannot_create_slots_that_overlap_on_a_shared_day(): void
    {
        $this->slot('18:00', '19:30', null, '1,2,3,4,5');
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/slots", [
            'start_time' => '18:00', 'end_time' => '19:30', 'days_of_week' => [6, 7], 'price_per_hour' => 200000,
        ])->assertCreated()->assertJsonPath('data.days_of_week', [6, 7]);

        $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/slots", [
            'start_time' => '18:30', 'end_time' => '20:00', 'days_of_week' => [5, 6],
        ])->assertStatus(409);
    }

    public function test_slot_times_must_be_on_the_half_hour(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/slots", ['start_time' => '18:10', 'end_time' => '19:30'])
            ->assertStatus(422)->assertJsonValidationErrors('start_time');
    }

    public function test_partial_closure_blocks_only_overlapping_slots(): void
    {
        $morning = $this->slot('06:00', '07:30');
        $evening = $this->slot('18:00', '19:30');
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/closures", [
            'start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '17:00', 'end_time' => '20:00', 'reason' => 'Sửa đèn',
        ])->assertCreated()->assertJsonPath('data.start_time', '17:00');

        $slots = collect($this->getJson("/api/v1/fields/{$this->field->id}/available-slots?date={$this->weekday}")->json('data.slots'))->keyBy('id');
        $this->assertFalse($slots[$morning->id]['is_booked']);
        $this->assertTrue($slots[$evening->id]['is_booked']);

        Sanctum::actingAs($this->customer());
        $this->postJson('/api/v1/customer/bookings', ['sports_field_id' => $this->field->id, 'time_slot_id' => $evening->id, 'booking_date' => $this->weekday])
            ->assertStatus(409);
        $this->postJson('/api/v1/customer/bookings', ['sports_field_id' => $this->field->id, 'time_slot_id' => $morning->id, 'booking_date' => $this->weekday])
            ->assertCreated();
    }

    public function test_partial_closure_is_refused_only_when_a_booking_falls_inside_its_hours(): void
    {
        $this->slot('06:00', '07:30');
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $this->field->id, 'booking_date' => $this->weekday,
            'start_time' => '06:00', 'end_time' => '07:30', 'status' => 'confirmed', 'total_price' => 1]);
        Sanctum::actingAs($this->owner);
        $url = "/api/v1/field-owner/fields/{$this->field->id}/closures";

        $this->postJson($url, ['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '18:00', 'end_time' => '20:00', 'reason' => 'Bảo trì'])
            ->assertCreated();
        $this->postJson($url, ['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '07:00', 'end_time' => '08:00', 'reason' => 'Bảo trì'])
            ->assertStatus(409);
        // Hai lịch khoá theo giờ khác nhau cùng ngày thì hợp lệ, trùng giờ thì không.
        $this->postJson($url, ['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '19:00', 'end_time' => '21:00', 'reason' => 'Trùng'])
            ->assertStatus(409);
    }

    public function test_closure_times_must_come_as_a_pair_on_the_half_hour(): void
    {
        Sanctum::actingAs($this->owner);
        $url = "/api/v1/field-owner/fields/{$this->field->id}/closures";

        $this->postJson($url, ['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '18:00', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->postJson($url, ['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '18:15', 'end_time' => '20:00', 'reason' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('start_time');
    }

    public function test_price_for_sums_half_hour_steps_across_windows(): void
    {
        $this->slot('06:00', '16:00', 80000);
        $this->slot('16:00', '22:00', 120000);

        $this->assertEquals(80000.0, $this->field->priceFor($this->weekday, '07:00', '08:00'));
        // 15:30–16:30 = 30' giá 80k + 30' giá 120k
        $this->assertEquals(100000.0, $this->field->priceFor($this->weekday, '15:30', '16:30'));

        $this->expectException(DomainException::class);
        $this->field->priceFor($this->weekday, '21:30', '22:30');
    }

    public function test_cells_show_free_booked_and_locked_states_with_prices(): void
    {
        $this->slot('17:00', '20:00', 120000);
        Booking::create(['user_id' => $this->customer()->id, 'sports_field_id' => $this->field->id, 'booking_date' => $this->weekday,
            'start_time' => '17:30', 'end_time' => '18:30', 'status' => 'pending', 'total_price' => 1]);
        $this->field->closures()->create(['start_date' => $this->weekday, 'end_date' => $this->weekday, 'start_time' => '19:00', 'end_time' => '19:30', 'reason' => 'x']);

        $states = collect($this->field->cellsFor($this->weekday))->pluck('state', 'start')->all();

        $this->assertSame([
            '17:00' => 'free', '17:30' => 'booked', '18:00' => 'booked', '18:30' => 'free', '19:00' => 'locked', '19:30' => 'free',
        ], $states);
        $this->assertSame(120000.0, $this->field->cellsFor($this->weekday)[0]['price_per_hour']);
    }

    public function test_waitlist_is_offered_when_an_overlapping_booking_is_freed(): void
    {
        $slot = $this->slot('18:00', '19:30');
        $holder = $this->customer();
        $queued = $this->customer();
        $booking = Booking::create(['user_id' => $holder->id, 'sports_field_id' => $this->field->id, 'booking_date' => $this->weekday,
            'start_time' => '17:30', 'end_time' => '19:00', 'status' => 'confirmed', 'total_price' => 1]);
        $wait = BookingWaitlist::create(['user_id' => $queued->id, 'sports_field_id' => $this->field->id, 'time_slot_id' => $slot->id,
            'booking_date' => $this->weekday, 'status' => 'waiting', 'priority_score' => 100]);

        $booking->update(['status' => 'cancelled']);
        app(WaitlistService::class)->handleBookingFreed($booking);

        $this->assertSame('offered', $wait->fresh()->status);
    }
}
