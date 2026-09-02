<?php

namespace Tests\Feature\Api;

use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LayeredApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_returns_the_standard_data_envelope(): void
    {
        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonStructure(['data' => ['categories', 'featured_fields']]);
    }

    public function test_protected_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/bookings')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Bạn cần đăng nhập để tiếp tục.');
    }

    public function test_role_middleware_rejects_a_customer_from_admin_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_inactive_account_is_rejected_by_protected_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => false]));

        $this->getJson('/api/v1/customer/bookings')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_session_login_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'api-customer@example.test',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/',
        ])->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'customer');

        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_can_create_a_booking_for_an_available_field_slot(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        [$field, $slot] = $this->createApprovedFieldWithSlot();

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => now()->addDay()->toDateString(),
            'notes' => 'Kiểm thử API',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.field.id', $field->id);

        $this->assertDatabaseHas('bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'status' => 'pending',
        ]);
    }

    public function test_customer_cannot_use_a_time_slot_from_another_field(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        [$field] = $this->createApprovedFieldWithSlot('san-a');
        [, $otherSlot] = $this->createApprovedFieldWithSlot('san-b');

        $this->postJson('/api/v1/customer/bookings', [
            'sports_field_id' => $field->id,
            'time_slot_id' => $otherSlot->id,
            'booking_date' => now()->addDay()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_public_cannot_read_slots_of_an_unapproved_field(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-cho-duyet');
        $field->update(['status' => 'pending']);

        $this->getJson("/api/v1/fields/{$field->id}/available-slots?date=".now()->addDay()->toDateString())
            ->assertNotFound();
    }

    public function test_owner_cannot_update_another_owners_field(): void
    {
        [$field] = $this->createApprovedFieldWithSlot('san-owner-khac');
        $otherOwner = User::factory()->create(['role' => 'field_owner', 'is_active' => true]);
        Sanctum::actingAs($otherOwner);

        $this->putJson("/api/v1/field-owner/fields/{$field->id}", [
            'name' => 'Tên không được cập nhật',
            'field_type_id' => $field->field_type_id,
            'address' => 'Đà Nẵng',
            'price_per_hour' => 250000,
        ])->assertForbidden();

        $this->assertDatabaseHas('sports_fields', [
            'id' => $field->id,
            'name' => $field->name,
        ]);
    }

    public function test_admin_account_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$admin->id}/toggle")
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    /** @return array{SportsField, TimeSlot} */
    private function createApprovedFieldWithSlot(string $slug = 'san-api'): array
    {
        $owner = User::factory()->create(['role' => 'field_owner', 'is_active' => true]);
        $category = SportCategory::create([
            'name' => 'Bóng đá',
            'slug' => "bong-da-{$slug}",
            'is_active' => true,
        ]);
        $fieldType = FieldType::create([
            'sport_category_id' => $category->id,
            'name' => 'Sân 5 người',
            'slug' => "san-5-{$slug}",
            'is_active' => true,
        ]);
        $field = SportsField::create([
            'field_owner_id' => $owner->id,
            'field_type_id' => $fieldType->id,
            'name' => "Sân {$slug}",
            'slug' => $slug,
            'address' => 'Đà Nẵng',
            'price_per_hour' => 200000,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $slot = TimeSlot::create([
            'sports_field_id' => $field->id,
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'is_active' => true,
        ]);

        return [$field, $slot];
    }
}
