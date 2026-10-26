<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFourDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        FieldOwnerProfile::create([
            'user_id' => $this->owner->id,
            'business_name' => 'Cơ sở thể thao Đại Dương',
            'business_address' => 'Hà Nội',
            'business_phone' => '0901234567',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'CHU SAN',
            'verification_status' => 'approved',
        ]);

        $cat = SportCategory::create(['name' => 'Bóng bàn', 'slug' => 'bong-ban-p4']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Bàn đơn', 'slug' => 'ban-don-p4']);
        $field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Bàn Bóng Bàn VIP',
            'slug' => 'ban-bong-ban-vip',
            'address' => '123 Cầu Giấy',
            'price_per_hour' => 80000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'price_per_hour' => 80000,
            'is_active' => true,
        ]);

        Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today(),
            'total_price' => 80000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_amount' => 24000,
            'commission_rate' => 8,
            // commission_amount để null như thực tế (chưa chốt đối soát cuối tháng)
        ]);

        Complaint::create([
            'user_id' => $this->customer->id,
            'subject_type' => 'booking',
            'category' => 'other',
            'content' => 'Khiếu nại kiểm tra hàng đợi.',
            'status' => 'open',
        ]);
    }

    public function test_admin_can_view_dashboard_with_kpis_and_action_queue(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'period',
                'from_date',
                'to_date',
                'kpis' => [
                    'gmv',
                    'paid_amount',
                    'commission_accrued',
                    'commission_collected',
                    'commission_debt',
                    'total_bookings',
                    'cancel_rate',
                    'no_show_rate',
                    'new_users',
                    'new_owners',
                ],
                'daily_series',
                'top_fields',
                'action_queue' => [
                    'pending_owners',
                    'pending_fields',
                    'open_complaints',
                    'pending_withdrawals',
                ],
            ],
        ]);

        $this->assertEquals(80000, $response->json('data.kpis.gmv'));
        // Hoa hồng tự động tính tại chỗ qua computeCommission() = 80000 * 8% = 6400
        $this->assertEquals(6400, $response->json('data.kpis.commission_accrued'));
        $this->assertEquals(1, $response->json('data.action_queue.open_complaints'));
    }

    public function test_admin_can_filter_dashboard_by_last_month(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/dashboard?period=last_month');

        $response->assertOk();
        $response->assertJsonPath('data.period', 'last_month');
        // GMV tháng trước = 0 vì đơn mẫu là today()
        $this->assertEquals(0, $response->json('data.kpis.gmv'));
        $this->assertEquals(0, $response->json('data.kpis.commission_accrued'));
    }

    public function test_admin_cannot_use_invalid_dashboard_period(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/dashboard?period=custom');
        $response->assertUnprocessable();
    }
}
