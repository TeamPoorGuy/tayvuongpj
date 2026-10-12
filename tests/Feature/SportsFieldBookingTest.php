<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class SportsFieldBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_customer_can_view_fields_list(): void
    {
        $response = $this->get('/fields');
        $response->assertStatus(200);
    }

    public function test_customer_can_access_login_page(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_customer_can_access_register_page(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_from_customer_area_to_login(): void
    {
        $this->get('/customer/bookings')->assertRedirect('/login');
    }

    public function test_customer_cannot_access_admin_area(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->actingAs($customer)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_unapproved_customer_is_redirected_to_owner_application(): void
    {
        $owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Sân đang chờ duyệt',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000000',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->get('/field-owner/dashboard')
            ->assertRedirect('/customer/owner-application');
    }

    public function test_approved_field_owner_can_access_dashboard(): void
    {
        $owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Sân đã duyệt',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000001',
            'verification_status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->get('/field-owner/dashboard')
            ->assertOk();

        $this->get('/customer/bookings')->assertOk();
    }

    public function test_inactive_account_is_logged_out_from_protected_routes(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => false]);

        $this->actingAs($user)
            ->get('/customer/bookings')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_session_login_authenticates_the_customer(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_creates_a_customer_without_an_owner_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Khách hàng mới',
            'email' => 'customer-new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0901234567',
            'address' => 'Hà Nội',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'customer-new@example.com',
            'role' => 'customer',
        ]);
        $this->assertDatabaseCount('field_owner_profiles', 0);
    }

    public function test_customer_can_submit_an_owner_application(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $response = $this->actingAs($customer)->post('/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn A',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Thể Thao A',
            'business_address' => 'Hà Nội',
            'business_phone' => '0901234567',
            'business_license' => 'GPKD-001',
            'description' => 'Cơ sở thể thao mới.',
            'license_file' => UploadedFile::fake()->image('license.jpg'),
        ]);

        $response->assertRedirect('/customer/owner-application');
        $this->assertDatabaseHas('field_owner_profiles', [
            'user_id' => $customer->id,
            'owner_id_number' => '001099001234',
            'verification_status' => 'pending',
        ]);
    }

    public function test_customer_cannot_resubmit_a_pending_owner_application(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân đang chờ',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000002',
            'verification_status' => 'pending',
        ]);

        $this->actingAs($customer)->post('/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn B',
            'owner_id_number' => '001099001235',
            'business_name' => 'Sân gửi lại',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000002',
            'business_license' => 'GPKD-002',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('field_owner_profiles', 1);
    }

    public function test_customer_cannot_book_a_time_slot_from_another_field(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$firstField, $secondField] = $this->createTwoApprovedFields();
        $foreignSlot = TimeSlot::create([
            'sports_field_id' => $secondField->id,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer)->post('/customer/bookings', [
            'sports_field_id' => $firstField->id,
            'time_slot_id' => $foreignSlot->id,
            'booking_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_price_uses_the_real_time_slot_duration(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        [$field] = $this->createTwoApprovedFields();
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '08:00',
            'end_time' => '10:00',
            'is_active' => true,
        ]);

        $this->actingAs($customer)->post('/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(),
        ])->assertRedirect('/customer/bookings');

        $this->assertDatabaseHas('bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'total_price' => 300000,
        ]);
    }

    public function test_double_booking_is_rejected(): void
    {
        [$field] = $this->createTwoApprovedFields();
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '10:00',
            'end_time' => '11:30',
            'is_active' => true,
        ]);
        $date = now()->addDay()->toDateString();
        $payload = [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => $date,
        ];

        $this->actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]))
            ->post('/customer/bookings', $payload)
            ->assertRedirect('/customer/bookings');

        $this->actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]))
            ->post('/customer/bookings', $payload)
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_past_booking_cannot_be_cancelled(): void
    {
        [$field] = $this->createTwoApprovedFields();
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '12:00',
            'end_time' => '13:30',
            'is_active' => true,
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->subDay(),
            'status' => 'pending',
            'total_price' => 150000,
        ]);

        $this->actingAs($customer)
            ->post('/customer/bookings/'.$booking->id.'/cancel')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'pending']);
    }

    public function test_admin_cannot_disable_another_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $target = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post('/admin/users/'.$target->id.'/toggle')
            ->assertSessionHas('error');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_approval_sends_owner_confirmation_email(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân cần duyệt',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000004',
            'verification_status' => 'pending',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post('/admin/field-owners/'.$profile->id.'/verify', ['status' => 'approved'])
            ->assertSessionHas('success');

        $this->assertSame('approved', $profile->fresh()->verification_status);
        Mail::assertSent(OwnerApplicationVerified::class, fn ($mail) => $mail->hasTo($customer->email));
    }

    public function test_owner_cannot_update_another_owners_field(): void
    {
        [$field] = $this->createTwoApprovedFields();
        $otherOwner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $otherOwner->id,
            'business_name' => 'Chủ sân khác',
            'business_address' => 'Đà Nẵng',
            'business_phone' => '0900000008',
            'verification_status' => 'approved',
        ]);

        $this->actingAs($otherOwner)->put('/field-owner/fields/'.$field->id, [
            'name' => 'Tên bị sửa trái phép',
            'field_type_id' => $field->field_type_id,
            'address' => 'Đà Nẵng',
            'price_per_hour' => 250000,
        ])->assertSessionHas('error');

        $this->assertSame('Sân A', $field->fresh()->name);
    }

    public function test_owner_cannot_apply_an_illegal_booking_transition(): void
    {
        [$field] = $this->createTwoApprovedFields();
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '14:00',
            'end_time' => '15:30',
            'is_active' => true,
        ]);
        $booking = Booking::create([
            'user_id' => User::factory()->create(['role' => 'customer', 'is_active' => true])->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay(),
            'status' => 'completed',
            'total_price' => 150000,
        ]);

        $this->actingAs($field->owner)
            ->put('/field-owner/bookings/'.$booking->id, ['status' => 'confirmed'])
            ->assertSessionHas('error');

        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_available_slots_are_hidden_for_an_unapproved_field(): void
    {
        [$field] = $this->createTwoApprovedFields();
        $field->update(['status' => 'pending']);

        $this->get('/ajax/fields/'.$field->id.'/check-slots?date='.now()->addDay()->toDateString())
            ->assertNotFound();
    }

    /** @return array{SportsField, SportsField} */
    private function createTwoApprovedFields(): array
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Chủ sân fixture',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000099',
            'verification_status' => 'approved',
        ]);
        $category = SportCategory::create([
            'name' => 'Bóng đá',
            'slug' => 'bong-da',
            'is_active' => true,
        ]);
        $type = FieldType::create([
            'sport_category_id' => $category->id,
            'name' => 'Sân 5 người',
            'slug' => 'san-5-nguoi',
            'is_active' => true,
        ]);

        $base = [
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'address' => 'Hà Nội',
            'price_per_hour' => 150000,
            'status' => 'approved',
            'is_active' => true,
        ];

        return [
            SportsField::create($base + ['name' => 'Sân A', 'slug' => 'san-a']),
            SportsField::create($base + ['name' => 'Sân B', 'slug' => 'san-b']),
        ];
    }
}
