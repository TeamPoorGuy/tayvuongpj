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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFourAdminBookingRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private SportsField $field;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Sân Admin Demo',
            'business_address' => 'Hà Nội',
            'business_phone' => '0901234567',
            'bank_name' => 'Techcombank',
            'bank_account_number' => '19036789999999',
            'bank_account_holder' => 'CHU SAN',
            'verification_status' => 'approved',
        ]);

        $cat = SportCategory::create(['name' => 'Cầu lông', 'slug' => 'cau-long-p4']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân đôi', 'slug' => 'san-doi-p4']);
        $this->field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Cầu Lông P4',
            'slug' => 'san-cau-long-p4',
            'address' => '123 Cầu Giấy',
            'price_per_hour' => 120000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->slot = TimeSlot::create([
            'sports_field_id' => $this->field->id,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'price_per_hour' => 120000,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_cancel_booking_with_reason(): void
    {
        Mail::fake();

        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(3),
            'total_price' => 120000,
            'status' => BookingStatus::Confirmed->value,
            'payment_status' => 'paid',
            'payment_amount' => 36000,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Sân bị bảo trì đột xuất theo thông báo của ban quản lý.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.refund.status', 'done');
        $this->assertEquals(36000, $this->customer->fresh()->wallet_balance);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancel_reason' => 'Sân bị bảo trì đột xuất theo thông báo của ban quản lý.',
            'refund_status' => 'done',
        ]);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'admin_cancel_booking',
            'subject_id' => (string) $booking->id,
        ]);
    }

    public function test_admin_cannot_cancel_already_cancelled_booking(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(3),
            'total_price' => 120000,
            'status' => BookingStatus::Cancelled->value,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/v1/admin/bookings/{$booking->id}/cancel", [
            'reason' => 'Huỷ lại',
        ]);

        $response->assertStatus(409);
    }

    public function test_manual_refund_endpoint_is_gone_because_refunds_go_to_the_wallet(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(3),
            'total_price' => 120000,
            'status' => BookingStatus::Cancelled->value,
        ]);

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/admin/bookings/{$booking->id}/refund", ['refund_status' => 'done'])->assertStatus(404);
    }

    public function test_admin_can_export_bookings_csv(): void
    {
        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(1),
            'total_price' => 120000,
            'status' => BookingStatus::Pending->value,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->get('/api/v1/admin/bookings/export-csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringContainsString('Mã đơn,Khách hàng', $content);
        $this->assertStringContainsString($this->customer->name, $content);
    }

    public function test_admin_can_filter_reviews_by_rating(): void
    {
        $booking1 = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->subDays(2),
            'total_price' => 100000,
            'status' => BookingStatus::Completed->value,
        ]);
        $booking2 = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->subDays(1),
            'total_price' => 100000,
            'status' => BookingStatus::Completed->value,
        ]);

        \App\Models\Review::create([
            'booking_id' => $booking1->id,
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'rating' => 5,
            'comment' => 'Sân rất đẹp và sạch sẽ!',
            'is_visible' => true,
        ]);

        \App\Models\Review::create([
            'booking_id' => $booking2->id,
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'rating' => 1,
            'comment' => 'Dịch vụ chưa tốt.',
            'is_visible' => true,
        ]);

        Sanctum::actingAs($this->admin);

        $res5 = $this->getJson('/api/v1/admin/reviews?rating=5');
        $res5->assertOk();
        $this->assertCount(1, $res5->json('data'));
        $this->assertSame(5, $res5->json('data.0.rating'));

        $res1 = $this->getJson('/api/v1/admin/reviews?rating=1');
        $res1->assertOk();
        $this->assertCount(1, $res1->json('data'));
        $this->assertSame(1, $res1->json('data.0.rating'));
    }

    public function test_admin_can_filter_inactive_fields(): void
    {
        $inactiveField = SportsField::create([
            'field_owner_id' => $this->field->field_owner_id,
            'field_type_id' => $this->field->field_type_id,
            'name' => 'Sân Cầu Lông Đang Tạm Đóng',
            'slug' => 'san-cau-long-dang-tam-dong-test',
            'address' => '123 Cầu Giấy',
            'price_per_hour' => 120000,
            'status' => 'approved',
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/v1/admin/fields?status=inactive');
        $res->assertOk();
        $fieldNames = collect($res->json('data'))->pluck('name')->all();
        $this->assertContains('Sân Cầu Lông Đang Tạm Đóng', $fieldNames);
    }

    public function test_admin_and_owner_can_export_statements_csv(): void
    {
        $owner = User::factory()->create(['role' => 'field_owner', 'is_active' => true]);
        $profile = FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Công Ty Thể Thao Hoàng Gia',
            'business_address' => '456 Lê Lợi, Q1',
            'business_phone' => '0912345678',
            'verification_status' => \App\Enums\VerificationStatus::Approved,
        ]);

        $statement = \App\Models\OwnerStatement::create([
            'owner_profile_id' => $profile->id,
            'period' => '2026-09',
            'booking_count' => 5,
            'gmv' => 1500000,
            'commission_rate' => 8,
            'commission_amount' => 120000,
            'transfer_code' => 'ST20260901',
            'due_at' => now()->addDays(5),
            'status' => 'unpaid',
        ]);

        // 1. Admin exports all statements CSV
        Sanctum::actingAs($this->admin);
        $resAdmin = $this->get('/api/v1/admin/statements/export-csv');
        $resAdmin->assertOk();
        $this->assertStringContainsString('text/csv', $resAdmin->headers->get('Content-Type'));
        $this->assertStringContainsString('Mã đối soát,Kỳ đối soát', $resAdmin->getContent());
        $this->assertStringContainsString('Công Ty Thể Thao Hoàng Gia', $resAdmin->getContent());

        // 2. Admin exports specific statement bookings CSV
        $resAdminDetail = $this->get("/api/v1/admin/statements/{$statement->id}/export-csv");
        $resAdminDetail->assertOk();
        $this->assertStringContainsString('text/csv', $resAdminDetail->headers->get('Content-Type'));
        $this->assertStringContainsString('Mã đơn,Khách hàng', $resAdminDetail->getContent());

        // 3. Owner exports their own statement bookings CSV
        Sanctum::actingAs($owner);
        $resOwnerDetail = $this->get("/api/v1/field-owner/statements/{$statement->id}/export-csv");
        $resOwnerDetail->assertOk();
        $this->assertStringContainsString('text/csv', $resOwnerDetail->headers->get('Content-Type'));
    }
}
