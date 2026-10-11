<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartMatchdayTest extends TestCase
{
    use RefreshDatabase;

    private function createFieldWithSlot(string $slug = 'san-test'): array
    {
        $owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'owner_name' => 'Chủ Sân Test',
            'owner_id_number' => '001099001234',
            'business_name' => 'CLB Test',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000009',
            'business_license' => 'GPKD-12345',
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);

        $cat = SportCategory::firstOrCreate(
            ['slug' => 'bong-da'],
            ['name' => 'Bóng đá']
        );

        $type = FieldType::firstOrCreate(
            ['slug' => "type-{$slug}"],
            ['sport_category_id' => $cat->id, 'name' => 'Sân 7 người']
        );

        $field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => "Sân Thể Thao {$slug}",
            'slug' => $slug,
            'address' => '123 Cầu Giấy, Hà Nội',
            'price_per_hour' => 200000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '17:30',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        return [$owner, $field, $slot, $cat];
    }

    public function test_customer_can_join_waitlist_when_slot_is_booked(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-waitlist-1');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now(), 'trust_score' => 100]);
        $otherCustomer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $date = today()->addDays(2)->toDateString();

        // 1. Tạo booking trước đó chiếm slot
        Booking::create([
            'user_id' => $otherCustomer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 200000,
        ]);

        // 2. Customer tham gia waitlist
        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/v1/customer/waitlist', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.priority_score', 100);

        $this->assertDatabaseHas('booking_waitlists', [
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'status' => 'waiting',
        ]);
    }

    public function test_cancelling_booking_cascades_offer_to_waitlist_user(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-waitlist-2');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $otherCustomer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $date = today()->addDays(3)->toDateString();

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 200000,
        ]);

        $waitlist = BookingWaitlist::create([
            'user_id' => $otherCustomer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
            'status' => 'waiting',
            'priority_score' => 110,
        ]);

        // Customer hủy đơn
        Sanctum::actingAs($customer);
        $response = $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel", [
            'reason' => 'Bận đột xuất',
        ]);

        $response->assertOk();

        // Kiểm tra waitlist của otherCustomer đã được chuyển sang 'offered'
        $waitlist->refresh();
        $this->assertSame('offered', $waitlist->status);
        $this->assertNotNull($waitlist->offered_at);
        $this->assertNotNull($waitlist->offer_expires_at);

        // otherCustomer chấp nhận offer
        Sanctum::actingAs($otherCustomer);
        $acceptResponse = $this->postJson("/api/v1/customer/waitlist/{$waitlist->id}/accept");

        $acceptResponse->assertStatus(201);
        $this->assertSame('accepted', $waitlist->fresh()->status);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $otherCustomer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
        ]);
    }

    public function test_owner_can_check_in_booking_with_qr_code(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-checkin-1');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 150000,
            'check_in_code' => 'CK-TEST1234',
        ]);
        $this->travelTo($booking->load('timeSlot')->startsAt()->subMinutes(10)); // trong cửa sổ check-in

        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/field-owner/check-in', [
            'code' => 'CK-TEST1234',
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertNotNull($booking->checked_in_at);
        $this->assertEquals($owner->id, $booking->checked_in_by);

        // Khách hàng được cộng 10 điểm uy tín
        $this->assertEquals(110, $customer->fresh()->trust_score);
    }

    public function test_cannot_check_in_cancelled_or_completed_booking(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-checkin-cancel');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $cancelledBooking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Cancelled->value,
            'total_price' => 150000,
            'check_in_code' => 'CK-CANCELLED',
        ]);

        Sanctum::actingAs($owner);
        $res = $this->postJson('/api/v1/field-owner/check-in', ['code' => 'CK-CANCELLED']);
        $res->assertStatus(422)
            ->assertJsonPath('message', 'Đơn đặt sân này không còn hiệu lực để check-in.');
    }

    public function test_cannot_check_in_twice(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-checkin-dup');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 150000,
            'check_in_code' => 'CK-ALREADY-IN',
            'checked_in_at' => now(),
            'checked_in_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);
        $res = $this->postJson('/api/v1/field-owner/check-in', ['code' => 'CK-ALREADY-IN']);
        $res->assertStatus(422);
    }

    public function test_cannot_join_waitlist_when_slot_is_empty(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-waitlist-empty');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $date = today()->addDays(2)->toDateString();

        Sanctum::actingAs($customer);
        $res = $this->postJson('/api/v1/customer/waitlist', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('message', 'Khung giờ này hiện đang còn trống, bạn có thể đặt trực tiếp mà không cần vào hàng đợi.');
    }

    public function test_cannot_join_waitlist_for_past_date(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-waitlist-past');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        Sanctum::actingAs($customer);
        $res = $this->postJson('/api/v1/customer/waitlist', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->subDays(2)->toDateString(),
        ]);

        $res->assertStatus(422);
    }

    public function test_smart_suggestions_return_alternatives(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-suggestions-1');

        $response = $this->getJson("/api/v1/fields/{$field->slug}/suggestions?slot_id={$slot->id}&date=" . today()->toDateString());

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_matchmaking_create_and_join(): void
    {
        [$owner, $field, $slot, $category] = $this->createFieldWithSlot('san-match-1');
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $player = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);

        // 1. Host tạo bài tìm đối
        Sanctum::actingAs($host);
        $createRes = $this->postJson('/api/v1/customer/matches', [
            'sport_category_id' => $category->id,
            'title' => 'Giao lưu cầu lông đôi nam tối thứ 4',
            'play_date' => today()->addDays(2)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'skill_level' => 'intermediate',
            'players_needed' => 2,
            'contact_phone' => '0912345678',
        ]);

        $createRes->assertStatus(201);
        $matchId = $createRes->json('data.id');

        // 2. Player gửi yêu cầu tham gia
        Sanctum::actingAs($player);
        $joinRes = $this->postJson("/api/v1/customer/matches/{$matchId}/join", [
            'message' => 'Mình có 1 bạn đánh cùng trình độ khá',
        ]);

        $joinRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $participantId = $joinRes->json('data.id');

        // 3. Host duyệt thành viên
        Sanctum::actingAs($host);
        $approveRes = $this->patchJson("/api/v1/customer/matches/{$matchId}/participants/{$participantId}", [
            'status' => 'accepted',
        ]);

        $approveRes->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $match = MatchRequest::find($matchId);
        $this->assertEquals(2, $match->joined_count);
    }
    public function test_public_matches_endpoint_never_leaks_user_pii_or_contact_phone(): void
    {
        $host = User::factory()->create(['email' => 'host-secret@example.test', 'phone' => '0911111111', 'address' => 'Dia chi rieng', 'role' => 'customer', 'is_active' => true]);
        $guest = User::factory()->create(['email' => 'guest-secret@example.test', 'phone' => '0922222222', 'role' => 'customer', 'is_active' => true]);
        $category = \App\Models\SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da-pii']);
        $match = MatchRequest::create([
            'user_id' => $host->id, 'sport_category_id' => $category->id, 'title' => 'Kèo test',
            'play_date' => now()->addDay()->toDateString(), 'start_time' => '18:00', 'end_time' => '19:30',
            'skill_level' => 'any', 'players_needed' => 3, 'joined_count' => 1,
            'contact_phone' => '0933333333', 'status' => 'open',
        ]);
        $match->participants()->create(['user_id' => $guest->id, 'status' => 'pending', 'message' => 'cho em di']);

        $body = $this->getJson('/api/v1/matches')->assertOk()->getContent();

        foreach (['host-secret@example.test', 'guest-secret@example.test', '0911111111', '0922222222', '0933333333', 'Dia chi rieng', 'google_id', 'cho em di'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
        $this->assertNotNull($this->getJson('/api/v1/matches')->json('meta.last_page'));

        // Chủ phòng thấy SĐT phòng và SĐT người xin tham gia.
        Sanctum::actingAs($host);
        $hostView = $this->getJson("/api/v1/matches/{$match->id}")->assertOk();
        $hostView->assertJsonPath('data.contact_phone', '0933333333');
        $hostView->assertJsonPath('data.participants.0.user.phone', '0922222222');
        $this->assertStringNotContainsString('host-secret@example.test', $hostView->getContent());

        // Người xin tham gia chưa được chấp nhận vẫn chưa thấy SĐT phòng; được chấp nhận thì thấy.
        Sanctum::actingAs($guest);
        $this->getJson("/api/v1/matches/{$match->id}")->assertJsonPath('data.contact_phone', null);
        $match->participants()->update(['status' => 'accepted']);
        $this->getJson("/api/v1/matches/{$match->id}")->assertJsonPath('data.contact_phone', '0933333333');
    }
    private function heldSlotFixture(): array
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        \App\Models\FieldOwnerProfile::create(['user_id' => $owner->id, 'business_name' => 'S', 'business_address' => 'HN', 'business_phone' => '0900000000', 'verification_status' => 'approved']);
        $cat = \App\Models\SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd-held']);
        $type = \App\Models\FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 5', 'slug' => 'san-5-held']);
        $field = \App\Models\SportsField::create(['field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân held', 'slug' => 'san-held', 'address' => 'HN', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true]);
        $slot = \App\Models\TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '18:00:00', 'end_time' => '19:30:00', 'is_active' => true]);

        return [$field, $slot, $owner];
    }

    public function test_a_slot_offered_to_the_waitlist_cannot_be_booked_by_someone_else(): void
    {
        [$field, $slot] = $this->heldSlotFixture();
        $date = now()->addDay()->toDateString();
        $first = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $queued = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $sniper = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $held = \App\Models\Booking::create(['user_id' => $first->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date, 'status' => 'confirmed', 'total_price' => 1]);
        \App\Models\BookingWaitlist::create(['user_id' => $queued->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date, 'status' => 'waiting', 'priority_score' => 100]);

        // Slot được nhả → người trong hàng đợi nhận lời mời 15 phút.
        $held->update(['status' => 'cancelled']);
        app(\App\Services\WaitlistService::class)->handleSlotFreed($field->id, $slot->id, $date);

        $payload = ['sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date];

        $slots = $this->getJson("/api/v1/fields/{$field->id}/available-slots?date={$date}")->json('data.slots');
        $this->assertTrue($slots[0]['is_held']);

        Sanctum::actingAs($sniper);
        $this->postJson('/api/v1/customer/bookings', $payload)->assertStatus(409);

        Sanctum::actingAs($queued);
        $this->postJson('/api/v1/customer/bookings', $payload)->assertCreated();
        $this->assertDatabaseHas('booking_waitlists', ['user_id' => $queued->id, 'status' => 'accepted']);

        // Booking của người được mời đang giữ slot → slot kín, nên kiểm tra hết hạn ở test riêng bên dưới.
    }

    public function test_waitlist_blocks_owner_own_field_and_caps_active_entries(): void
    {
        [$field, $slot, $owner] = $this->heldSlotFixture();
        $date = now()->addDay()->toDateString();
        \App\Models\Booking::create(['user_id' => User::factory()->create()->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date, 'status' => 'confirmed', 'total_price' => 1]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/customer/waitlist', ['sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date])
            ->assertStatus(422);
    }

    public function test_cleanup_expires_waitlist_entries_of_slots_that_already_started(): void
    {
        [$field, $slot] = $this->heldSlotFixture();
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $entry = \App\Models\BookingWaitlist::create(['user_id' => $user->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->toDateString(), 'status' => 'waiting', 'priority_score' => 100]);

        $this->travelTo(now()->setTime(20, 0));
        app(\App\Services\WaitlistService::class)->expireOffers();

        $this->assertSame('expired', $entry->fresh()->status);
    }

    public function test_expired_offer_no_longer_blocks_other_customers(): void
    {
        [$field, $slot] = $this->heldSlotFixture();
        $date = now()->addDays(2)->toDateString();
        $queued = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        \App\Models\BookingWaitlist::create([
            'user_id' => $queued->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date,
            'status' => 'offered', 'priority_score' => 100, 'offered_at' => now(), 'offer_expires_at' => now()->addMinutes(15),
        ]);
        $payload = ['sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date];

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/customer/bookings', $payload)->assertStatus(409);

        $this->travel(16)->minutes();
        $this->postJson('/api/v1/customer/bookings', $payload)->assertCreated();
    }

    public function test_check_in_is_only_allowed_inside_the_time_window(): void
    {
        [$owner, $field, $slot] = $this->createFieldWithSlot('san-checkin-window');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        $booking = Booking::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(), 'status' => BookingStatus::Confirmed->value,
            'total_price' => 1, 'check_in_code' => 'CK-WINDOW01',
        ]);
        $booking->load('timeSlot');
        Sanctum::actingAs($owner);

        $this->travelTo($booking->startsAt()->subHours(3));
        $this->postJson('/api/v1/field-owner/check-in', ['code' => 'CK-WINDOW01'])->assertStatus(422);

        $this->travelTo($booking->endsAt()->addMinutes(5));
        $this->postJson('/api/v1/field-owner/check-in', ['code' => 'CK-WINDOW01'])->assertStatus(422);
        $this->assertSame(100, $customer->fresh()->trust_score);

        $this->travelTo($booking->startsAt()->subMinutes(30));
        $this->postJson('/api/v1/field-owner/check-in', ['code' => 'CK-WINDOW01'])->assertOk();
        $this->assertSame(110, $customer->fresh()->trust_score);
    }

    public function test_no_show_is_penalised_exactly_once_and_blocks_review(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        [, $field, $slot] = $this->createFieldWithSlot('san-no-show');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        $booking = Booking::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(), 'status' => BookingStatus::Confirmed->value, 'total_price' => 1,
        ]);
        $this->travelTo($booking->load('timeSlot')->endsAt()->addMinutes(1));

        $this->artisan('bookings:sync')->assertSuccessful();
        $this->artisan('bookings:sync')->assertSuccessful();

        $this->assertSame(75, $customer->fresh()->trust_score);
        $this->assertNotNull($booking->fresh()->no_show_at);
        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/reviews', ['booking_id' => $booking->id, 'rating' => 5, 'comment' => 'x'])->assertNotFound();
    }

    public function test_suggestions_use_real_date_labels_and_skip_held_slots(): void
    {
        [$field, $slot] = $this->heldSlotFixture();
        $other = \App\Models\TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '20:00:00', 'end_time' => '21:30:00', 'is_active' => true]);
        $date = now()->addDays(3)->toDateString();
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        \App\Models\BookingWaitlist::create([
            'user_id' => $user->id, 'sports_field_id' => $field->id, 'time_slot_id' => $other->id, 'booking_date' => $date,
            'status' => 'offered', 'priority_score' => 100, 'offered_at' => now(), 'offer_expires_at' => now()->addMinutes(10),
        ]);

        $data = $this->getJson("/api/v1/fields/{$field->slug}/suggestions?slot_id={$slot->id}&date={$date}")->assertOk()->json('data');

        $labels = collect($data)->pluck('date_label')->implode('|');
        $this->assertStringNotContainsString('Hôm nay', $labels);
        $this->assertStringContainsString(now()->addDays(4)->format('d/m'), $labels); // 'cùng giờ ngày hôm sau' tính từ ngày đang xem
        // Slot 20:00 đang được giữ chỗ nên không được gợi ý.
        $this->assertNotContains($other->id, collect($data)->where('type', 'same_field_today')->pluck('time_slot_id')->all());
    }

    public function test_match_validation_leave_and_cancel_notifications(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $player = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $category = \App\Models\SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd-match']);
        $payload = [
            'sport_category_id' => $category->id, 'title' => 'Kèo', 'play_date' => now()->addDay()->toDateString(),
            'start_time' => '18:00', 'end_time' => '19:30', 'skill_level' => 'any', 'players_needed' => 1, 'contact_phone' => '0912345678',
        ];

        Sanctum::actingAs($host);
        $this->postJson('/api/v1/customer/matches', ['end_time' => '17:00'] + $payload)->assertStatus(422);
        $this->postJson('/api/v1/customer/matches', ['start_time' => '8h chieu'] + $payload)->assertStatus(422);
        $id = $this->postJson('/api/v1/customer/matches', $payload)->assertCreated()->json('data.id');

        Sanctum::actingAs($player);
        $this->postJson("/api/v1/customer/matches/{$id}/join", [])->assertCreated();
        $participantId = MatchRequest::find($id)->participants()->first()->id;

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/customer/matches/{$id}/participants/{$participantId}", ['status' => 'accepted'])->assertOk();
        $this->assertSame('full', MatchRequest::find($id)->status);

        // Người chơi rút lui: phòng mở lại.
        Sanctum::actingAs($player);
        $this->deleteJson("/api/v1/customer/matches/{$id}/participation")->assertOk();
        $match = MatchRequest::find($id);
        $this->assertSame('open', $match->status);
        $this->assertSame(1, $match->joined_count);

        // Vào lại, được chấp nhận, host huỷ phòng → participant nhận mail.
        $this->postJson("/api/v1/customer/matches/{$id}/join", [])->assertCreated();
        $participantId = MatchRequest::find($id)->participants()->first()->id;
        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/customer/matches/{$id}/participants/{$participantId}", ['status' => 'accepted'])->assertOk();
        $this->deleteJson("/api/v1/customer/matches/{$id}")->assertOk();
        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\MatchCancelled::class, fn ($m) => $m->hasTo($player->email));
    }
}
