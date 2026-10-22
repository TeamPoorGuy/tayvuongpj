<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\BookingPaymentService;
use App\Services\StatementService;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseTwoCommissionAndStatementsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherOwner;
    private User $customer;
    private User $admin;
    private SportsField $field;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo chủ sân chính
        $this->owner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'trust_score' => 100,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $this->owner->id,
            'business_name' => 'Sân Bóng Nam Hải',
            'business_address' => '100 Nguyễn Huệ, Q1',
            'business_phone' => '0901234567',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'NGUYEN CHU SAN',
            'verification_status' => 'approved',
        ]);

        // 2. Tạo chủ sân thứ hai
        $this->otherOwner = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $this->otherOwner->id,
            'business_name' => 'Sân Bóng Bắc Hải',
            'business_address' => '200 Lê Lợi, Q1',
            'business_phone' => '0909999999',
            'bank_name' => 'Techcombank',
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'CHU SAN KHAC',
            'verification_status' => 'approved',
        ]);

        // 3. Khách hàng & Admin
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // 4. Sân & Khung giờ
        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da-p2']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 7', 'slug' => 'san-7-p2']);

        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Đẹp Quận 1',
            'slug' => 'san-dep-q1',
            'address' => '100 Nguyễn Huệ, Q1',
            'price_per_hour' => 300000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'is_active' => true,
        ]);
    }

    public function test_booking_calculates_commission_base_and_amount_correctly(): void
    {
        // Rate mặc định 8%
        Settings::set('commission_percent', 8);

        // Đơn 1: Completed với check-in -> hoa hồng tính trên total_price (300.000 * 8% = 24.000)
        $completedBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-10',
            'status' => BookingStatus::Completed->value,
            'total_price' => 300000,
            'payment_status' => 'paid',
            'payment_amount' => 300000,
            'checked_in_at' => '2026-09-10 18:05:00',
        ]);

        $this->assertEquals(300000, $completedBooking->commissionBase());
        $this->assertEquals(24000.0, $completedBooking->calculateCommission());

        // Đơn 2: Khách huỷ muộn bị phạt cọc -> hoa hồng tính trên tiền cọc chủ sân được giữ (90.000 * 8% = 7.200)
        $lateCancelledBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-12',
            'status' => BookingStatus::Cancelled->value,
            'total_price' => 300000,
            'payment_status' => 'paid',
            'payment_amount' => 90000,
            'cancelled_by' => 'customer',
            'cancelled_at' => '2026-09-12 17:00:00',
            'refund_status' => 'waived', // phạt cọc
        ]);

        $this->assertEquals(90000, $lateCancelledBooking->commissionBase());
        $this->assertEquals(7200.0, $lateCancelledBooking->calculateCommission());

        // Đơn 3: Khách huỷ đúng hạn (hoàn tiền) -> hoa hồng = 0
        $freeCancelledBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-15',
            'status' => BookingStatus::Cancelled->value,
            'total_price' => 300000,
            'payment_status' => 'paid',
            'payment_amount' => 90000,
            'cancelled_by' => 'customer',
            'cancelled_at' => '2026-09-14 10:00:00',
            'refund_status' => 'pending', // được hoàn
        ]);

        $this->assertEquals(0.0, $freeCancelledBooking->commissionBase());
        $this->assertEquals(0.0, $freeCancelledBooking->calculateCommission());

        // Đơn 4: Khách no-show -> chủ sân giữ cọc -> hoa hồng tính trên cọc (90.000 * 8% = 7.200)
        $noShowBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-18',
            'status' => BookingStatus::Completed->value,
            'total_price' => 300000,
            'payment_status' => 'paid',
            'payment_amount' => 90000,
            'no_show_at' => '2026-09-18 18:30:00',
        ]);

        $this->assertEquals(90000, $noShowBooking->commissionBase());
        $this->assertEquals(7200.0, $noShowBooking->calculateCommission());
    }


    public function test_statements_generate_console_command(): void
    {
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-08-15',
            'status' => BookingStatus::Completed->value,
            'total_price' => 500000,
            'payment_status' => 'paid',
            'payment_amount' => 500000,
            'checked_in_at' => '2026-08-15 18:00:00',
        ]);

        $this->artisan('statements:generate 2026-08')
            ->expectsOutputToContain('Đang lập bảng đối soát & chi trả cho kỳ: 2026-08...')
            ->assertExitCode(0);

        $this->assertDatabaseHas('owner_statements', [
            'period' => '2026-08',
            'owner_profile_id' => $this->owner->fieldOwnerProfile->id,
        ]);
    }


    public function test_field_owner_cannot_view_other_owner_statement(): void
    {
        $otherProfile = $this->otherOwner->fieldOwnerProfile;
        $otherStatement = OwnerStatement::create([
            'owner_profile_id' => $otherProfile->id,
            'period' => '2026-08',
            'booking_count' => 1,
            'gmv' => 500000,
            'commission_amount' => 40000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(5),
            'transfer_code' => 'ST202608P2ABC',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/field-owner/statements/{$otherStatement->id}");
        $response->assertForbidden();
    }




    public function test_owner_and_admin_dashboards_include_statement_and_gmv_metrics(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        // Tạo 1 statement đã nộp
        OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-07',
            'booking_count' => 2,
            'gmv' => 1000000,
            'commission_amount' => 80000,
            'status' => 'paid',
            'paid_at' => now(),
            'due_at' => now()->subMonth(),
            'transfer_code' => 'ST202607P1PAID',
        ]);

        // Tạo 1 statement chưa nộp
        OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-08',
            'booking_count' => 3,
            'gmv' => 1500000,
            'commission_amount' => 120000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(5),
            'transfer_code' => 'ST202608P1UNPAID',
        ]);

        // 1. FieldOwner Dashboard
        Sanctum::actingAs($this->owner);
        $ownerDash = $this->getJson('/api/v1/field-owner/dashboard');
        $ownerDash->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_gmv',
                    'current_month_gmv',
                    'current_month_collected',
                    'current_month_commission',
                    'current_month_net_revenue',
                    'statement_summary',
                ],
            ]);

        // 2. Admin Dashboard
        Sanctum::actingAs($this->admin);
        $adminDash = $this->getJson('/api/v1/admin/dashboard');
        $adminDash->assertOk();
        $this->assertEquals(80000, (float) $adminDash->json('data.total_commission_collected'));
        $this->assertEquals(120000, (float) $adminDash->json('data.total_commission_pending'));
    }




    public function test_cannot_generate_statement_for_future_period(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/v1/admin/statements/generate', [
            'period' => '2099-01',
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('message', 'Không thể sinh bảng đối soát cho các kỳ trong tương lai.');
    }


    public function test_viewing_booking_resource_does_not_write_commission_to_database(): void
    {
        Settings::set('commission_percent', 8);

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => now()->addDays(3)->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 300000,
        ]);

        $this->assertNull($booking->commission_amount);

        // Khách hoặc Chủ sân xem chi tiết đơn qua API
        Sanctum::actingAs($this->customer);
        $res = $this->getJson("/api/v1/customer/bookings/{$booking->id}");
        $res->assertOk();

        // Kiểm tra trong DB: commission_amount vẫn phải là null, không bị ghi đè thành 0
        $this->assertNull($booking->refresh()->commission_amount);

        // Sau đó đơn được hoàn thành và check-in
        $booking->update([
            'status' => BookingStatus::Completed->value,
            'checked_in_at' => now(),
            'payment_status' => 'paid',
            'payment_amount' => 300000,
        ]);

        // Đọc lại qua Resource -> tự tính đúng 24000 (300k * 8%) mà không bị kẹt ở 0
        $resAfter = $this->getJson("/api/v1/customer/bookings/{$booking->id}");
        $resAfter->assertOk()
            ->assertJsonPath('data.commission.amount', 24000);
    }

    private function makeStatement(string $period, string $status, float $commission, $dueAt): OwnerStatement
    {
        return OwnerStatement::create([
            'owner_profile_id' => $this->owner->fieldOwnerProfile->id,
            'period' => $period,
            'booking_count' => 2,
            'gmv' => $commission * 10,
            'commission_amount' => $commission,
            'status' => $status,
            'due_at' => $dueAt,
            'transfer_code' => 'ST'.str_replace('-', '', $period).'P1TEST',
        ]);
    }


}
