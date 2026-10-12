<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchParticipant;
use App\Models\MatchRequest;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmartMatchdayFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_customer_can_create_and_browse_match_requests(): void
    {
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field, $slot] = $this->createApprovedFieldWithSlots();

        $response = $this->actingAs($host)->post('/customer/matches', [
            'sports_field_id' => $field->id,
            'sport_category_id' => $field->fieldType->sport_category_id,
            'title' => 'Tìm kèo đá banh tối thứ 6',
            'play_date' => now()->addDays(2)->toDateString(),
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'skill_level' => 'intermediate',
            'players_needed' => 4,
            'contact_phone' => '0901234567',
            'description' => 'Cần tìm 4 bạn đá giao lưu vui vẻ.',
        ]);

        $this->assertDatabaseHas('match_requests', [
            'user_id' => $host->id,
            'title' => 'Tìm kèo đá banh tối thứ 6',
            'status' => 'open',
            'players_needed' => 4,
            'joined_count' => 1,
        ]);

        $match = MatchRequest::where('user_id', $host->id)->first();
        $this->assertNotNull($match);
        $response->assertRedirect('/matches/' . $match->id);

        // Browse list and detail page
        $this->get('/matches')->assertOk()->assertSee('Tìm kèo đá banh tối thứ 6');
        $this->get('/matches/' . $match->id)->assertOk()->assertSee('Cần tìm 4 bạn đá giao lưu vui vẻ.');
    }

    public function test_matchmaking_join_accept_leave_and_cancel_lifecycle(): void
    {
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $guest = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field] = $this->createApprovedFieldWithSlots();

        $match = MatchRequest::create([
            'user_id' => $host->id,
            'sports_field_id' => $field->id,
            'sport_category_id' => $field->fieldType->sport_category_id,
            'title' => 'Kèo bóng rổ 3x3',
            'play_date' => now()->addDays(3)->toDateString(),
            'start_time' => '17:00',
            'end_time' => '18:30',
            'skill_level' => 'any',
            'players_needed' => 2,
            'joined_count' => 1,
            'contact_phone' => '0901234567',
            'status' => 'open',
        ]);

        // Guest joins match
        $joinResponse = $this->actingAs($guest)->post('/customer/matches/' . $match->id . '/join', [
            'message' => 'Cho mình xin 1 slot vị trí PG nhé.',
        ]);
        $joinResponse->assertRedirect();
        $this->assertDatabaseHas('match_participants', [
            'match_request_id' => $match->id,
            'user_id' => $guest->id,
            'status' => 'pending',
            'message' => 'Cho mình xin 1 slot vị trí PG nhé.',
        ]);

        $participant = MatchParticipant::where('match_request_id', $match->id)
            ->where('user_id', $guest->id)
            ->first();

        // Host accepts guest
        $acceptResponse = $this->actingAs($host)->post('/customer/matches/' . $match->id . '/participants/' . $participant->id, [
            'status' => 'accepted',
        ]);
        $acceptResponse->assertRedirect();
        $this->assertEquals('accepted', $participant->fresh()->status);
        $this->assertEquals(2, $match->fresh()->joined_count);

        // Guest leaves match
        $leaveResponse = $this->actingAs($guest)->post('/customer/matches/' . $match->id . '/leave');
        $leaveResponse->assertRedirect();
        $this->assertDatabaseMissing('match_participants', [
            'id' => $participant->id,
        ]);
        $this->assertEquals(1, $match->fresh()->joined_count);

        // Host cancels match
        $cancelResponse = $this->actingAs($host)->post('/customer/matches/' . $match->id . '/cancel');
        $cancelResponse->assertRedirect();
        $this->assertEquals('cancelled', $match->fresh()->status);
    }

    public function test_admin_can_view_and_cancel_match_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field] = $this->createApprovedFieldWithSlots();

        $match = MatchRequest::create([
            'user_id' => $host->id,
            'sports_field_id' => $field->id,
            'sport_category_id' => $field->fieldType->sport_category_id,
            'title' => 'Kèo vi phạm quy định',
            'play_date' => now()->addDays(2)->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:30',
            'skill_level' => 'any',
            'status' => 'open',
            'players_needed' => 2,
            'contact_phone' => '0901234567',
        ]);

        $this->actingAs($admin)->get('/admin/matches')->assertOk()->assertSee('Kèo vi phạm quy định');

        $this->actingAs($admin)->post('/admin/matches/' . $match->id . '/cancel', [
            'reason' => 'Vi phạm tiêu chuẩn cộng đồng',
        ])->assertRedirect();

        $this->assertEquals('cancelled', $match->fresh()->status);
    }

    public function test_waitlist_registration_offer_acceptance_flow(): void
    {
        $customerA = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $customerB = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field, $slot] = $this->createApprovedFieldWithSlots();
        $date = now()->addDays(2)->toDateString();

        // Customer A books the slot
        $bookingA = Booking::create([
            'user_id' => $customerA->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'total_price' => 150000,
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        // Customer B joins waitlist on the same slot
        $waitlistResponse = $this->actingAs($customerB)->post('/customer/waitlists', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ]);
        $waitlistResponse->assertRedirect();
        $this->assertDatabaseHas('booking_waitlists', [
            'user_id' => $customerB->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'status' => 'waiting',
        ]);

        $waitlistEntry = BookingWaitlist::where('user_id', $customerB->id)->first();
        $this->assertEquals($date, $waitlistEntry->booking_date->toDateString());

        // Customer A cancels booking
        $this->actingAs($customerA)->post('/customer/bookings/' . $bookingA->id . '/cancel', [
            'cancel_reason' => 'Bận đột xuất',
        ])->assertRedirect();

        // Waitlist entry is offered to Customer B
        $waitlistEntry->refresh();
        $this->assertEquals('offered', $waitlistEntry->status);
        $this->assertNotNull($waitlistEntry->offer_expires_at);

        // Customer B accepts the offered slot
        $acceptResponse = $this->actingAs($customerB)->post('/customer/waitlists/' . $waitlistEntry->id . '/accept');
        $acceptResponse->assertRedirect('/customer/bookings');

        $this->assertEquals('accepted', $waitlistEntry->fresh()->status);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $customerB->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    public function test_customer_cannot_cancel_booking_past_cancel_window(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field, $slot] = $this->createApprovedFieldWithSlots();

        // Booking confirmed 3 hours ago (window is 2 hours)
        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'total_price' => 150000,
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now()->subHours(3),
        ]);

        $response = $this->actingAs($customer)->post('/customer/bookings/' . $booking->id . '/cancel');
        $response->assertSessionHas('error');
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_late_cancellation_deducts_trust_score(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        [$field, $slot] = $this->createApprovedFieldWithSlots();

        // Booking starting in 3 hours (less than 4 hours late threshold, confirmed just now so within cancel window)
        $slot->update([
            'start_time' => now()->addHours(3)->format('H:i:s'),
            'end_time' => now()->addHours(4)->format('H:i:s'),
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->toDateString(),
            'total_price' => 150000,
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($customer)->post('/customer/bookings/' . $booking->id . '/cancel', [
            'cancel_reason' => 'Việc gấp',
        ])->assertRedirect();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertEquals(85, $customer->fresh()->trust_score); // 100 - 15

        $this->assertDatabaseHas('trust_score_logs', [
            'user_id' => $customer->id,
            'change' => -15,
            'score_after' => 85,
        ]);
    }

    public function test_field_owner_can_check_in_booking_by_code_and_reward_trust_score(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Sân vận động Sao Mai',
            'business_address' => 'Hà Nội',
            'business_phone' => '0912345678',
            'verification_status' => 'approved',
        ]);

        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        [$field, $slot] = $this->createApprovedFieldWithSlots($owner);

        // Slot active today around current time
        $slot->update([
            'start_time' => now()->subMinutes(10)->format('H:i'),
            'end_time' => now()->addMinutes(50)->format('H:i'),
        ]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->toDateString(),
            'total_price' => 150000,
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->assertNotNull($booking->check_in_code);

        // Owner checks in using the check-in code
        $response = $this->actingAs($owner)
            ->from('/field-owner/bookings')
            ->post('/field-owner/bookings/check-in', [
                'check_in_code' => $booking->check_in_code,
            ]);

        $response->assertRedirect('/field-owner/bookings');
        $booking->refresh();

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertEquals($owner->id, $booking->checked_in_by);

        // Customer receives +10 trust score
        $this->assertEquals(110, $customer->fresh()->trust_score);
        $this->assertDatabaseHas('trust_score_logs', [
            'user_id' => $customer->id,
            'change' => 10,
            'score_after' => 110,
        ]);
    }

    public function test_smart_suggestions_ajax_endpoint_returns_json(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlots();

        $response = $this->getJson('/ajax/fields/' . $field->id . '/suggestions?date=' . now()->addDay()->toDateString() . '&time_slot_id=' . $slot->id);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'suggestions',
            ]);
    }

    public function test_scheduled_console_commands_run_successfully(): void
    {
        $this->artisan('bookings:sync')->assertSuccessful();
        $this->artisan('waitlist:expire')->assertSuccessful();
        $this->artisan('matches:close-past')->assertSuccessful();
    }

    /** @return array{SportsField, TimeSlot} */
    private function createApprovedFieldWithSlots(?User $owner = null): array
    {
        if (!$owner) {
            $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
            FieldOwnerProfile::create([
                'user_id' => $owner->id,
                'business_name' => 'Sân vận động Test',
                'business_address' => 'Hà Nội',
                'business_phone' => '0909999999',
                'verification_status' => 'approved',
            ]);
        }

        $category = SportCategory::create([
            'name' => 'Bóng đá',
            'slug' => 'bong-da-' . uniqid(),
            'is_active' => true,
        ]);

        $type = FieldType::create([
            'sport_category_id' => $category->id,
            'name' => 'Sân 7 người',
            'slug' => 'san-7-nguoi-' . uniqid(),
            'is_active' => true,
        ]);

        $field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Test Smart ' . uniqid(),
            'slug' => 'san-test-' . uniqid(),
            'address' => '123 Đường Cầu Giấy, Hà Nội',
            'latitude' => 21.0333,
            'longitude' => 105.7999,
            'price_per_hour' => 150000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '14:00',
            'end_time' => '15:30',
            'price_per_hour' => 150000,
            'is_active' => true,
        ]);

        return [$field, $slot];
    }
}
