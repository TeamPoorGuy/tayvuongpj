<?php

namespace Tests\Feature\Api;

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

    public function test_a_past_dated_booking_cannot_be_cancelled(): void
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

        $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel", [])
            ->assertStatus(409);

        $this->assertSame('pending', $booking->fresh()->status->value);
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
        ])->assertCreated()
            ->assertJsonPath('data.role', 'customer')
            ->assertJsonPath('data.field_owner_profile', null);

        $this->assertDatabaseHas('users', ['email' => 'newcustomer@example.test', 'role' => 'customer']);
        $this->assertDatabaseCount('field_owner_profiles', 0);
    }

    public function test_customer_can_submit_an_owner_application_with_a_license_file(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn A',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Thể Thao ABC',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000002',
            'business_license' => 'GPKD-001',
            'license_file' => UploadedFile::fake()->create('giay-phep.pdf', 100),
        ])->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        $this->assertDatabaseHas('field_owner_profiles', [
            'user_id' => $customer->id,
            'verification_status' => 'pending',
        ]);
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

        $this->postJson('/api/v1/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn A',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân X',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000003',
            'business_license' => 'GPKD-001',
        ])->assertStatus(409);
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

        Mail::assertSent(OwnerApplicationVerified::class, fn ($mail) => $mail->hasTo($customer->email)
            && $mail->profile->verification_status->value === 'rejected');

        Sanctum::actingAs($customer);
        $this->postJson('/api/v1/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn B',
            'owner_id_number' => '001099005678',
            'business_name' => 'Sân Y',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000004',
            'business_license' => 'GPKD-002',
        ])->assertCreated()->assertJsonPath('data.verification_status', 'pending');
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

        Mail::assertSent(OwnerApplicationVerified::class, fn ($mail) => $mail->hasTo($customer->email)
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
}
