<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\TrustScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Hồi quy cho các lỗi tìm thấy khi rà soát hệ thống. */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function approvedOwner(string $email = 'owner@example.test'): User
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email' => $email, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ Sân', 'owner_id_number' => '0010990012'.random_int(10, 99),
            'business_name' => 'CLB', 'business_address' => 'Hà Nội', 'business_phone' => '0900000009',
            'business_license' => 'GPKD-1', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);

        return $owner;
    }

    private function fieldFor(User $owner, string $slug = 'san-audit'): array
    {
        $cat = SportCategory::firstOrCreate(['slug' => 'bong-da'], ['name' => 'Bóng đá']);
        $type = FieldType::firstOrCreate(['slug' => 'san-7'], ['sport_category_id' => $cat->id, 'name' => 'Sân 7']);
        $field = SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân Audit', 'slug' => $slug,
            'address' => 'Cầu Giấy', 'price_per_hour' => 200000, 'status' => 'approved', 'is_active' => true,
        ]);
        $slot = TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '17:30', 'end_time' => '19:00', 'is_active' => true]);

        return [$field, $slot, $cat];
    }

    public function test_expired_offer_is_persisted_as_expired_when_accept_fails(): void
    {
        [$field, $slot] = $this->fieldFor($this->approvedOwner());
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $waitlist = BookingWaitlist::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->addDays(2), 'status' => 'offered', 'priority_score' => 100,
            'offered_at' => now()->subMinutes(30), 'offer_expires_at' => now()->subMinutes(15),
        ]);

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/customer/waitlist/{$waitlist->id}/accept")->assertStatus(409);

        $this->assertSame('expired', $waitlist->fresh()->status);
        $this->assertSame(0, Booking::count());
    }

    public function test_host_cannot_accept_more_players_than_the_room_holds(): void
    {
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $guest = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd']);
        $match = MatchRequest::create([
            'user_id' => $host->id, 'sport_category_id' => $cat->id, 'title' => 'Kèo đủ người', 'play_date' => today()->addDay(),
            'start_time' => '18:00', 'end_time' => '19:30', 'skill_level' => 'any', 'players_needed' => 1,
            'joined_count' => 2, 'contact_phone' => '0933333333', 'status' => 'full',
        ]);
        $participant = $match->participants()->create(['user_id' => $guest->id, 'status' => 'pending']);

        Sanctum::actingAs($host);
        $this->patchJson("/api/v1/customer/matches/{$match->id}/participants/{$participant->id}", ['status' => 'accepted'])
            ->assertStatus(409);

        $this->assertSame(2, $match->fresh()->joined_count);
        $this->assertSame('pending', $participant->fresh()->status);
    }

    public function test_cancelling_a_match_twice_is_rejected(): void
    {
        $host = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd']);
        $match = MatchRequest::create([
            'user_id' => $host->id, 'sport_category_id' => $cat->id, 'title' => 'Kèo huỷ', 'play_date' => today()->addDay(),
            'start_time' => '18:00', 'end_time' => '19:30', 'skill_level' => 'any', 'players_needed' => 2,
            'joined_count' => 1, 'contact_phone' => '0933333333', 'status' => 'open',
        ]);

        Sanctum::actingAs($host);
        $this->deleteJson("/api/v1/customer/matches/{$match->id}")->assertOk();
        $this->deleteJson("/api/v1/customer/matches/{$match->id}")->assertStatus(409);
    }

    public function test_admin_user_list_filters_and_labels_field_owners(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $owner = $this->approvedOwner('chu@example.test');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email' => 'khach@example.test']);

        Sanctum::actingAs($admin);
        $owners = $this->getJson('/api/v1/admin/users?role=field_owner')->assertOk();
        $this->assertSame([$owner->id], collect($owners->json('data'))->pluck('id')->all());
        $owners->assertJsonPath('data.0.is_field_owner', true);

        $customers = collect($this->getJson('/api/v1/admin/users?role=customer')->assertOk()->json('data'));
        $this->assertSame([$customer->id], $customers->pluck('id')->all());
        $this->assertFalse($customers->first()['is_field_owner']);
    }

    public function test_owner_can_save_field_coordinates(): void
    {
        $owner = $this->approvedOwner();
        $this->fieldFor($owner, 'san-co-san');
        $type = FieldType::first();

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/field-owner/fields', [
            'name' => 'Sân Có Toạ Độ', 'field_type_id' => $type->id, 'address' => 'Hà Nội', 'price_per_hour' => 150000,
            'latitude' => 21.0285, 'longitude' => 105.8542,
        ])->assertCreated()->json('data.id');

        $this->assertEquals(21.0285, (float) SportsField::find($id)->latitude);

        // Sửa không gửi toạ độ thì giữ nguyên; chỉ gửi một nửa thì lỗi validate.
        $this->putJson("/api/v1/field-owner/fields/{$id}", [
            'name' => 'Sân Có Toạ Độ', 'field_type_id' => $type->id, 'address' => 'Hà Nội', 'price_per_hour' => 160000,
        ])->assertOk();
        $this->assertEquals(105.8542, (float) SportsField::find($id)->longitude);

        $this->putJson("/api/v1/field-owner/fields/{$id}", [
            'name' => 'Sân Có Toạ Độ', 'field_type_id' => $type->id, 'address' => 'Hà Nội', 'price_per_hour' => 160000,
            'latitude' => 21.0,
        ])->assertStatus(422);
    }

    public function test_field_is_hidden_when_owner_account_is_deactivated(): void
    {
        $owner = $this->approvedOwner();
        $this->fieldFor($owner, 'san-an');
        $this->getJson('/api/v1/fields/san-an')->assertOk();

        $owner->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/fields/san-an')->assertNotFound();
    }

    public function test_trust_score_is_clamped_and_logs_actual_change(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'trust_score' => 145]);
        TrustScoreService::rewardCheckIn($user);
        $this->assertSame(150, $user->fresh()->trust_score);
        $this->assertSame(5, $user->trustScoreLogs()->first()->change);

        $low = User::factory()->create(['role' => 'customer', 'trust_score' => 10]);
        TrustScoreService::penalizeNoShow($low);
        $this->assertSame(0, $low->fresh()->trust_score);
        $this->assertSame(-10, $low->trustScoreLogs()->first()->change);
    }
}
