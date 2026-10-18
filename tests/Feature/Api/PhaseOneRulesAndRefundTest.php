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
use App\Services\AdminService;
use App\Services\BookingService;
use App\Services\CheckInService;
use App\Services\FieldOwnerService;
use App\Services\ProfileService;
use App\Services\WaitlistService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseOneRulesAndRefundTest extends TestCase
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
            'business_name' => 'Sân Bóng Test',
            'business_address' => '123 Đường Test, Quận 1',
            'business_phone' => '0901234567',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'NGUYEN CHU SAN',
            'verification_status' => 'approved',
        ]);

        $category = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da-phase1']);
        $type = FieldType::create(['sport_category_id' => $category->id, 'name' => 'Sân 7', 'slug' => 'san-7-phase1']);

        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Mỹ Đình Test',
            'slug' => 'san-my-dinh-test',
            'address' => 'Hà Nội',
            'price_per_hour' => 300000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => '17:00:00',
            'end_time' => '18:30:00',
            'price_per_hour' => 300000,
            'is_active' => true,
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'trust_score' => 100,
        ]);
    }

    public function test_customer_free_cancellation_records_pending_refund_and_no_penalty(): void
    {
        // Trận đấu ngày mai 17:00
        $bookingDate = today()->addDay()->toDateString();
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $bookingDate,
            'status' => BookingStatus::Confirmed->value,
            'confirmed_at' => now()->subHours(10), // duyệt từ lâu
            'total_price' => 450000,
            'payment_status' => 'paid',
            'payment_amount' => 135000, // cọc 30%
        ]);

        // Huỷ lúc 09:00 sáng ngày mai (trước giờ chơi 8 tiếng >= 4h)
        $this->travelTo(today()->addDay()->setTime(9, 0));

        Sanctum::actingAs($this->customer);
        $res = $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel");

        $res->assertOk();
        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertSame('pending', $fresh->refund_status);
        $this->assertEquals(135000, $fresh->refund_amount);
        $this->assertSame(100, $this->customer->fresh()->trust_score);
    }

    public function test_customer_late_cancellation_loses_deposit_and_penalizes_trust_score(): void
    {
        $bookingDate = today()->addDay()->toDateString();
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $bookingDate,
            'status' => BookingStatus::Confirmed->value,
            'confirmed_at' => now()->subHours(10), // đã quá 2h ân hạn
            'total_price' => 450000,
            'payment_status' => 'paid',
            'payment_amount' => 135000,
        ]);

        // Huỷ lúc 15:00 (còn 2 tiếng trước giờ chơi 17:00 < 4h)
        $this->travelTo(today()->addDay()->setTime(15, 0));

        Sanctum::actingAs($this->customer);
        $res = $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel");

        $res->assertOk();
        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertNull($fresh->refund_status); // Mất cọc
        $this->assertNull($fresh->refund_amount);
        $this->assertSame(85, $this->customer->fresh()->trust_score); // Bị phạt -15 điểm
    }

    public function test_owner_cancellation_always_refunds_customer(): void
    {
        $bookingDate = today()->addDay()->toDateString();
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $bookingDate,
            'status' => BookingStatus::Confirmed->value,
            'confirmed_at' => now()->subHour(),
            'total_price' => 450000,
            'payment_status' => 'paid',
            'payment_amount' => 135000,
        ]);

        $this->travelTo(today()->addDay()->setTime(16, 0)); // 1 tiếng trước giờ chơi

        Sanctum::actingAs($this->owner);
        $res = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Sân gặp sự cố ngập nước',
        ]);

        $res->assertOk();
        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertSame('pending', $fresh->refund_status);
        $this->assertEquals(135000, $fresh->refund_amount);
        $this->assertSame(100, $this->customer->fresh()->trust_score); // Khách không có lỗi
    }

    public function test_owner_cannot_reject_or_cancel_reported_booking(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDay()->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 450000,
            'payment_status' => 'reported',
            'payment_amount' => 135000,
        ]);

        Sanctum::actingAs($this->owner);

        // Thử từ chối đơn reported
        $resReject = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'rejected',
            'reason' => 'Không muốn nhận',
        ]);
        $resReject->assertStatus(409);
        $resReject->assertJsonFragment(['message' => 'Khách đã báo chuyển khoản, vui lòng xác nhận hoặc đối soát thanh toán thay vì từ chối/huỷ.']);

        // Thử huỷ đơn reported
        $resCancel = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Huỷ ngang',
        ]);
        $resCancel->assertStatus(409);
    }

    public function test_owner_cannot_cancel_booking_after_start_time(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
        ]);

        // Đã 17:05 (quá giờ bắt đầu 17:00)
        $this->travelTo(today()->setTime(17, 5));

        Sanctum::actingAs($this->owner);
        $res = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Huỷ muộn',
        ]);

        $res->assertStatus(409);
        $res->assertJsonFragment(['message' => 'Đơn đã quá giờ bắt đầu, không thể huỷ.']);
    }

    public function test_check_in_only_allowed_for_confirmed_bookings(): void
    {
        $pendingBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 450000,
        ]);

        $this->travelTo(today()->setTime(16, 30)); // trong khung check-in

        Sanctum::actingAs($this->owner);
        $res = $this->patchJson("/api/v1/field-owner/bookings/{$pendingBooking->id}/check-in");

        $res->assertStatus(422);
        $res->assertJsonFragment(['message' => 'Đơn chưa thanh toán xác nhận, không thể check-in.']);

        // Đơn không bị đổi sang confirmed
        $this->assertSame(BookingStatus::Pending, $pendingBooking->fresh()->status);
        $this->assertNull($pendingBooking->fresh()->checked_in_at);
    }

    public function test_owner_cannot_complete_booking_without_check_in(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
            'checked_in_at' => null, // Chưa check-in
        ]);

        // Hết giờ thi đấu 18:35
        $this->travelTo(today()->setTime(18, 35));

        Sanctum::actingAs($this->owner);
        $res = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", [
            'status' => 'completed',
        ]);

        $res->assertStatus(409);
        $res->assertJsonFragment(['message' => 'Chỉ có thể hoàn thành đơn sau khi khách đã check-in.']);
    }

    public function test_owner_can_record_no_show_and_customer_is_penalized(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
            'checked_in_at' => null,
        ]);

        // Lúc 17:30 (sau giờ bắt đầu 17:00, khách chưa đến)
        $this->travelTo(today()->setTime(17, 30));

        Sanctum::actingAs($this->owner);
        $res = $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/no-show");

        $res->assertOk();
        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->no_show_at);
        $this->assertSame(75, $this->customer->fresh()->trust_score); // -25 điểm no-show
    }

    public function test_max_pending_bookings_enforced(): void
    {
        // Cấu hình max_pending_bookings = 3
        Settings::set('max_pending_bookings', 3);

        for ($i = 1; $i <= 3; $i++) {
            $otherSlot = TimeSlot::create([
                'sports_field_id' => $this->field->id,
                'start_time' => sprintf('%02d:00:00', $i * 2),
                'end_time' => sprintf('%02d:00:00', $i * 2 + 1),
                'price_per_hour' => 300000,
                'is_active' => true,
            ]);

            Booking::create([
                'user_id' => $this->customer->id,
                'sports_field_id' => $this->field->id,
                'time_slot_id' => $otherSlot->id,
                'booking_date' => today()->addDays($i)->toDateString(),
                'status' => BookingStatus::Pending->value,
                'total_price' => 300000,
            ]);
        }

        Sanctum::actingAs($this->customer);
        $res = $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(5)->toDateString(),
        ]);

        $res->assertStatus(409);
        $res->assertJsonFragment(['message' => 'Bạn đang có 3 đơn chờ xử lý. Tối đa chỉ được có 3 đơn cùng lúc.']);
    }

    public function test_booking_too_far_in_advance_is_rejected(): void
    {
        Settings::set('max_advance_days', 30);

        Sanctum::actingAs($this->customer);
        $res = $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(31)->toDateString(),
        ]);

        $res->assertStatus(422); // Validation error
    }

    public function test_owner_cannot_change_bank_account_when_active_payments_exist(): void
    {
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDay()->toDateString(),
            'status' => BookingStatus::Pending->value,
            'total_price' => 450000,
            'payment_status' => 'awaiting',
        ]);

        Sanctum::actingAs($this->owner);
        $res = $this->putJson('/api/v1/field-owner/profile', [
            'business_name' => 'Sân Bóng Test',
            'business_address' => '123 Đường Test, Quận 1',
            'business_phone' => '0901234567',
            'bank_name' => 'Techcombank', // Đổi ngân hàng
            'bank_account_number' => '9876543210',
            'bank_account_holder' => 'NGUYEN CHU SAN',
        ]);

        $res->assertStatus(409);
        $res->assertJsonFragment(['message' => 'Không thể thay đổi thông tin tài khoản ngân hàng khi đang có đơn chờ thanh toán hoặc đang xác nhận chuyển khoản.']);
    }

    public function test_cannot_deactivate_field_or_slot_with_upcoming_bookings(): void
    {
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(2)->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
        ]);

        Sanctum::actingAs($this->owner);

        // 1. Tắt sân
        $resToggleField = $this->patchJson("/api/v1/field-owner/fields/{$this->field->id}/toggle");
        $resToggleField->assertStatus(409);
        $resToggleField->assertJsonFragment(['message' => 'Sân đang có đơn đặt sắp tới, không thể tạm dừng hoạt động.']);

        // 2. Tắt slot
        $resSaveSlot = $this->putJson("/api/v1/field-owner/fields/{$this->field->id}/slots/{$this->slot->id}", [
            'start_time' => '17:00',
            'end_time' => '18:30',
            'price_per_hour' => 300000,
            'is_active' => false,
        ]);
        $resSaveSlot->assertStatus(409);
        $resSaveSlot->assertJsonFragment(['message' => 'Khung giờ đang có đơn sắp tới, không thể tắt hoạt động.']);
    }

    public function test_admin_deactivating_user_cancels_future_bookings_with_refund(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(2)->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
            'payment_status' => 'paid',
            'payment_amount' => 135000,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        Sanctum::actingAs($admin);
        // Khoá tài khoản chủ sân
        $res = $this->patchJson("/api/v1/admin/users/{$this->owner->id}/toggle", [
            'reason' => 'Chủ sân vi phạm quy chế hoạt động.',
        ]);
        $res->assertOk();

        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertSame('pending', $fresh->refund_status);
        $this->assertEquals(135000, $fresh->refund_amount);
        $this->assertStringContainsString('Chủ sân đã bị tạm khoá tài khoản', $fresh->cancel_reason);
    }

    public function test_admin_suspending_owner_cancels_future_bookings_with_refund(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(2)->toDateString(),
            'status' => BookingStatus::Confirmed->value,
            'total_price' => 450000,
            'payment_status' => 'paid',
            'payment_amount' => 135000,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $profile = $this->owner->fieldOwnerProfile;

        Sanctum::actingAs($admin);
        // Đình chỉ hồ sơ chủ sân (Approved -> Suspended)
        $res = $this->patchJson("/api/v1/admin/field-owners/{$profile->id}/verify", [
            'status' => 'suspended',
            'rejection_reason' => 'Vi phạm quy chế nền tảng',
        ]);
        $res->assertOk();

        $this->assertSame('suspended', $profile->fresh()->verification_status->value);

        $fresh = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $fresh->status);
        $this->assertSame('pending', $fresh->refund_status);
        $this->assertEquals(135000, $fresh->refund_amount);
        $this->assertStringContainsString('Chủ sân đã bị đình chỉ', $fresh->cancel_reason);
    }

    public function test_admin_verify_field_transition_guard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        // 1. Sân đang approved: không thể chuyển sang approved lần nữa
        $resInvalid = $this->patchJson("/api/v1/admin/fields/{$this->field->id}/verify", [
            'status' => 'approved',
        ]);
        $resInvalid->assertStatus(409);
        $resInvalid->assertJsonFragment(['message' => 'Sân này không thể chuyển sang trạng thái này.']);

        // 2. Sân đang approved -> rejected: hợp lệ
        $resReject = $this->patchJson("/api/v1/admin/fields/{$this->field->id}/verify", [
            'status' => 'rejected',
            'rejection_reason' => 'Thông tin sân không chính xác',
        ]);
        $resReject->assertOk();
        $this->assertSame(\App\Enums\FieldStatus::Rejected, $this->field->fresh()->status);

        // 3. Sân đang rejected -> duyệt lại thành approved: hợp lệ
        $resReapprove = $this->patchJson("/api/v1/admin/fields/{$this->field->id}/verify", [
            'status' => 'approved',
        ]);
        $resReapprove->assertOk();
        $this->assertSame(\App\Enums\FieldStatus::Approved, $this->field->fresh()->status);
        $this->assertNull($this->field->fresh()->rejection_reason);
    }

    public function test_waitlist_accept_offer_blocks_owner_from_booking_own_field(): void
    {
        $waitlist = \App\Models\BookingWaitlist::create([
            'user_id' => $this->owner->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(2)->toDateString(),
            'status' => 'offered',
            'offer_expires_at' => now()->addMinutes(15),
            'priority_score' => 100,
        ]);

        $this->expectException(\App\Exceptions\ConflictException::class);
        $this->expectExceptionMessage('Bạn không thể đặt sân của chính mình.');

        app(WaitlistService::class)->acceptOffer($this->owner, $waitlist->id);
    }
}
