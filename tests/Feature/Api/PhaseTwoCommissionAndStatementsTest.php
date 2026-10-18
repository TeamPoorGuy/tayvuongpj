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

    public function test_statement_service_generates_idempotent_monthly_statements(): void
    {
        Settings::set('commission_percent', 10);
        Settings::set('statement_due_days', 7);

        // Tạo 2 đơn trong tháng 9/2026
        $b1 = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-05',
            'status' => BookingStatus::Completed->value,
            'total_price' => 400000,
            'payment_status' => 'paid',
            'payment_amount' => 400000,
            'checked_in_at' => '2026-09-05 18:00:00',
        ]);

        $b2 = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-09-20',
            'status' => BookingStatus::Completed->value,
            'total_price' => 600000,
            'payment_status' => 'paid',
            'payment_amount' => 600000,
            'checked_in_at' => '2026-09-20 18:00:00',
        ]);

        $service = app(StatementService::class);
        $profileId = $this->owner->fieldOwnerProfile->id;

        // Sinh kỳ đối soát cho tháng 2026-09
        $statements = $service->generate('2026-09', $profileId);

        $this->assertCount(1, $statements);
        $st = $statements[0];
        $this->assertEquals('2026-09', $st->period);
        $this->assertEquals(2, $st->booking_count);
        $this->assertEquals(1000000.0, (float) $st->gmv);
        $this->assertEquals(100000.0, (float) $st->commission_amount);
        $this->assertEquals('unpaid', $st->status);
        $this->assertNotNull($st->transfer_code);
        $this->assertEquals('2026-10-07 23:59:59', $st->due_at->toDateTimeString());

        // Kiểm tra bookings được gắn statement_id
        $this->assertEquals($st->id, $b1->refresh()->statement_id);
        $this->assertEquals($st->id, $b2->refresh()->statement_id);

        // Chạy lại (Idempotency) -> không tạo bản ghi trùng lặp
        $statementsAgain = $service->generate('2026-09', $profileId);
        $this->assertCount(1, $statementsAgain);
        $this->assertEquals($st->id, $statementsAgain[0]->id);
        $this->assertEquals(1, OwnerStatement::where('owner_profile_id', $profileId)->where('period', '2026-09')->count());
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
            ->expectsOutputToContain('Đang sinh bảng đối soát hoa hồng cho kỳ: 2026-08...')
            ->assertExitCode(0);

        $this->assertDatabaseHas('owner_statements', [
            'period' => '2026-08',
            'owner_profile_id' => $this->owner->fieldOwnerProfile->id,
        ]);
    }

    public function test_field_owner_can_view_statements_and_current_summary(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        $st = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-08',
            'booking_count' => 5,
            'gmv' => 2000000,
            'commission_amount' => 160000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(5),
            'transfer_code' => 'ST202608P1XYZ',
        ]);

        Settings::set('platform_bank_name', 'Techcombank');

        Sanctum::actingAs($this->owner);

        // 1. Danh sách đối soát
        $response = $this->getJson('/api/v1/field-owner/statements');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $st->id)
            ->assertJsonPath('data.0.period', '2026-08')
            ->assertJsonPath('data.0.platform_bank.bank_name', 'Techcombank');

        // 2. Chi tiết kỳ đối soát
        $detailResponse = $this->getJson("/api/v1/field-owner/statements/{$st->id}");
        $detailResponse->assertOk()
            ->assertJsonPath('data.id', $st->id);
        $this->assertEquals(160000, (float) $detailResponse->json('data.commission_amount'));

        // 3. Ước tính tháng hiện tại
        $summaryResponse = $this->getJson('/api/v1/field-owner/statements/current-summary');
        $summaryResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'booking_count',
                    'estimated_gmv',
                    'estimated_commission',
                    'commission_rate',
                    'has_overdue_debt',
                ],
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

    public function test_field_owner_can_report_statement_payment(): void
    {
        $profile = $this->owner->fieldOwnerProfile;
        $st = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-08',
            'booking_count' => 3,
            'gmv' => 1500000,
            'commission_amount' => 120000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(3),
            'transfer_code' => 'ST202608P1REP',
        ]);

        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/field-owner/statements/{$st->id}/report", [
            'note' => 'Tôi đã chuyển khoản qua Vietcombank lúc 10h sáng.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.note', 'Tôi đã chuyển khoản qua Vietcombank lúc 10h sáng.');

        $this->assertDatabaseHas('owner_statements', [
            'id' => $st->id,
            'status' => 'reported',
            'note' => 'Tôi đã chuyển khoản qua Vietcombank lúc 10h sáng.',
        ]);
    }

    public function test_admin_can_generate_confirm_and_waive_statements(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        // Tạo đơn hoàn thành để có phát sinh phí hoa hồng > 0
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-07-15',
            'status' => BookingStatus::Completed->value,
            'total_price' => 500000,
            'payment_status' => 'paid',
            'payment_amount' => 500000,
            'checked_in_at' => '2026-07-15 18:00:00',
        ]);

        Sanctum::actingAs($this->admin);

        // 1. Admin bấm sinh đối soát
        $generateResponse = $this->postJson('/api/v1/admin/statements/generate', [
            'period' => '2026-07',
            'owner_profile_id' => $profile->id,
        ]);
        $generateResponse->assertOk();

        $st = OwnerStatement::where('owner_profile_id', $profile->id)->where('period', '2026-07')->firstOrFail();

        // 2. Admin xem danh sách
        $listResponse = $this->getJson('/api/v1/admin/statements?period=2026-07');
        $listResponse->assertOk()
            ->assertJsonPath('data.0.id', $st->id);

        // 3. Admin xác nhận đã thu phí (confirm)
        $confirmResponse = $this->postJson("/api/v1/admin/statements/{$st->id}/confirm");
        $confirmResponse->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.confirmed_by.id', $this->admin->id);

        $this->assertDatabaseHas('admin_logs', [
            'action' => 'confirm_statement',
            'admin_id' => $this->admin->id,
        ]);

        // 4. Admin miễn phí (waive) cho 1 kỳ khác
        $st2 = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-06',
            'booking_count' => 2,
            'gmv' => 800000,
            'commission_amount' => 64000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(2),
            'transfer_code' => 'ST202606P1WAV',
        ]);

        $waiveResponse = $this->postJson("/api/v1/admin/statements/{$st2->id}/waive", [
            'reason' => 'Tháng khai trương, miễn phí đối soát.',
        ]);

        $waiveResponse->assertOk()
            ->assertJsonPath('data.status', 'waived')
            ->assertJsonPath('data.note', 'Tháng khai trương, miễn phí đối soát.');

        $this->assertDatabaseHas('admin_logs', [
            'action' => 'waive_statement',
            'admin_id' => $this->admin->id,
        ]);
    }

    public function test_owner_blocked_from_approving_bookings_when_having_overdue_debt(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        // Tạo kỳ đối soát quá hạn: due_at ngày hôm qua, status unpaid
        $overdueStatement = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-07',
            'booking_count' => 4,
            'gmv' => 1600000,
            'commission_amount' => 128000,
            'status' => 'unpaid',
            'due_at' => now()->subDay(),
            'transfer_code' => 'ST202607P1DEBT',
        ]);

        $this->assertTrue($overdueStatement->isOverdue());

        // Khách đặt đơn mới
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 300000,
        ]);

        Sanctum::actingAs($this->owner);

        // Chủ sân bấm duyệt -> bị chặn với HTTP 409
        $response = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/approve");
        $response->assertStatus(409)
            ->assertJsonPath('message', 'Tài khoản của bạn đang có kỳ đối soát nợ phí nền tảng quá hạn. Vui lòng thanh toán phí đối soát để tiếp tục duyệt đơn.');

        // Sau khi thanh toán (chuyển sang paid)
        $overdueStatement->update(['status' => 'paid', 'paid_at' => now()]);

        // Duyệt lại -> thành công
        $retryResponse = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/approve");
        $retryResponse->assertOk()
            ->assertJsonPath('data.payment.status', 'awaiting');
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
                    'has_overdue_debt',
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

    public function test_zero_commission_statement_auto_paid_and_does_not_block_owner_after_due_date(): void
    {
        $profile = $this->owner->fieldOwnerProfile;
        $service = app(StatementService::class);

        // Tháng 2026-06 không có đơn nào
        $statements = $service->generate('2026-06', $profile->id);
        $this->assertCount(1, $statements);
        $st = $statements[0];

        $this->assertEquals(0, $st->booking_count);
        $this->assertEquals(0.0, (float) $st->commission_amount);
        $this->assertEquals('paid', $st->status);
        $this->assertNotNull($st->paid_at);

        // Kể cả khi due_at nằm trong quá khứ
        $st->update(['due_at' => now()->subDays(10)]);
        $this->assertFalse($st->isOverdue());
        $this->assertFalse($service->hasOverdueDebt($this->owner));

        // Chủ sân vẫn duyệt đơn bình thường không bị khoá
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 300000,
        ]);

        Sanctum::actingAs($this->owner);
        $response = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/approve");
        $response->assertOk()
            ->assertJsonPath('data.payment.status', 'awaiting');
    }

    public function test_platform_bank_settings_change_is_reflected_in_statements(): void
    {
        Settings::set('platform_bank_name', 'MB Bank');
        Settings::set('platform_bank_account', '999988887777');
        Settings::set('platform_bank_holder', 'CONG TY SPORTHUB VIET NAM - MB');

        $profile = $this->owner->fieldOwnerProfile;
        $st = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-08',
            'booking_count' => 1,
            'gmv' => 500000,
            'commission_amount' => 40000,
            'status' => 'unpaid',
            'due_at' => now()->addDays(5),
            'transfer_code' => 'ST202608MBTEST',
        ]);

        Sanctum::actingAs($this->owner);

        $res = $this->getJson("/api/v1/field-owner/statements/{$st->id}");
        $res->assertOk()
            ->assertJsonPath('data.platform_bank.bank_name', 'MB Bank')
            ->assertJsonPath('data.platform_bank.account_number', '999988887777')
            ->assertJsonPath('data.platform_bank.account_holder', 'CONG TY SPORTHUB VIET NAM - MB');
    }

    public function test_admin_can_reject_reported_statement_and_overdue_debt_is_enforced(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        // Kỳ đối soát quá hạn
        $st = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-07',
            'booking_count' => 2,
            'gmv' => 1000000,
            'commission_amount' => 80000,
            'status' => 'unpaid',
            'due_at' => now()->subDay(),
            'transfer_code' => 'ST202607REJECT',
        ]);

        // Chủ sân bấm báo đã chuyển tiền để thoát khoá
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/field-owner/statements/{$st->id}/report", ['note' => 'Tôi đã chuyển tiền rồi']);
        $this->assertEquals('reported', $st->refresh()->status);
        $this->assertFalse(app(StatementService::class)->hasOverdueDebt($this->owner));

        // Admin đối chiếu sao kê và bấm từ chối
        Sanctum::actingAs($this->admin);
        $rejectRes = $this->postJson("/api/v1/admin/statements/{$st->id}/reject-report", [
            'reason' => 'Chưa thấy tiền nổi trên tài khoản Vietcombank.',
        ]);
        $rejectRes->assertOk()
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.note', 'Từ chối báo chuyển: Chưa thấy tiền nổi trên tài khoản Vietcombank.');

        $this->assertDatabaseHas('admin_logs', [
            'action' => 'reject_statement_payment',
            'admin_id' => $this->admin->id,
        ]);

        // Ngay lập tức chủ sân bị khoá duyệt đơn lại vì quá hạn và status là unpaid
        $this->assertTrue(app(StatementService::class)->hasOverdueDebt($this->owner));

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => now()->addDays(2)->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 300000,
        ]);

        Sanctum::actingAs($this->owner);
        $approveRes = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/approve");
        $approveRes->assertStatus(409)
            ->assertJsonPath('message', 'Tài khoản của bạn đang có kỳ đối soát nợ phí nền tảng quá hạn. Vui lòng thanh toán phí đối soát để tiếp tục duyệt đơn.');
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

    public function test_reported_statement_is_not_overwritten_by_generate(): void
    {
        $profile = $this->owner->fieldOwnerProfile;

        $st = OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-07',
            'booking_count' => 1,
            'gmv' => 500000,
            'commission_amount' => 40000,
            'status' => 'reported',
            'reported_at' => now(),
            'due_at' => now()->addDays(5),
            'transfer_code' => 'ST202607STABLE',
        ]);

        // Tạo thêm đơn mới trong tháng đó
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => '2026-07-20',
            'status' => BookingStatus::Completed->value,
            'total_price' => 800000,
            'payment_status' => 'paid',
            'payment_amount' => 800000,
            'checked_in_at' => '2026-07-20 18:00:00',
        ]);

        // Chạy lại generate
        app(StatementService::class)->generate('2026-07', $profile->id);

        // Số tiền và status kỳ reported không bị thay đổi
        $st->refresh();
        $this->assertEquals('reported', $st->status);
        $this->assertEquals(40000.0, (float) $st->commission_amount);
        $this->assertEquals(1, $st->booking_count);
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
}
