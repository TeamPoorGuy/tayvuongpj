<?php

namespace Tests\Feature\Api;

use App\Enums\VerificationStatus;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\Review;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AdminService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseZeroFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::clearCache();
    }

    protected function tearDown(): void
    {
        Settings::clearCache();
        parent::tearDown();
    }

    public function test_public_settings_endpoint_returns_expected_structure_and_defaults(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'commission_percent',
                    'statement_due_days',
                    'deposit_percent',
                    'payment_window_hours',
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

        $this->assertSame(8, $response->json('data.commission_percent'));
        $this->assertSame(7, $response->json('data.statement_due_days'));
        $this->assertSame(30, $response->json('data.deposit_percent'));
    }

    public function test_settings_helper_db_overrides_config_and_caches(): void
    {
        $this->assertSame(8, Settings::getInt('commission_percent'));

        Settings::set('commission_percent', 12);
        $this->assertSame(12, Settings::getInt('commission_percent'));

        $response = $this->getJson('/api/v1/settings/public');
        $response->assertOk();
        $this->assertSame(12, $response->json('data.commission_percent'));

        // Test fallback to default when key does not exist
        $this->assertSame(999, Settings::getInt('non_existent_key', 999));
        $this->assertSame('custom', Settings::getString('another_missing_key', 'custom'));
    }

    public function test_admin_logs_recorded_for_admin_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $adminService = app(AdminService::class);

        // 1. Toggle user
        $adminService->toggleUser($customer);
        $this->assertDatabaseHas('admin_logs', [
            'action' => 'toggle_user',
            'subject_type' => $customer->getMorphClass(),
            'subject_id' => (string) $customer->id,
        ]);

        // 2. Verify owner profile
        $ownerProfile = FieldOwnerProfile::create([
            'user_id' => $customer->id,
            'business_name' => 'Sân Bóng Test',
            'business_address' => '123 Đường Test',
            'business_phone' => '0901234567',
            'verification_status' => VerificationStatus::Pending,
        ]);

        $adminService->verifyOwner($ownerProfile, VerificationStatus::Approved->value, null, $admin);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'verify_owner',
            'subject_type' => $ownerProfile->getMorphClass(),
            'subject_id' => (string) $ownerProfile->id,
        ]);

        // 3. Category & FieldType
        $category = $adminService->createCategory(['name' => 'Bóng Đá Futsal']);
        $this->assertDatabaseHas('admin_logs', [
            'action' => 'create_category',
            'subject_type' => $category->getMorphClass(),
            'subject_id' => (string) $category->id,
        ]);

        $fieldType = $adminService->createFieldType([
            'sport_category_id' => $category->id,
            'name' => 'Sân 5 người',
        ]);
        $this->assertDatabaseHas('admin_logs', [
            'action' => 'create_field_type',
            'subject_type' => $fieldType->getMorphClass(),
            'subject_id' => (string) $fieldType->id,
        ]);

        // 4. Verify field
        $field = SportsField::create([
            'field_owner_id' => $customer->id,
            'field_type_id' => $fieldType->id,
            'name' => 'Sân Cỏ Nhân Tạo Sao Mai',
            'slug' => 'san-co-nhan-tao-sao-mai',
            'address' => '456 Lê Lợi',
            'price_per_hour' => 200000,
            'status' => 'pending',
            'is_active' => true,
        ]);

        $adminService->verifyField($field, 'approved');
        $this->assertDatabaseHas('admin_logs', [
            'action' => 'verify_field',
            'subject_type' => $field->getMorphClass(),
            'subject_id' => (string) $field->id,
        ]);

        // 5. Toggle review
        $review = Review::create([
            'user_id' => $customer->id,
            'sports_field_id' => $field->id,
            'rating' => 5,
            'comment' => 'Sân rất đẹp',
            'is_visible' => true,
        ]);

        $adminService->toggleReview($review);
        $this->assertDatabaseHas('admin_logs', [
            'action' => 'toggle_review',
            'subject_type' => $review->getMorphClass(),
            'subject_id' => (string) $review->id,
        ]);

        // 6. Cancel match via Admin Controller / Service
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '18:00',
            'end_time' => '19:30',
            'price_per_hour' => 200000,
            'is_active' => true,
        ]);

        $match = MatchRequest::create([
            'user_id' => $customer->id,
            'sport_category_id' => $category->id,
            'sports_field_id' => $field->id,
            'title' => 'Giao lưu bóng đá phong trào',
            'play_date' => today()->addDays(2),
            'start_time' => '18:00',
            'end_time' => '19:30',
            'skill_level' => 'any',
            'players_needed' => 4,
            'contact_phone' => '0987654321',
            'status' => 'open',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/matches/{$match->id}", ['reason' => 'Vi phạm nội quy'])
            ->assertOk();

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'cancel_match',
            'subject_type' => $match->getMorphClass(),
            'subject_id' => (string) $match->id,
        ]);
    }
}
