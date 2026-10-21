<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseThreePolicyAndComplianceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $customer;
    private SportsField $field;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'trust_score' => 100,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $this->owner->id,
            'business_name' => 'Sân Bóng Demo',
            'business_address' => '123 Cầu Giấy, Hà Nội',
            'business_phone' => '0901234567',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'CHU SAN DEMO',
            'verification_status' => 'approved',
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'trust_score' => 100,
        ]);

        $category = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da-p3']);
        $type = FieldType::create(['sport_category_id' => $category->id, 'name' => 'Sân 7', 'slug' => 'san-7-p3']);

        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Bóng Test P3',
            'slug' => 'san-bong-test-p3',
            'address' => '123 Cầu Giấy',
            'price_per_hour' => 200000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'price_per_hour' => 200000,
            'is_active' => true,
        ]);
    }

    /** 1. Đăng ký tài khoản lưu terms_accepted_at */
    public function test_user_registration_saves_terms_accepted_at(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Nguyễn Văn Minh Bạch',
            'email' => 'minhbach@example.com',
            'phone' => '0912345678',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms_accepted' => '1',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'minhbach@example.com');
        $this->assertNotNull($response->json('data.terms_accepted_at'));

        $user = User::where('email', 'minhbach@example.com')->first();
        $this->assertNotNull($user->terms_accepted_at);
    }

    /** 2. Đăng ký từ chối điều khoản thì validation fail */
    public function test_user_registration_fails_if_terms_accepted_is_false(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Khách Không Đồng Ý',
            'email' => 'tuchoi@example.com',
            'phone' => '0912345679',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms_accepted' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terms_accepted']);
    }

    /** 2b. Đăng ký thiếu trường terms_accepted thì validation fail */
    public function test_user_registration_fails_if_terms_accepted_is_missing(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Khách Quên Tích',
            'email' => 'missing@example.com',
            'phone' => '0912345670',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['terms_accepted']);
    }

    /** 3. API /settings/public trả đầy đủ các trường cấu hình phục vụ chính sách */
    public function test_public_settings_endpoint_returns_expected_keys(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'commission_percent',
                'statement_due_days',
                'deposit_percent',
                'payment_hold_minutes',
                'free_cancel_hours',
                'max_pending_bookings',
                'max_advance_days',
                'platform_bank_name',
                'platform_bank_account',
                'platform_bank_holder',
                'support_email',
                'support_hotline',
                'owner_terms_version',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(Settings::getInt('commission_percent', 8), $data['commission_percent']);
        $this->assertEquals(Settings::getInt('deposit_percent', 30), $data['deposit_percent']);
        $this->assertEquals(Settings::getInt('free_cancel_hours', 4), $data['free_cancel_hours']);
    }

    /** 4. Check-in bị chặn nếu đơn chưa hoàn tất thanh toán */
    public function test_check_in_blocked_if_booking_payment_is_unpaid(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 400000,
            'check_in_code' => 'CK-UNPAID01',
            'payment_status' => 'awaiting', // Chưa trả tiền
        ]);

        $this->travelTo($booking->load('timeSlot')->startsAt()->subMinutes(15));

        Sanctum::actingAs($this->owner);

        // Check in bằng mã
        $response = $this->postJson('/api/v1/field-owner/check-in', [
            'code' => 'CK-UNPAID01',
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Đơn chưa hoàn tất thanh toán, không thể check-in.');

        // Check in bằng direct patch
        $patchResponse = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/check-in");
        $patchResponse->assertStatus(422);
        $patchResponse->assertJsonPath('message', 'Đơn chưa hoàn tất thanh toán, không thể check-in.');
    }

    /** 5. Check-in thành công khi đơn đã xác nhận và đã thanh toán, tích +10 điểm uy tín */
    public function test_check_in_succeeds_when_booking_is_paid_and_confirmed(): void
    {
        $initialScore = $this->customer->trust_score;

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 400000,
            'check_in_code' => 'CK-PAID0001',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->travelTo($booking->load('timeSlot')->startsAt()->subMinutes(15));

        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/field-owner/check-in', [
            'code' => 'CK-PAID0001',
        ]);

        $response->assertOk();
        $booking->refresh();
        $this->assertNotNull($booking->checked_in_at);
        $this->assertEquals($this->owner->id, $booking->checked_in_by);

        // Khách hàng nhận được +10 điểm tin cậy
        $this->assertEquals($initialScore + 10, $this->customer->fresh()->trust_score);
    }

    /** 6. Chủ sân check-in trực tiếp qua route patch /bookings/{id}/check-in */
    public function test_check_in_via_booking_patch_endpoint(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 400000,
            'check_in_code' => 'CK-PATCH001',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->travelTo($booking->load('timeSlot')->startsAt()->subMinutes(20));

        Sanctum::actingAs($this->owner);

        $response = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/check-in");
        $response->assertOk();

        $booking->refresh();
        $this->assertNotNull($booking->checked_in_at);
    }

    /** 7. Đơn chưa check-in không thể hoàn thành thủ công */
    public function test_booking_cannot_complete_without_check_in(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 400000,
            'check_in_code' => 'CK-NOCHECK1',
            'payment_status' => 'paid',
        ]);

        // Đã qua giờ kết thúc
        $this->travelTo($booking->load('timeSlot')->endsAt()->addMinutes(10));

        Sanctum::actingAs($this->owner);

        $response = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Chỉ có thể hoàn thành đơn sau khi khách đã check-in.');
    }

    /** 8. Lệnh syncStatuses tự động đánh dấu no-show và trừ điểm cho đơn đã xác nhận nhưng không check-in */
    public function test_sync_statuses_penalizes_un_checked_in_past_booking_as_no_show(): void
    {
        $initialScore = $this->customer->trust_score;

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 400000,
            'check_in_code' => 'CK-NOSHOW01',
            'payment_status' => 'paid',
        ]);

        // Trận đấu đã kết thúc
        $this->travelTo($booking->load('timeSlot')->endsAt()->addMinutes(15));

        $result = app(BookingService::class)->syncStatuses();

        $this->assertGreaterThanOrEqual(1, $result['completed']);

        $booking->refresh();
        $this->assertEquals(BookingStatus::Completed, $booking->status);
        $this->assertNotNull($booking->no_show_at);

        // Bị trừ 25 điểm no-show
        $this->assertEquals($initialScore - 25, $this->customer->fresh()->trust_score);
    }
}
