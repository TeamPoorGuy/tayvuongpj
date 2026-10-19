<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\Review;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Hồi quy: upload hồ sơ chủ sân và các quy tắc nghiệp vụ về thời gian/hiển thị. */
class UploadAndRulesTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(string $role = 'customer'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function approvedOwner(): User
    {
        $owner = $this->verifiedUser();
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ Sân', 'owner_id_number' => '001099001234',
            'business_name' => 'CLB', 'business_address' => 'Hà Nội', 'business_phone' => '0900000009',
            'business_license' => 'GPKD-1', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);

        return $owner;
    }

    /** @return array{0: SportsField, 1: TimeSlot} */
    private function fieldWithSlot(User $owner, string $start = '17:30', string $end = '19:00'): array
    {
        $cat = SportCategory::firstOrCreate(['slug' => 'bong-da'], ['name' => 'Bóng đá']);
        $type = FieldType::firstOrCreate(['slug' => 'san-7'], ['sport_category_id' => $cat->id, 'name' => 'Sân 7']);
        $field = SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân Test', 'slug' => 'san-test',
            'address' => 'Cầu Giấy', 'price_per_hour' => 200000, 'status' => 'approved', 'is_active' => true,
        ]);
        $slot = TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => $start, 'end_time' => $end, 'is_active' => true]);

        return [$field, $slot];
    }

    private function applicationFields(): array
    {
        return [
            'owner_name' => 'Nguyễn Văn A', 'owner_id_number' => '012345678901', 'business_name' => 'CLB Test',
            'business_address' => '1 Đường A', 'business_phone' => '0901234567', 'business_license' => 'GP123',
            'bank_name' => 'VCB', 'bank_account_number' => '123456789', 'bank_account_holder' => 'NGUYEN VAN A',
            'accept_terms' => '1',
        ];
    }

    public function test_owner_application_accepts_jpg_png_webp_and_pdf_documents(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->verifiedUser());

        $this->post('/api/v1/customer/owner-application', $this->applicationFields() + ['documents' => [
            'id_front' => UploadedFile::fake()->image('front.webp', 800, 600)->size(3000),
            'id_back' => UploadedFile::fake()->image('back.png', 800, 600)->size(2500),
            'business_license' => UploadedFile::fake()->create('gpkd.pdf', 1200, 'application/pdf'),
        ]], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_owner_application_reports_unsupported_and_oversized_files_in_vietnamese(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->verifiedUser());

        $response = $this->post('/api/v1/customer/owner-application', $this->applicationFields() + ['documents' => [
            'id_front' => UploadedFile::fake()->image('front.gif'),
            'id_back' => UploadedFile::fake()->image('back.png')->size(6000),
            'business_license' => UploadedFile::fake()->image('gpkd.jpg'),
        ]], ['Accept' => 'application/json'])->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertStringContainsString('định dạng', $errors['documents.id_front'][0]);
        $this->assertStringContainsString('KB', $errors['documents.id_back'][0]);
    }

    public function test_php_upload_failure_has_a_vietnamese_message(): void
    {
        Sanctum::actingAs($this->verifiedUser());
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        $broken = new UploadedFile($tmp, 'front.png', 'image/png', UPLOAD_ERR_INI_SIZE, true);

        $response = $this->post('/api/v1/customer/owner-application', $this->applicationFields() + ['documents' => [
            'id_front' => $broken,
            'id_back' => UploadedFile::fake()->image('back.png'),
            'business_license' => UploadedFile::fake()->image('gpkd.jpg'),
        ]], ['Accept' => 'application/json'])->assertStatus(422);

        $message = $response->json('errors')['documents.id_front'][0];
        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('giới hạn', $message);
    }

    public function test_cannot_join_a_match_that_has_already_ended(): void
    {
        $host = $this->verifiedUser();
        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd']);
        $match = MatchRequest::create([
            'user_id' => $host->id, 'sport_category_id' => $cat->id, 'title' => 'Kèo đã xong', 'play_date' => today()->subDay(),
            'start_time' => '00:00', 'end_time' => '00:30', 'skill_level' => 'any', 'players_needed' => 3,
            'joined_count' => 1, 'contact_phone' => '0933333333', 'status' => 'open',
        ]);


        Sanctum::actingAs($this->verifiedUser());
        $this->postJson("/api/v1/customer/matches/{$match->id}/join")->assertStatus(409);

        $this->assertSame(0, $match->participants()->count());
    }

    public function test_waitlist_offer_cannot_be_accepted_after_field_is_rejected(): void
    {
        [$field, $slot] = $this->fieldWithSlot($this->approvedOwner());
        $customer = $this->verifiedUser();
        $waitlist = BookingWaitlist::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->addDays(2), 'status' => 'offered', 'priority_score' => 100,
            'offered_at' => now(), 'offer_expires_at' => now()->addMinutes(10),
        ]);
        $field->update(['status' => 'rejected']);

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/customer/waitlist/{$waitlist->id}/accept")->assertStatus(409);

        $this->assertSame('expired', $waitlist->fresh()->status);
        $this->assertSame(0, Booking::count());
    }

    public function test_owner_reviews_list_excludes_reviews_hidden_by_admin(): void
    {
        $owner = $this->approvedOwner();
        [$field, $slot] = $this->fieldWithSlot($owner);
        $customer = $this->verifiedUser();
        foreach ([true, false] as $i => $visible) {
            $booking = Booking::create([
                'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
                'booking_date' => today()->subDays($i + 1), 'status' => 'completed', 'total_price' => 100000,
            ]);
            Review::create([
                'user_id' => $customer->id, 'sports_field_id' => $field->id, 'booking_id' => $booking->id,
                'rating' => 5, 'comment' => $visible ? 'Hiện' : 'Ẩn', 'is_visible' => $visible,
            ]);
        }

        Sanctum::actingAs($owner);
        $comments = collect($this->getJson('/api/v1/field-owner/reviews')->assertOk()->json('data'))->pluck('comment');

        $this->assertSame(['Hiện'], $comments->all());
    }

    public function test_unverified_user_can_book_when_email_verification_is_not_required(): void
    {
        config(['auth.require_email_verification' => false]);
        [$field, $slot] = $this->fieldWithSlot($this->approvedOwner());
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => null]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email_verified', true);
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->addDay()->toDateString(),
        ])->assertCreated();
    }

    public function test_unverified_user_is_blocked_when_email_verification_is_required(): void
    {
        config(['auth.require_email_verification' => true]);
        [$field, $slot] = $this->fieldWithSlot($this->approvedOwner());
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => null]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->addDay()->toDateString(),
        ])->assertStatus(403)->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }
}
