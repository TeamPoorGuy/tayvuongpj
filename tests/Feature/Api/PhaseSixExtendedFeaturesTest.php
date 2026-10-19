<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\FieldClosure;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Review;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseSixExtendedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private SportsField $field;

    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id,
            'owner_name' => 'Chủ Sân Phase 6',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Bóng P6',
            'business_address' => 'Hà Nội',
            'business_phone' => '0901234567',
            'business_license' => 'GPKD-P6',
            'verification_status' => 'approved',
            'verified_at' => now(),
            'bank_name' => 'Techcombank',
            'bank_bin' => '970407',
            'bank_account_number' => '1903666888',
            'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);

        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now(), 'trust_score' => 100]);

        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 7', 'slug' => 'san-7']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Thể Thao P6',
            'slug' => 'san-the-thao-p6',
            'address' => 'Hà Nội',
            'price_per_hour' => 300000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => '18:00',
            'end_time' => '19:30',
            'is_active' => true,
        ]);
    }

    // ==========================================
    // 1. NGÀY NGHỈ & ĐÓNG SÂN (FIELD CLOSURES)
    // ==========================================

    public function test_owner_can_create_list_and_delete_field_closures(): void
    {
        Sanctum::actingAs($this->owner);

        $startDate = today()->addDays(5)->toDateString();
        $endDate = today()->addDays(7)->toDateString();

        // 1. Tạo lịch nghỉ
        $res = $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/closures", [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Bảo dưỡng định kỳ mặt cỏ',
        ]);
        $res->assertCreated();
        $this->assertSame('Bảo dưỡng định kỳ mặt cỏ', $res->json('data.reason'));
        $closureId = $res->json('data.id');

        // 2. Danh sách ngày nghỉ
        $listRes = $this->getJson("/api/v1/field-owner/fields/{$this->field->id}/closures");
        $listRes->assertOk();
        $this->assertCount(1, $listRes->json('data'));

        // 3. Xoá ngày nghỉ
        $delRes = $this->deleteJson("/api/v1/field-owner/fields/{$this->field->id}/closures/{$closureId}");
        $delRes->assertOk();
        $this->assertDatabaseMissing('field_closures', ['id' => $closureId]);
    }

    public function test_cannot_create_closure_when_active_bookings_exist_in_range(): void
    {
        $bookDate = today()->addDays(6)->toDateString();

        // Khách đã đặt đơn ngày này
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $bookDate,
            'status' => 'confirmed',
            'total_price' => 450000,
        ]);

        Sanctum::actingAs($this->owner);

        // Chủ sân cố gắng tạo ngày nghỉ bao gồm ngày đã có đơn
        $res = $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/closures", [
            'start_date' => today()->addDays(5)->toDateString(),
            'end_date' => today()->addDays(7)->toDateString(),
            'reason' => 'Bảo trì',
        ]);

        $res->assertStatus(409);
        $this->assertStringContainsString('Không thể đóng sân vì đã có 1 đơn đặt sân', $res->json('message'));
    }

    public function test_customer_booking_on_closed_date_is_blocked(): void
    {
        $closedDate = today()->addDays(3)->toDateString();

        FieldClosure::create([
            'sports_field_id' => $this->field->id,
            'start_date' => $closedDate,
            'end_date' => $closedDate,
            'reason' => 'Nghỉ lễ Giỗ Tổ',
        ]);

        // 1. Kiểm tra catalog available-slots
        $availRes = $this->getJson("/api/v1/fields/{$this->field->id}/available-slots?date={$closedDate}");
        $availRes->assertOk();
        $this->assertTrue($availRes->json('data.is_closed'));
        $this->assertSame('Nghỉ lễ Giỗ Tổ', $availRes->json('data.closure_reason'));
        $this->assertTrue($availRes->json('data.slots.0.is_booked'));

        // 2. Khách cố tình gửi request đặt sân
        Sanctum::actingAs($this->customer);
        $res = $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $closedDate,
        ]);

        $res->assertStatus(409);
        $this->assertStringContainsString('Sân tạm đóng cửa', $res->json('message'));
    }

    public function test_non_owner_cannot_manage_closures(): void
    {
        Sanctum::actingAs($this->customer);

        $res = $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/closures", [
            'start_date' => today()->addDays(2)->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
            'reason' => 'Thử nghiệm',
        ]);

        $res->assertStatus(403);
    }

    // ==========================================
    // 2. CHỦ SÂN TRẢ LỜI ĐÁNH GIÁ (OWNER REPLIES)
    // ==========================================

    public function test_owner_can_reply_to_review_and_customer_cannot_reply(): void
    {
        $review = Review::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'rating' => 5,
            'comment' => 'Sân đẹp, mặt cỏ rất êm và đèn sáng.',
            'is_visible' => true,
        ]);

        // 1. Khách hàng không có quyền reply
        Sanctum::actingAs($this->customer);
        $failRes = $this->patchJson("/api/v1/field-owner/reviews/{$review->id}/reply", [
            'reply' => 'Cảm ơn bạn',
        ]);
        $failRes->assertStatus(403);

        // 2. Chủ sân reply
        Sanctum::actingAs($this->owner);
        $successRes = $this->patchJson("/api/v1/field-owner/reviews/{$review->id}/reply", [
            'reply' => 'Cảm ơn bạn đã ủng hộ sân! Rất hân hạnh được phục vụ bạn những trận tới.',
        ]);
        $successRes->assertOk();
        $this->assertSame('Cảm ơn bạn đã ủng hộ sân! Rất hân hạnh được phục vụ bạn những trận tới.', $successRes->json('data.owner_reply'));
        $this->assertNotNull($successRes->json('data.owner_replied_at'));

        // 3. Endpoint công khai của sân trả về câu trả lời của chủ sân
        $fieldRes = $this->getJson("/api/v1/fields/{$this->field->slug}");
        $fieldRes->assertOk();
        $this->assertSame('Cảm ơn bạn đã ủng hộ sân! Rất hân hạnh được phục vụ bạn những trận tới.', $fieldRes->json('data.reviews.0.owner_reply'));
    }

    // ==========================================
    // 3. ĐẶT CỐ ĐỊNH HÀNG TUẦN (RECURRING BOOKINGS)
    // ==========================================

    public function test_customer_can_create_recurring_booking_series(): void
    {
        Sanctum::actingAs($this->customer);

        $startDate = today()->addDays(2)->toDateString();

        $res = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => $startDate,
            'weeks' => 4,
            'notes' => 'Đặt đội cố định thứ 5 hàng tuần',
        ]);

        $res->assertStatus(201);
        $groupId = $res->json('data.recurring_group_id');
        $this->assertNotNull($groupId);
        $this->assertSame(4, $res->json('data.total_bookings'));

        // Kiểm tra database: 4 đơn đều có chung recurring_group_id
        $bookings = Booking::where('recurring_group_id', $groupId)->get();
        $this->assertCount(4, $bookings);

        // Owner approves entire recurring series
        Sanctum::actingAs($this->owner);
        $approveRes = $this->patchJson("/api/v1/field-owner/bookings/recurring/{$groupId}/approve");
        $approveRes->assertOk();

        // 4 bookings are now awaiting payment
        $freshBookings = Booking::where('recurring_group_id', $groupId)->orderBy('booking_date')->get();
        foreach ($freshBookings as $b) {
            $this->assertSame('awaiting', $b->payment_status);
            $this->assertNotNull($b->payment_due_at);
        }

        // Buổi 1: due_at trong vòng vài tiếng (payment_window_hours)
        $this->assertTrue($freshBookings[0]->payment_due_at->lte(now()->addHours(3)));

        // Buổi 4: due_at cách xa theo quy tắc trước giờ thi đấu 24h
        $this->assertTrue($freshBookings[3]->payment_due_at->gt(now()->addDays(10)));
    }

    public function test_recurring_booking_detects_conflicts_and_skips_when_requested(): void
    {
        $startDate = today()->addDays(2)->toDateString();
        $conflictDate = today()->addDays(2)->addWeeks(1)->toDateString(); // Tuần thứ 2 bị trùng

        // Tạo đơn đã chiếm chỗ ở tuần thứ 2
        Booking::create([
            'user_id' => $this->owner->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $conflictDate,
            'status' => 'confirmed',
            'total_price' => 450000,
        ]);

        Sanctum::actingAs($this->customer);

        // 1. Chưa chọn skip_conflicts: trả về 409 thông báo có ngày trùng
        $res = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => $startDate,
            'weeks' => 4,
            'skip_conflicts' => false,
        ]);

        $res->assertStatus(409);
        $this->assertTrue($res->json('has_conflicts'));
        $this->assertCount(1, $res->json('conflicts'));
        $this->assertSame($conflictDate, $res->json('conflicts.0.date'));

        // 2. Gửi lại kèm skip_conflicts: true -> tạo thành công 3 buổi còn lại
        $skipRes = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => $startDate,
            'weeks' => 4,
            'skip_conflicts' => true,
        ]);

        $skipRes->assertStatus(201);
        $this->assertSame(3, $skipRes->json('data.total_bookings'));
        $groupId = $skipRes->json('data.recurring_group_id');

        $bookings = Booking::where('recurring_group_id', $groupId)->pluck('booking_date')->map->toDateString()->all();
        $this->assertNotContains($conflictDate, $bookings);
        $this->assertContains($startDate, $bookings);
    }

    public function test_owner_can_reject_recurring_series(): void
    {
        Sanctum::actingAs($this->customer);
        $createRes = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => today()->addDays(3)->toDateString(),
            'weeks' => 3,
        ]);
        $groupId = $createRes->json('data.recurring_group_id');

        Sanctum::actingAs($this->owner);
        $rejectRes = $this->patchJson("/api/v1/field-owner/bookings/recurring/{$groupId}/reject", [
            'reason' => 'Khung giờ này sân ưu tiên giải đấu nội bộ.',
        ]);
        $rejectRes->assertOk();

        $rejectedBookings = Booking::where('recurring_group_id', $groupId)->get();
        foreach ($rejectedBookings as $b) {
            $this->assertSame('rejected', $b->status->value);
            $this->assertSame('Khung giờ này sân ưu tiên giải đấu nội bộ.', $b->cancel_reason);
        }
    }

    public function test_customer_can_cancel_entire_remaining_recurring_series(): void
    {
        Sanctum::actingAs($this->customer);
        $createRes = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => today()->addDays(4)->toDateString(),
            'weeks' => 3,
        ]);
        $groupId = $createRes->json('data.recurring_group_id');

        // Customer cancels all remaining
        $cancelRes = $this->postJson("/api/v1/customer/bookings/recurring/{$groupId}/cancel", [
            'cancel_reason' => 'Đội bóng bận việc không duy trì được.',
        ]);
        $cancelRes->assertOk();

        $cancelledBookings = Booking::where('recurring_group_id', $groupId)->get();
        foreach ($cancelledBookings as $b) {
            $this->assertSame('cancelled', $b->status->value);
            $this->assertSame('Đội bóng bận việc không duy trì được.', $b->cancel_reason);
        }
    }

    public function test_owner_can_approve_recurring_series_with_tiered_due_dates(): void
    {
        Sanctum::actingAs($this->customer);
        $createRes = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => today()->addDays(7)->toDateString(),
            'weeks' => 3,
        ]);
        $createRes->assertCreated();
        $groupId = $createRes->json('data.recurring_group_id');

        Sanctum::actingAs($this->owner);
        $approveRes = $this->patchJson("/api/v1/field-owner/bookings/recurring/{$groupId}/approve");
        $approveRes->assertOk();

        $bookings = Booking::where('recurring_group_id', $groupId)->orderBy('booking_date')->get();
        $this->assertCount(3, $bookings);

        foreach ($bookings as $b) {
            $this->assertSame('awaiting', $b->payment_status);
            $this->assertNotNull($b->payment_code);
            $this->assertNotNull($b->payment_due_at);
        }

        // Buổi 1: thanh toán theo payment_window_hours (2 tiếng kể từ bây giờ)
        $this->assertTrue($bookings[0]->payment_due_at->between(now()->addMinutes(110), now()->addMinutes(130)));

        // Buổi 2 (14 ngày tới): thanh toán muộn nhất trước giờ chơi 24 tiếng
        $expectedDueSession2 = $bookings[1]->startsAt()->subHours(24);
        $this->assertSame(
            $expectedDueSession2->format('Y-m-d H:i'),
            $bookings[1]->payment_due_at->format('Y-m-d H:i')
        );

        // Buổi 3 (21 ngày tới): thanh toán muộn nhất trước giờ chơi 24 tiếng
        $expectedDueSession3 = $bookings[2]->startsAt()->subHours(24);
        $this->assertSame(
            $expectedDueSession3->format('Y-m-d H:i'),
            $bookings[2]->payment_due_at->format('Y-m-d H:i')
        );
    }

    public function test_closure_creation_rejects_past_date(): void
    {
        Sanctum::actingAs($this->owner);

        // 1. Qua API validation
        $res = $this->postJson("/api/v1/field-owner/fields/{$this->field->id}/closures", [
            'start_date' => today()->subDays(2)->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Đã nghỉ xong',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['start_date']);

        // 2. Qua Service layer
        $this->expectException(\App\Exceptions\ConflictException::class);
        $this->expectExceptionMessage('Ngày bắt đầu không được ở trong quá khứ.');

        app(\App\Services\FieldClosureService::class)->createClosure($this->owner, $this->field, [
            'start_date' => today()->subDays(1)->toDateString(),
            'end_date' => today()->addDays(2)->toDateString(),
            'reason' => 'Nghỉ bù',
        ]);
    }

    public function test_cancel_recurring_enforces_cancellation_rules_and_frees_waitlist(): void
    {
        // Tạo chuỗi đặt cố định
        Sanctum::actingAs($this->customer);
        $startDate = today()->addDays(2)->toDateString();
        $createRes = $this->postJson('/api/v1/customer/bookings/recurring', [
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'start_date' => $startDate,
            'weeks' => 2,
        ]);
        $createRes->assertCreated();
        $groupId = $createRes->json('data.recurring_group_id');

        // Có người khác đang chờ waitlist buổi đầu
        $waitlistUser = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $waitlistEntry = \App\Models\BookingWaitlist::create([
            'user_id' => $waitlistUser->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => $startDate,
            'status' => 'waiting',
            'priority_score' => 100,
        ]);

        // Customer huỷ chuỗi
        Sanctum::actingAs($this->customer);
        $cancelRes = $this->postJson("/api/v1/customer/bookings/recurring/{$groupId}/cancel", [
            'cancel_reason' => 'Bận đột xuất',
        ]);
        $cancelRes->assertOk();

        // Kiểm tra slot được nhả cho người waitlist
        $this->assertSame('offered', $waitlistEntry->fresh()->status);
    }

    public function test_cancel_recurring_deducts_trust_score_on_late_cancellation(): void
    {
        // Tạo slot hôm nay lúc 2 tiếng nữa (< 4 tiếng -> huỷ muộn)
        $nearStartTime = now()->addHours(2)->format('H:i');
        $nearEndTime = now()->addHours(3)->addMinutes(30)->format('H:i');
        $nearSlot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => $nearStartTime,
            'end_time' => $nearEndTime,
            'is_active' => true,
        ]);

        $groupId = (string) \Illuminate\Support\Str::uuid();
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $nearSlot->id,
            'booking_date' => today()->toDateString(),
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'total_price' => 200000,
            'recurring_group_id' => $groupId,
        ]);

        $initialScore = $this->customer->trust_score;

        Sanctum::actingAs($this->customer);
        $cancelRes = $this->postJson("/api/v1/customer/bookings/recurring/{$groupId}/cancel", [
            'cancel_reason' => 'Huỷ gấp',
        ]);
        $cancelRes->assertOk();

        // Bị trừ 15 điểm uy tín vì huỷ muộn
        $this->assertSame($initialScore - 15, $this->customer->fresh()->trust_score);
    }
}
