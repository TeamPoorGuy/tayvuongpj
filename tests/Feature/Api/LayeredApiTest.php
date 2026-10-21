<?php

namespace Tests\Feature\Api;

use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
use App\Mail\OwnerApplicationVerified;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LayeredApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_returns_the_standard_data_envelope(): void
    {
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonStructure(['data' => ['categories', 'featured_fields']]);
    }

    public function test_protected_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/bookings')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Bạn cần đăng nhập để tiếp tục.');
    }

    public function test_role_middleware_rejects_a_customer_from_admin_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_inactive_account_is_rejected_by_protected_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => false]));

        $this->getJson('/api/v1/customer/bookings')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_session_login_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'api-customer@example.test',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'customer');

        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_can_create_a_booking_for_an_available_field_slot(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        [$field, $slot] = $this->createApprovedFieldWithSlot();

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(),
            'notes' => 'Kiểm thử API',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.field.id', $field->id);

        $this->assertDatabaseHas('bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'status' => 'pending',
        ]);
    }

    public function test_customer_cannot_use_a_time_slot_from_another_field(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        [$field] = $this->createApprovedFieldWithSlot('san-a');
        [, $otherSlot] = $this->createApprovedFieldWithSlot('san-b');

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $otherSlot->id,
            'booking_date' => now()->addDay()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_public_cannot_read_slots_of_an_unapproved_field(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-cho-duyet');
        $field->update(['status' => 'pending']);

        $this->getJson("/api/v1/fields/{$field->id}/available-slots?date=".now()->addDay()->toDateString())
            ->assertNotFound();
    }

    public function test_owner_cannot_update_another_owners_field(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-owner-khac');
        $otherOwner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $otherOwner->id,
            'business_name' => 'Sân khác',
            'business_address' => 'Đà Nẵng',
            'business_phone' => '0900000001',
            'verification_status' => 'approved',
        ]);
        Sanctum::actingAs($otherOwner);

        $this->putJson("/api/v1/field-owner/fields/{$field->id}", [
            'name' => 'Tên không được cập nhật',
            'field_type_id' => $field->field_type_id,
            'address' => 'Đà Nẵng',
            'price_per_hour' => 250000,
        ])->assertForbidden();

        $this->assertDatabaseHas('sports_fields', [
            'id' => $field->id,
            'name' => $field->name,
        ]);
    }

    public function test_admin_account_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$admin->id}/toggle")
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_double_booking_the_same_slot_is_rejected_with_conflict(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot();
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ])->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ])->assertStatus(409);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_slot_reported_as_free_by_availability_is_always_bookable(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot();
        $date = now()->addDay()->toDateString();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $available = $this->getJson("/api/v1/fields/{$field->id}/available-slots?date={$date}")
            ->assertOk()
            ->json('data.slots');

        $reportedFree = collect($available)->firstWhere('id', $slot->id)['is_booked'] === false;
        $this->assertTrue($reportedFree, 'Fixture slot should be reported as free before booking.');

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ])->assertCreated();
    }

    public function test_booking_price_is_calculated_from_the_slots_real_duration(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-2h', startTime: '08:00:00', endTime: '10:00:00');

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.total_price', (int) $field->price_per_hour * 2);
    }

    public function test_owner_cannot_apply_an_illegal_status_transition(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-transition');
        $owner = $field->owner;

        $booking = Booking::create([
            'user_id' => User::factory()->create(['role' => 'customer', 'is_active' => true])->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(),
            'status' => 'completed',
            'total_price' => $field->price_per_hour,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'pending',
        ])->assertStatus(409);

        $this->assertSame('completed', $booking->fresh()->status->value);
    }

    public function test_an_overdue_pending_request_can_still_be_cancelled_without_penalty(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-past');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->subDay()->toDateString(),
            'status' => 'pending',
            'total_price' => $field->price_per_hour,
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel", [])->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertSame(100, $customer->fresh()->trust_score);
    }

    public function test_register_always_creates_a_customer_without_an_owner_profile(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Người Dùng Mới',
            'email' => 'newcustomer@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '0987654321',
            'terms_accepted' => '1',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonPath('data.field_owner_profile', null);

        $this->assertDatabaseHas('users', ['email' => 'newcustomer@example.test', 'role' => 'customer']);
        $this->assertDatabaseCount('field_owner_profiles', 0);
    }

    public function test_customer_can_submit_an_owner_application_with_documents_and_terms(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload())
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonCount(3, 'data.documents');

        $profile = FieldOwnerProfile::where('user_id', $customer->id)->first();
        $this->assertSame(config('booking.owner_terms_version'), $profile->terms_version);
        $this->assertNotNull($profile->terms_accepted_at);
        $this->assertNotSame('001099001234', $profile->getRawOriginal('owner_id_number')); // mã hoá trong DB
        $this->assertSame('001099001234', $profile->owner_id_number);
    }

    public function test_owner_application_requires_terms_and_mandatory_documents(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload(['accept_terms' => false, 'documents' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['accept_terms', 'documents.id_front', 'documents.id_back']);
    }

    public function test_same_id_number_cannot_be_used_by_two_accounts(): void
    {
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload())->assertCreated();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload())->assertStatus(409);
    }

    public function test_customer_cannot_resubmit_a_pending_owner_application(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân X',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000003',
            'verification_status' => 'pending',
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload())->assertStatus(409);
    }

    public function test_unapproved_customer_is_rejected_from_owner_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->getJson('/api/v1/field-owner/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_reject_requires_a_reason_sends_email_and_allows_resubmission(): void
    {
        Mail::fake();
        Storage::fake('local');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân Y',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000004',
            'verification_status' => 'pending',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/field-owners/{$profile->id}/verify", ['status' => 'rejected'])
            ->assertStatus(422);

        $this->patchJson("/api/v1/admin/field-owners/{$profile->id}/verify", [
            'status' => 'rejected',
            'rejection_reason' => 'Thiếu giấy phép kinh doanh.',
        ])->assertOk()->assertJsonPath('data.verification_status', 'rejected');

        Mail::assertQueued(OwnerApplicationVerified::class, fn ($mail) => $mail->hasTo($customer->email)
            && $mail->profile->verification_status->value === 'rejected');

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/owner-application', $this->applicationPayload(['owner_id_number' => '001099005678']))
            ->assertCreated()->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_admin_approve_sends_confirmation_email(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân Z',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000006',
            'verification_status' => 'pending',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->patchJson("/api/v1/admin/field-owners/{$profile->id}/verify", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('data.verification_status', 'approved');

        Mail::assertQueued(OwnerApplicationVerified::class, fn ($mail) => $mail->hasTo($customer->email)
            && $mail->profile->verification_status->value === 'approved');
    }

    public function test_admin_cannot_verify_an_already_processed_application(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân W',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000007',
            'verification_status' => 'approved',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->patchJson("/api/v1/admin/field-owners/{$profile->id}/verify", ['status' => 'rejected', 'rejection_reason' => 'x'])
            ->assertStatus(409);
    }

    public function test_approved_owner_keeps_customer_access_too(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $user->id,
            'business_name' => 'Sân Dual',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000005',
            'verification_status' => 'approved',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/customer/bookings')->assertOk();
        $this->getJson('/api/v1/field-owner/dashboard')->assertOk();
    }


    private function applicationPayload(array $override = []): array
    {
        return array_merge([
            'owner_name' => 'Nguyễn Văn A',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Thể Thao ABC',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000002',
            'business_license' => 'GPKD-001',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '0123456789',
            'bank_account_holder' => 'NGUYEN VAN A',
            'accept_terms' => true,
            'documents' => [
                'id_front' => UploadedFile::fake()->image('front.jpg'),
                'id_back' => UploadedFile::fake()->image('back.jpg'),
                'business_license' => UploadedFile::fake()->create('gp.pdf', 100),
            ],
        ], $override);
    }

    /** @return array{SportsField, TimeSlot} */
    private function createApprovedFieldWithSlot(string $slug = 'san-api', string $startTime = '18:00:00', string $endTime = '19:30:00'): array
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => "Sân {$slug}",
            'business_address' => 'Đà Nẵng',
            'business_phone' => '0900000000',
            'verification_status' => 'approved',
        ]);
        $category = SportCategory::create([
            'name' => 'Bóng đá',
            'slug' => "bong-da-{$slug}",
            'is_active' => true,
        ]);
        $fieldType = FieldType::create([
            'sport_category_id' => $category->id,
            'name' => 'Sân 5 người',
            'slug' => "san-5-{$slug}",
            'is_active' => true,
        ]);
        $field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $fieldType->id,
            'name' => "Sân {$slug}",
            'slug' => $slug,
            'address' => 'Đà Nẵng',
            'price_per_hour' => 200000,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_active' => true,
        ]);

        return [$field, $slot];
    }
    public function test_public_field_detail_never_leaks_owner_private_data_or_hidden_reviews(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'A', 'owner_id_number' => '001099001234',
            'business_name' => 'Sân Y', 'business_address' => 'HN', 'business_phone' => '0900000009',
            'verification_status' => 'approved',
        ]);
        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 5', 'slug' => 'san-5']);
        $field = SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân Y', 'slug' => 'san-y',
            'address' => 'HN', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
        $viewer = User::factory()->create();
        $field->reviews()->create(['user_id' => $viewer->id, 'rating' => 1, 'comment' => 'HIDDEN-TEXT', 'is_visible' => false]);

        $body = $this->getJson('/api/v1/fields/san-y')->assertOk()->getContent();

        $this->assertStringNotContainsString('001099001234', $body);
        $this->assertStringNotContainsString('HIDDEN-TEXT', $body);
    }

    public function test_owner_documents_are_private_to_admin_and_the_applicant(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('licenses/x.pdf', 'pdf');
        $applicant = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $applicant->id, 'owner_name' => 'A', 'owner_id_number' => '001099001234',
            'business_name' => 'S', 'business_address' => 'HN', 'business_phone' => '0900000009',
            'verification_status' => 'pending',
        ]);
        $doc = $profile->documents()->create(['type' => 'business_license', 'path' => 'licenses/x.pdf', 'original_name' => 'x.pdf']);
        $adminUrl = "/api/v1/admin/field-owners/{$profile->id}/documents/{$doc->id}";

        $this->getJson($adminUrl)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->getJson($adminUrl)->assertForbidden();
        $this->getJson("/api/v1/customer/owner-application/documents/{$doc->id}")->assertNotFound(); // không phải của họ

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get($adminUrl)->assertOk();

        Sanctum::actingAs($applicant);
        $this->get("/api/v1/customer/owner-application/documents/{$doc->id}")->assertOk();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'bad'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->getJson('/api/v1/home')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
    private function bookingPayload(SportsField $field, TimeSlot $slot, string $date): array
    {
        return ['sports_field_id' => $field->id, 'time_slot_id' => $slot->id, 'booking_date' => $date];
    }

    public function test_booking_a_slot_that_already_started_today_is_rejected(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-qua-gio', '08:00:00', '09:30:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->postJson('/api/v1/customer/bookings', $this->bookingPayload($field, $slot, today()->toDateString()))->assertStatus(409);

        $slots = $this->getJson("/api/v1/fields/{$field->id}/available-slots?date=".today()->toDateString())->json('data.slots');
        $this->assertTrue($slots[0]['is_past']);
    }

    public function test_owner_cannot_book_their_own_field(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot();
        Sanctum::actingAs($field->owner);

        $this->postJson('/api/v1/customer/bookings', $this->bookingPayload($field, $slot, now()->addDay()->toDateString()))->assertForbidden();
    }

    public function test_confirmed_booking_cancellation_rules_and_penalty(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-huy', '18:00:00', '19:30:00');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        $make = fn () => Booking::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(), 'status' => 'confirmed', 'confirmed_at' => now(), 'total_price' => 1,
        ]);
        Sanctum::actingAs($customer);

        $within = $make(); // vừa duyệt, còn 6 giờ tới giờ chơi → huỷ được, không bị trừ điểm
        $this->patchJson("/api/v1/customer/bookings/{$within->id}/cancel")->assertOk();
        $this->assertSame(100, $customer->fresh()->trust_score);

        $late = $make();
        $this->travelTo(now()->addHours(2)->addMinute()); // 14:01: quá 2 giờ kể từ lúc duyệt và còn < 4 giờ tới giờ chơi → huỷ được nhưng bị trừ điểm (-15)
        $this->patchJson("/api/v1/customer/bookings/{$late->id}/cancel")->assertOk();
        $this->assertSame(85, $customer->fresh()->trust_score);

        $past = $make();
        $this->travelTo(today()->setTime(18, 5)); // Đã quá giờ bắt đầu trận đấu → không thể huỷ
        $this->patchJson("/api/v1/customer/bookings/{$past->id}/cancel")->assertStatus(409);
    }


    public function test_owner_completes_only_after_slot_ends_and_cancel_needs_reason(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        Mail::fake();
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-hoan-thanh', '18:00:00', '19:30:00');
        $booking = Booking::create([
            'user_id' => User::factory()->create(['role' => 'customer', 'is_active' => true])->id,
            'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(), 'status' => 'confirmed', 'total_price' => 1,
        ]);
        Sanctum::actingAs($field->owner);

        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'completed'])->assertStatus(409);
        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'cancelled'])->assertStatus(422);

        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'cancelled', 'reason' => 'Sân bảo trì'])
            ->assertOk()->assertJsonPath('data.cancel_reason', 'Sân bảo trì');
        Mail::assertQueued(BookingStatusChanged::class, fn ($m) => $m->hasTo($booking->customer->email));
    }

    public function test_new_booking_notifies_the_owner_by_queued_mail(): void
    {
        Mail::fake();
        [$field, $slot] = $this->createApprovedFieldWithSlot();
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $paymentId = $this->postJson('/api/v1/customer/bookings', $this->bookingPayload($field, $slot, now()->addDay()->toDateString()))
            ->assertCreated()->json('payment.id');

        // Chủ sân chỉ được báo khi tiền đã vào tài khoản nền tảng (đơn đã xác nhận).
        Mail::assertNotQueued(NewBookingRequest::class);
        app(\App\Services\PaymentService::class)->markPaid(\App\Models\Payment::find($paymentId), 'admin');
        Mail::assertQueued(NewBookingRequest::class, fn ($m) => $m->hasTo($field->owner->email));
    }

    public function test_sync_command_expires_pending_and_completes_confirmed_bookings(): void
    {
        Mail::fake();
        $this->travelTo(now()->setTime(22, 0));
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-sync', '18:00:00', '19:30:00');
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $mk = fn (string $status) => Booking::create([
            'user_id' => $user->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->toDateString(), 'status' => $status, 'total_price' => 1,
        ]);
        $pending = $mk('pending');
        $confirmed = $mk('confirmed');

        $this->artisan('bookings:sync')->assertSuccessful();

        $this->assertSame('rejected', $pending->fresh()->status->value);
        $this->assertSame('completed', $confirmed->fresh()->status->value);
    }

    public function test_owner_manages_time_slots_without_overlap_and_keeps_booking_history(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-slot', '18:00:00', '19:30:00');
        Sanctum::actingAs($field->owner);

        $this->postJson("/api/v1/field-owner/fields/{$field->id}/slots", ['start_time' => '19:00', 'end_time' => '20:00'])->assertStatus(409);
        $created = $this->postJson("/api/v1/field-owner/fields/{$field->id}/slots", ['start_time' => '19:30', 'end_time' => '21:00', 'price_per_hour' => 300000])
            ->assertCreated()->json('data');
        $this->postJson("/api/v1/field-owner/fields/{$field->id}/slots", ['start_time' => '21:00', 'end_time' => '20:00'])->assertStatus(422);

        // Slot có đơn trong quá khứ: xoá → chỉ ngưng hoạt động, không mất lịch sử.
        Booking::create([
            'user_id' => User::factory()->create()->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => now()->subDays(3)->toDateString(), 'status' => 'completed', 'total_price' => 1,
        ]);
        $this->deleteJson("/api/v1/field-owner/fields/{$field->id}/slots/{$slot->id}")->assertOk();
        $this->assertFalse($slot->fresh()->is_active);
        $this->deleteJson("/api/v1/field-owner/fields/{$field->id}/slots/{$created['id']}")->assertOk();
        $this->assertDatabaseMissing('time_slots', ['id' => $created['id']]);
    }

    public function test_slot_price_override_is_used_for_booking_total(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-peak', '18:00:00', '19:00:00');
        $slot->update(['price_per_hour' => 500000]);
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->postJson('/api/v1/customer/bookings', $this->bookingPayload($field, $slot, now()->addDay()->toDateString()))
            ->assertCreated()->assertJsonPath('data.total_price', 500000);
    }

    public function test_field_delete_is_soft_and_blocked_by_upcoming_bookings(): void
    {
        [$field, $slot] = $this->createApprovedFieldWithSlot('san-xoa');
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(), 'status' => 'confirmed', 'total_price' => 1,
        ]);
        Sanctum::actingAs($field->owner);

        $this->deleteJson("/api/v1/field-owner/fields/{$field->id}")->assertStatus(409);

        $booking->update(['status' => 'cancelled']);
        $this->deleteJson("/api/v1/field-owner/fields/{$field->id}")->assertOk();
        $this->assertSoftDeleted('sports_fields', ['id' => $field->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]); // lịch sử còn nguyên
    }

    public function test_editing_an_approved_field_name_requires_reapproval_but_price_does_not(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-sua');
        Sanctum::actingAs($field->owner);
        $payload = ['name' => $field->name, 'field_type_id' => $field->field_type_id, 'address' => $field->address, 'price_per_hour' => 250000];

        $this->putJson("/api/v1/field-owner/fields/{$field->id}", $payload)->assertOk();
        $this->assertSame('approved', $field->fresh()->status->value);

        $this->putJson("/api/v1/field-owner/fields/{$field->id}", ['name' => 'Tên mới'] + $payload)->assertOk();
        $this->assertSame('pending', $field->fresh()->status->value);
    }

    public function test_admin_can_suspend_owner_which_hides_fields_and_blocks_owner_area(): void
    {
        Mail::fake();
        [$field] = $this->createApprovedFieldWithSlot('san-suspend');
        $profile = $field->owner->fieldOwnerProfile;
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $url = "/api/v1/admin/field-owners/{$profile->id}/verify";

        $this->patchJson($url, ['status' => 'suspended'])->assertStatus(422); // cần lý do
        $this->patchJson($url, ['status' => 'suspended', 'rejection_reason' => 'Vi phạm điều khoản'])->assertOk();

        $this->getJson("/api/v1/fields/{$field->slug}")->assertNotFound();
        Sanctum::actingAs($field->owner->fresh());
        $this->getJson('/api/v1/field-owner/dashboard')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->patchJson($url, ['status' => 'approved'])->assertOk(); // khôi phục
        $this->getJson("/api/v1/fields/{$field->slug}")->assertOk();
    }

    public function test_admin_rejecting_a_field_requires_a_reason_and_owner_can_see_it(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-tu-choi');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->patchJson("/api/v1/admin/fields/{$field->id}/verify", ['status' => 'rejected'])->assertStatus(422);
        $this->patchJson("/api/v1/admin/fields/{$field->id}/verify", ['status' => 'rejected', 'rejection_reason' => 'Ảnh không rõ'])
            ->assertOk()->assertJsonPath('data.rejection_reason', 'Ảnh không rõ');
    }

    public function test_admin_can_update_category_and_reject_duplicate_field_type_names(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-dm');
        $type = $field->fieldType;
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->putJson("/api/v1/admin/categories/{$type->sport_category_id}", ['name' => 'Bóng đá mini', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->putJson("/api/v1/admin/field-types/{$type->id}", ['is_active' => false])->assertOk();
        $this->postJson('/api/v1/admin/field-types', ['sport_category_id' => $type->sport_category_id, 'name' => $type->name])
            ->assertStatus(422);
    }
}
