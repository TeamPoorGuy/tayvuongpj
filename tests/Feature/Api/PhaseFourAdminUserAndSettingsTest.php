<?php

namespace Tests\Feature\Api;

use App\Models\AdminLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFourAdminUserAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 90]);
    }

    public function test_admin_can_lock_user_with_reason_and_send_mail(): void
    {
        Mail::fake();

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/users/{$this->customer->id}/toggle", [
            'reason' => 'Vi phạm quy chế đặt sân nhiều lần.',
        ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('data.is_active'));
        $this->assertFalse((bool) $this->customer->fresh()->is_active);

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'toggle_user',
            'subject_id' => (string) $this->customer->id,
        ]);
    }

    public function test_admin_cannot_lock_user_without_reason(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/v1/admin/users/{$this->customer->id}/toggle", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason']);
    }


    public function test_admin_can_view_user_detail(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/v1/admin/users/{$this->customer->id}/detail");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'user',
                'stats' => ['total_bookings', 'cancelled_bookings', 'no_show_bookings', 'complaints_count'],
                'recent_bookings',
                'recent_trust_scores',
            ],
        ]);
    }

    public function test_admin_can_get_and_update_settings(): void
    {
        Sanctum::actingAs($this->admin);

        $getRes = $this->getJson('/api/v1/admin/settings');
        $getRes->assertOk();
        $getRes->assertJsonPath('data.commission_percent', 8);

        $putRes = $this->putJson('/api/v1/admin/settings', [
            'commission_percent' => 10,
            'statement_due_days' => 10,
            'support_hotline' => '1900 9999',
        ]);

        $putRes->assertOk();
        $this->assertEquals(10, Settings::getInt('commission_percent'));
        $this->assertEquals(10, Settings::getInt('statement_due_days'));
        $this->assertEquals('1900 9999', Settings::getString('support_hotline'));

        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'update_settings',
        ]);
    }

    public function test_admin_can_view_admin_logs(): void
    {
        AdminLog::record('test_action', null, ['foo' => 'bar'], $this->admin);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/admin/logs?action=test_action');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'test_action');
    }
}
