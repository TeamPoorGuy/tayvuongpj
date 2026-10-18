<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseFivePaymentAndUiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private User $admin;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id,
            'owner_name' => 'Chủ Sân Test',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Bóng A',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000009',
            'business_license' => 'GPKD-1',
            'verification_status' => 'approved',
            'verified_at' => now(),
            'bank_name' => 'Vietcombank',
            'bank_bin' => '970436',
            'bank_account_number' => '1029384756',
            'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);

        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);

        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 7', 'slug' => 'san-7']);
        $field = SportsField::create([
            'field_owner_id' => $this->owner->id,
            'field_type_id' => $type->id,
            'name' => 'Sân Test',
            'slug' => 'san-test',
            'address' => 'Cầu Giấy',
            'price_per_hour' => 200000,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $slot = TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '17:30', 'end_time' => '19:00', 'is_active' => true]);
        $this->booking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slot->id,
            'booking_date' => today()->addDays(3),
            'status' => 'pending',
            'total_price' => 300000,
        ]);
    }

    public function test_owner_profile_returns_bank_bin_and_can_update_bank_info(): void
    {
        Sanctum::actingAs($this->owner);

        $res = $this->getJson('/api/v1/field-owner/profile');
        $res->assertOk();
        $this->assertSame('970436', $res->json('data.bank_bin'));

        // Update profile with another bank and auto-guessed BIN
        $updateRes = $this->putJson('/api/v1/field-owner/profile', [
            'owner_name' => 'Chủ Sân Test',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Bóng A',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000009',
            'bank_name' => 'MBBank',
            'bank_account_number' => '88888888',
            'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);
        $updateRes->assertOk();
        $this->assertSame('970422', $updateRes->json('data.bank_bin'));
    }

    public function test_booking_payment_with_proof_upload_and_streaming(): void
    {
        // 1. Owner approves booking
        Sanctum::actingAs($this->owner);
        $approveRes = $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve");
        $approveRes->assertOk();

        // 2. Customer views booking detail: has bank_bin for VietQR
        Sanctum::actingAs($this->customer);
        $showRes = $this->getJson("/api/v1/customer/bookings/{$this->booking->id}");
        $showRes->assertOk();
        $this->assertSame('970436', $showRes->json('data.bank.bank_bin'));
        $this->assertNull($showRes->json('data.payment.proof_url'));

        // 3. Customer reports payment with image proof
        $fakeImage = UploadedFile::fake()->image('receipt.png', 400, 400);
        $reportRes = $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $fakeImage,
        ]);
        $reportRes->assertOk();
        $this->assertNotNull($reportRes->json('data.payment.proof_url'));

        // 4. Access payment proof endpoint:
        // Customer can view
        $proofRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $proofRes->assertOk();

        // Field Owner can view
        Sanctum::actingAs($this->owner);
        $ownerProofRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $ownerProofRes->assertOk();

        // Admin can view
        Sanctum::actingAs($this->admin);
        $adminProofRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $adminProofRes->assertOk();

        // Another customer cannot view (403)
        $otherCustomer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        Sanctum::actingAs($otherCustomer);
        $otherProofRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $otherProofRes->assertStatus(403);
    }

    public function test_invalid_bank_bin_is_rejected_by_validation(): void
    {
        Sanctum::actingAs($this->owner);

        $res = $this->putJson('/api/v1/field-owner/profile', [
            'owner_name' => 'Chủ Sân Test',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Bóng A',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000009',
            'bank_name' => 'Vietcombank',
            'bank_bin' => '999999', // Invalid BIN
            'bank_account_number' => '88888888',
            'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['bank_bin']);

        // Valid BIN succeeds
        $validRes = $this->putJson('/api/v1/field-owner/profile', [
            'owner_name' => 'Chủ Sân Test',
            'owner_id_number' => '001099001234',
            'business_name' => 'Sân Bóng A',
            'business_address' => 'Hà Nội',
            'business_phone' => '0900000009',
            'bank_name' => 'Techcombank',
            'bank_bin' => '970407',
            'bank_account_number' => '88888888',
            'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);
        $validRes->assertOk();
        $this->assertSame('970407', $validRes->json('data.bank_bin'));
    }

    public function test_payment_proof_lifecycle_cleans_up_old_file_and_leaves_no_orphans(): void
    {
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve")->assertOk();

        Sanctum::actingAs($this->customer);

        // Upload first proof
        $file1 = UploadedFile::fake()->image('proof1.png', 200, 200);
        $res1 = $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $file1,
        ]);
        $res1->assertOk();
        $path1 = $this->booking->fresh()->payment_proof_path;
        $this->assertNotNull($path1);
        Storage::disk('local')->assertExists($path1);

        // Owner reports not received, booking goes back to awaiting
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/payment", ['received' => false])->assertOk();

        // Customer re-uploads with second proof
        Sanctum::actingAs($this->customer);
        $file2 = UploadedFile::fake()->image('proof2.png', 200, 200);
        $res2 = $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $file2,
        ]);
        $res2->assertOk();
        $path2 = $this->booking->fresh()->payment_proof_path;
        $this->assertNotNull($path2);
        $this->assertNotSame($path1, $path2);

        // Old file deleted, new file exists
        Storage::disk('local')->assertMissing($path1);
        Storage::disk('local')->assertExists($path2);

        // Failed submission due to invalid status does NOT store new orphan files
        $file3 = UploadedFile::fake()->image('proof3.png', 200, 200);
        $res3 = $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $file3,
        ]);
        $res3->assertStatus(409); // Already reported, cannot report again

        // Total files in payment_proofs is still exactly 1 ($path2)
        $allFiles = Storage::disk('local')->allFiles('payment_proofs');
        $this->assertCount(1, $allFiles);
        $this->assertSame($path2, $allFiles[0]);
    }

    public function test_customer_bookings_loads_owner_phone_and_business_phone(): void
    {
        Sanctum::actingAs($this->customer);
        $res = $this->getJson('/api/v1/customer/bookings');
        $res->assertOk();

        $bookingData = $res->json('data.0');
        $this->assertNotNull($bookingData);
        $this->assertSame('0900000009', $bookingData['field']['owner']['business']['business_phone']);
    }

    public function test_owner_records_no_show_when_guest_does_not_check_in(): void
    {
        // Setup past booking that was confirmed
        $field = SportsField::first();
        $pastSlot = TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '07:00', 'end_time' => '08:30', 'is_active' => true]);
        $confirmedBooking = Booking::create([
            'user_id' => $this->customer->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $pastSlot->id,
            'booking_date' => today()->subDays(1),
            'status' => 'confirmed',
            'total_price' => 200000,
            'confirmed_at' => now()->subDays(2),
        ]);

        $initialScore = $this->customer->fresh()->trust_score;

        Sanctum::actingAs($this->owner);
        $noShowRes = $this->patchJson("/api/v1/field-owner/bookings/{$confirmedBooking->id}/no-show");
        $noShowRes->assertOk();
        $this->assertSame('completed', $noShowRes->json('data.status'));
        $this->assertTrue($noShowRes->json('data.no_show'));

        // Customer lost 25 trust score points
        $this->assertSame($initialScore - 25, $this->customer->fresh()->trust_score);
    }

    public function test_admin_can_view_booking_with_payment_proof_and_gets_404_when_no_proof(): void
    {
        // 1. Without proof: proof_url is null
        Sanctum::actingAs($this->admin);
        $adminRes = $this->getJson('/api/v1/admin/bookings');
        $adminRes->assertOk();
        $this->assertNull($adminRes->json('data.0.payment.proof_url'));

        // Accessing proof endpoint directly returns 404
        $notFoundRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $notFoundRes->assertNotFound();

        // 2. Owner approves and customer uploads proof
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve")->assertOk();

        Sanctum::actingAs($this->customer);
        $file = UploadedFile::fake()->image('test_proof.jpg', 300, 300);
        $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $file,
        ])->assertOk();

        // 3. Admin list returns payment.proof_url
        Sanctum::actingAs($this->admin);
        $listRes = $this->getJson('/api/v1/admin/bookings');
        $listRes->assertOk();
        $proofUrl = $listRes->json('data.0.payment.proof_url');
        $this->assertNotNull($proofUrl);

        // Admin streams the proof image successfully
        $proofRes = $this->getJson("/api/v1/bookings/{$this->booking->id}/payment-proof");
        $proofRes->assertOk();
    }

    public function test_customer_cancellation_data_fallbacks_to_owner_user_phone(): void
    {
        // Set business_phone to null in profile, but keep owner->phone
        $this->owner->update(['phone' => '0987654321']);
        $this->owner->fieldOwnerProfile->update(['business_phone' => null]);

        Sanctum::actingAs($this->customer);
        $res = $this->getJson('/api/v1/customer/bookings');
        $res->assertOk();

        $bookingData = $res->json('data.0');
        $this->assertNotNull($bookingData);
        $this->assertNull($bookingData['field']['owner']['business']['business_phone']);
        $this->assertSame('0987654321', $bookingData['field']['owner']['phone']);
    }

    public function test_orphaned_proof_file_is_deleted_when_payment_report_fails(): void
    {
        Storage::fake('local');

        // Set payment_due_at in the past so report throws ConflictException
        $this->booking->update([
            'payment_status' => 'awaiting',
            'payment_due_at' => now()->subMinutes(10),
        ]);

        Sanctum::actingAs($this->customer);
        $file = UploadedFile::fake()->image('expired_proof.jpg', 300, 300);

        $res = $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", [
            'option' => 'deposit',
            'proof' => $file,
        ]);

        $res->assertStatus(409);

        // Verify that no orphaned file remains in payment_proofs directory
        $files = Storage::disk('local')->files('payment_proofs');
        $this->assertEmpty($files, 'Expected orphaned payment proof to be deleted when report fails.');
    }
}

