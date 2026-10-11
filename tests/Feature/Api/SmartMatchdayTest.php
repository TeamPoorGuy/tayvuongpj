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
}
