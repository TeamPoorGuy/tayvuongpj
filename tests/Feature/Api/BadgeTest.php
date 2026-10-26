<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Huy hiệu việc chờ trên menu theo vai trò. */
class BadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_gets_only_its_own_pending_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $field = SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
        Booking::create([
            'user_id' => $customer->id, 'sports_field_id' => $field->id, 'booking_date' => today()->toDateString(),
            'start_time' => '18:00', 'end_time' => '19:00', 'total_price' => 100000, 'status' => 'confirmed',
        ]);
        // Khách khiếu nại chủ sân: chủ sân cần phản hồi, admin cần xử lý.
        Complaint::create(['user_id' => $customer->id, 'direction' => 'customer_to_owner', 'against_user_id' => $owner->id, 'category' => 'other', 'content' => 'Cần hỗ trợ.', 'status' => 'open']);
        Withdrawal::create(['user_id' => $customer->id, 'amount' => 3000000, 'bank_bin' => '970436', 'bank_name' => 'Vietcombank', 'account_number' => '0123456789', 'account_holder' => 'LE VAN KHACH', 'status' => 'pending']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/me/badges')->assertOk()->assertExactJson(['data' => ['/bookings' => 1, '/owner/complaints' => 1, '/owner/bookings' => 1]]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/me/badges')->assertOk()->assertExactJson(['data' => []]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/me/badges')->assertOk()->assertExactJson(['data' => ['/admin/complaints' => 1, '/admin/withdrawals' => 1]]);
    }
}
