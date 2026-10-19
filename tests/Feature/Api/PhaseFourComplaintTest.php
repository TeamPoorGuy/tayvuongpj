<?php

namespace Tests\Feature\Api;

use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\Complaint;
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

class PhaseFourComplaintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer1;
    private User $customer2;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->customer1 = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->customer2 = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id,
            'business_name' => 'Sân Demo',
            'business_address' => 'Hà Nội',
            'business_phone' => '0901234567',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'CHU SAN',
            'verification_status' => 'approved',
        ]);

        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bd-p4']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 5', 'slug' => 's5-p4']);
        $field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Bóng P4',
            'slug' => 'san-bong-p4',
            'address' => '123 Cầu Giấy',
            'price_per_hour' => 100000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '17:00',
            'end_time' => '18:00',
            'price_per_hour' => 100000,
            'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->customer1->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->addDays(2),
            'total_price' => 100000,
            'status' => 'cancelled',
            'refund_status' => 'pending',
            'refund_amount' => 30000,
        ]);
    }

    public function test_customer_can_create_complaint_for_own_booking(): void
    {
        Sanctum::actingAs($this->customer1);

        $response = $this->postJson('/api/v1/customer/complaints', [
            'booking_id' => $this->booking->id,
            'subject_type' => 'booking',
            'category' => 'refund_pending',
            'content' => 'Tôi đã huỷ đơn trước 4 giờ nhưng quá 48h chưa nhận được tiền hoàn cọc.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.category', 'refund_pending');
        $response->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('complaints', [
            'user_id' => $this->customer1->id,
            'booking_id' => $this->booking->id,
            'category' => 'refund_pending',
            'status' => 'open',
        ]);
    }

    public function test_customer_cannot_complain_other_users_booking(): void
    {
        Sanctum::actingAs($this->customer2);

        $response = $this->postJson('/api/v1/customer/complaints', [
            'booking_id' => $this->booking->id,
            'subject_type' => 'booking',
            'category' => 'refund_pending',
            'content' => 'Khiếu nại giùm người khác.',
        ]);

        $response->assertStatus(403);
    }

    public function test_guest_can_submit_contact_form(): void
    {
        $response = $this->postJson('/api/v1/contact', [
            'subject_type' => 'contact',
            'category' => 'other',
            'content' => '[Khách vãng lai: guest@example.com] Tôi muốn liên hệ hợp tác quảng cáo.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('complaints', [
            'subject_type' => 'contact',
            'category' => 'other',
            'user_id' => null,
        ]);
    }

    public function test_admin_can_view_and_handle_complaint(): void
    {
        Mail::fake();

        $complaint = Complaint::create([
            'user_id' => $this->customer1->id,
            'booking_id' => $this->booking->id,
            'subject_type' => 'booking',
            'category' => 'refund_pending',
            'content' => 'Chủ sân không chịu hoàn tiền cọc.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($this->admin);

        // List
        $listRes = $this->getJson('/api/v1/admin/complaints?status=open');
        $listRes->assertOk();
        $listRes->assertJsonCount(1, 'data');

        // Handle
        $handleRes = $this->patchJson("/api/v1/admin/complaints/{$complaint->id}/handle", [
            'status' => 'resolved',
            'resolution' => 'Admin đã làm việc với chủ sân và chủ sân đã chuyển khoản hoàn tiền thành công.',
        ]);

        $handleRes->assertOk();
        $handleRes->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'resolved',
            'handled_by' => $this->admin->id,
        ]);

        // Check AdminLog recorded
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'handle_complaint',
            'subject_id' => (string) $complaint->id,
        ]);
    }
}
