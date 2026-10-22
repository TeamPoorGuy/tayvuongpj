<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Ảnh chứng cứ của khiếu nại và kháng cáo "tôi đã đến sân" khi bị ghi vắng mặt. */
class ComplaintEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $customer;

    private User $stranger;

    private SportsField $field;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
            'bank_name' => 'Vietcombank', 'bank_bin' => '970436', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'NGUYEN CHU SAN',
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->stranger = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id,
            'booking_date' => today()->subDay()->toDateString(), 'start_time' => '18:00', 'end_time' => '20:00',
            'total_price' => 200000, 'status' => 'completed', 'checked_in_at' => now()->subDay(),
        ]);
    }

    private function photos(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("anh{$i}.jpg", 300, 300), range(1, $n));
    }

    public function test_complaint_keeps_up_to_three_photos_and_only_parties_and_admin_can_open_them(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.', 'photos' => $this->photos(4)])
            ->assertStatus(422)->assertJsonValidationErrors('photos');
        $this->assertSame(0, Complaint::count());

        $response = $this->post('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.', 'photos' => $this->photos(2)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonCount(2, 'data.attachments')->assertJsonPath('data.attachments.0.by', 'complainant');
        $attachment = ComplaintAttachment::first();
        Storage::disk('local')->assertExists($attachment->path);

        $url = "/api/v1/complaint-attachments/{$attachment->id}";
        $this->get($url)->assertOk();
        Sanctum::actingAs($this->owner);
        $this->get($url)->assertOk();
        Sanctum::actingAs($this->admin);
        $this->get($url)->assertOk();
        Sanctum::actingAs($this->stranger);
        $this->getJson($url)->assertStatus(403);
        $this->assertNotNull($response->json('data.attachments.0.url'));
    }

    public function test_non_image_files_are_rejected(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($this->customer);

        $this->post('/api/v1/customer/complaints', [
            'booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.',
            'photos' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('photos.0');
    }

    public function test_respondent_attaches_own_photos_within_their_own_limit_of_three(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($this->customer);
        $id = $this->post('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.', 'photos' => $this->photos(3)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        Sanctum::actingAs($this->owner);
        $this->post("/api/v1/field-owner/complaints/{$id}/respond", ['response' => 'Đèn vẫn sáng bình thường.', 'photos' => $this->photos(3)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(6, 'data.attachments');

        $this->assertSame(3, ComplaintAttachment::where('user_id', $this->owner->id)->count());
        $this->getJson('/api/v1/field-owner/complaints')->assertOk()->assertJsonPath('data.0.attachments.5.by', 'respondent');
    }

    public function test_guest_contact_form_cannot_attach_photos(): void
    {
        $this->post('/api/v1/contact', ['category' => 'other', 'content' => 'Tôi cần hỗ trợ.', 'subject_type' => 'contact', 'photos' => $this->photos(1)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_the_other_side_sees_photos_on_the_booking(): void
    {
        $booking = $this->booking(['status' => 'completed']);
        Sanctum::actingAs($this->owner);
        $this->post('/api/v1/field-owner/complaints', ['booking_id' => $booking->id, 'category' => 'property_damage', 'content' => 'Khách làm vỡ đèn.', 'photos' => $this->photos(1)], ['Accept' => 'application/json'])
            ->assertCreated();

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/customer/bookings')->assertOk()->assertJsonCount(1, 'data.0.complaint_against.attachments');
    }

    public function test_no_show_appeal_needs_a_no_show_booking_inside_the_appeal_window(): void
    {
        $payload = fn (Booking $b) => ['booking_id' => $b->id, 'category' => 'wrong_no_show', 'content' => 'Tôi đã đến sân đúng giờ.'];
        Sanctum::actingAs($this->customer);

        $attended = $this->booking();
        $this->postJson('/api/v1/customer/complaints', $payload($attended))->assertStatus(422);

        $late = $this->booking(['checked_in_at' => null, 'no_show_at' => now()->subHours(49)]);
        $this->postJson('/api/v1/customer/complaints', $payload($late))->assertStatus(422);

        $fresh = $this->booking(['checked_in_at' => null, 'no_show_at' => now()->subHours(47)]);
        $this->postJson('/api/v1/customer/complaints', $payload($fresh))->assertCreated();

        $this->getJson('/api/v1/customer/bookings')->assertOk();
        $this->assertNotNull($this->getJson('/api/v1/customer/bookings')->json('data.0.no_show_appeal_until'));
    }
}
