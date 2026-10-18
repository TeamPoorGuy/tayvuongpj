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
        $response->assertJsonPath('data.refund.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancel_reason' => 'Sân bị bảo trì đột xuất theo thông báo của ban quản lý.',
            'refund_status' => 'pending',
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

    public function test_admin_can_update_refund_status(): void
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $this->field->id,
            'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDays(3),
            'total_price' => 120000,
            'status' => BookingStatus::Cancelled->value,
            'refund_status' => 'pending',
            'refund_amount' => 36000,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/bookings/{$booking->id}/refund", [
            'refund_status' => 'done',
            'refund_amount' => 36000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.refund.status', 'done');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'refund_status' => 'done',
            'refund_amount' => 36000,
        ]);

        $this->assertNotNull(Booking::find($booking->id)->refunded_at);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'update_booking_refund',
            'subject_id' => (string) $booking->id,
        ]);
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
}
