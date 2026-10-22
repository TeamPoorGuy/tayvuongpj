<?php

namespace Tests\Feature\Api;

use App\Models\AdminLog;
use App\Models\FieldOwnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Giấy tờ chủ sân: che CCCD, GPKD tuỳ chọn, tự xoá khi bị từ chối, log khi admin xem. */
class OwnerDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'customer'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function profile(User $user, string $status = 'pending', $verifiedAt = null, string $idNumber = '012345678901'): FieldOwnerProfile
    {
        return FieldOwnerProfile::create([
            'user_id' => $user->id, 'owner_name' => 'Chủ Sân', 'owner_id_number' => $idNumber,
            'business_name' => 'CLB', 'business_address' => 'Hà Nội', 'business_phone' => '0900000009',
            'verification_status' => $status, 'verified_at' => $verifiedAt,
        ]);
    }

    public function test_applicant_sees_masked_id_number_but_admin_sees_full(): void
    {
        $user = $this->user();
        $this->profile($user);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/customer/owner-application')->assertOk()
            ->assertJsonPath('data.owner_id_number', '********8901');

        Sanctum::actingAs($this->user('admin'));
        $this->getJson('/api/v1/admin/field-owners')->assertOk()
            ->assertJsonPath('data.0.owner_id_number', '012345678901');
    }

    public function test_application_without_business_license_is_accepted(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->user());

        $this->post('/api/v1/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn A', 'owner_id_number' => '012345678901', 'business_name' => 'CLB Test',
            'business_address' => '1 Đường A', 'business_phone' => '0901234567',
            'bank_name' => 'VCB', 'bank_account_number' => '123456789', 'bank_account_holder' => 'NGUYEN VAN A',
            'accept_terms' => '1',
            'documents' => [
                'id_front' => UploadedFile::fake()->image('front.png'),
                'id_back' => UploadedFile::fake()->image('back.png'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_resubmit_without_id_number_keeps_the_stored_one(): void
    {
        Storage::fake('local');
        $user = $this->user();
        $this->profile($user, 'rejected', now()->subDay());
        Sanctum::actingAs($user);

        $this->post('/api/v1/customer/owner-application', [
            'owner_name' => 'Nguyễn Văn A', 'business_name' => 'CLB Test',
            'business_address' => '1 Đường A', 'business_phone' => '0901234567',
            'bank_name' => 'VCB', 'bank_account_number' => '123456789', 'bank_account_holder' => 'NGUYEN VAN A',
            'accept_terms' => '1',
            'documents' => [
                'id_front' => UploadedFile::fake()->image('front.png'),
                'id_back' => UploadedFile::fake()->image('back.png'),
            ],
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('012345678901', $user->fieldOwnerProfile()->first()->owner_id_number);
    }

    public function test_purge_removes_documents_of_rejected_profiles_after_30_days(): void
    {
        Storage::fake('local');
        $old = $this->profile($this->user(), 'rejected', now()->subDays(31));
        $recent = $this->profile($this->user(), 'rejected', now()->subDays(29), '012345678902');
        foreach ([$old, $recent] as $profile) {
            Storage::disk('local')->put("licenses/{$profile->id}.jpg", 'x');
            $profile->documents()->create(['type' => 'id_front', 'path' => "licenses/{$profile->id}.jpg", 'original_name' => 'a.jpg']);
        }

        Artisan::call('owners:purge-documents');

        $this->assertSame(0, $old->documents()->count());
        Storage::disk('local')->assertMissing("licenses/{$old->id}.jpg");
        $this->assertSame(1, $recent->documents()->count());
        Storage::disk('local')->assertExists("licenses/{$recent->id}.jpg");
        $this->assertNotNull($old->fresh());
    }

    public function test_admin_opening_a_document_is_logged(): void
    {
        Storage::fake('local');
        $profile = $this->profile($this->user());
        Storage::disk('local')->put('licenses/x.jpg', 'x');
        $doc = $profile->documents()->create(['type' => 'id_front', 'path' => 'licenses/x.jpg', 'original_name' => 'x.jpg']);

        Sanctum::actingAs($this->user('admin'));
        $this->get("/api/v1/admin/field-owners/{$profile->id}/documents/{$doc->id}")->assertOk();

        $this->assertDatabaseHas('admin_logs', ['action' => 'view_owner_document']);
    }
}
