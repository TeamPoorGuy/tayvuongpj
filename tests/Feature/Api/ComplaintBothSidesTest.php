<?php

namespace Tests\Feature\Api;

use App\Mail\ComplaintFiled;
use App\Mail\ComplaintHandled;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Khiếu nại hai chiều: chủ sân khiếu nại khách, bên bị khiếu nại được báo và phản hồi, admin quyết định. */
class ComplaintBothSidesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $otherOwner;

    private User $customer;

    private SportsField $field;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->owner = $this->makeOwner('012345678901');
        $this->otherOwner = $this->makeOwner('012345678902');
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 100]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function makeOwner(string $idNumber): User
    {
        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => $idNumber, 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
            'bank_name' => 'Vietcombank', 'bank_bin' => '970436', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'NGUYEN CHU SAN',
        ]);

        return $owner;
    }

    private function playedBooking(array $attributes = []): Booking
    {
        $payment = Payment::create([
            'user_id' => $this->customer->id, 'code' => 'SH'.Str::upper(Str::random(6)), 'option' => 'deposit',
            'total' => 200000, 'amount' => 60000, 'status' => 'paid', 'due_at' => now(), 'paid_at' => now(),
        ]);

        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment->id,
            'booking_date' => now()->subMonth()->startOfMonth()->addDays(5)->toDateString(),
            'start_time' => '18:00', 'end_time' => '20:00', 'total_price' => 200000, 'status' => 'completed',
            'checked_in_at' => now()->subMonth(), 'payment_status' => 'paid', 'payment_option' => 'deposit',
            'payment_amount' => 60000, 'commission_rate' => 8,
        ]);
    }

    private function ownerComplains(Booking $booking, string $category = 'property_damage')
    {
        Sanctum::actingAs($this->owner);

        return $this->postJson('/api/v1/field-owner/complaints', [
            'booking_id' => $booking->id, 'category' => $category, 'content' => 'Khách làm vỡ đèn sân và không đền.',
        ]);
    }

    public function test_owner_complaint_names_the_customer_as_respondent_and_notifies_them(): void
    {
        $booking = $this->playedBooking();

        $this->ownerComplains($booking)->assertCreated()
            ->assertJsonPath('data.direction', 'owner_to_customer')
            ->assertJsonPath('data.against_user_id', $this->customer->id);

        Mail::assertQueued(ComplaintFiled::class, fn ($m) => $m->hasTo($this->customer->email));
        $this->assertNotNull(Complaint::sole()->respond_by);
        $this->assertSame(100, $this->customer->refresh()->trust_score, 'Chưa quyết định thì chưa trừ điểm.');
    }

    public function test_owner_cannot_complain_about_future_other_owners_or_customer_only_categories(): void
    {
        $future = $this->playedBooking(['booking_date' => now()->addDay()->toDateString(), 'status' => 'confirmed', 'checked_in_at' => null]);
        $this->ownerComplains($future)->assertStatus(422);

        $played = $this->playedBooking();

        Sanctum::actingAs($this->otherOwner);
        $this->postJson('/api/v1/field-owner/complaints', ['booking_id' => $played->id, 'category' => 'property_damage', 'content' => 'Không phải sân của tôi.'])
            ->assertStatus(403);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $played->id, 'category' => 'property_damage', 'content' => 'Loại của chủ sân.'])
            ->assertStatus(422);
    }

    public function test_customer_complaint_notifies_owner_who_can_respond_once(): void
    {
        $booking = $this->playedBooking();
        Sanctum::actingAs($this->customer);
        $id = $this->postJson('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Sân không đúng mô tả.'])
            ->assertCreated()->assertJsonPath('data.against_user_id', $this->owner->id)->json('data.id');
        Mail::assertQueued(ComplaintFiled::class, fn ($m) => $m->hasTo($this->owner->email));

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/field-owner/complaints')->assertOk()->assertJsonPath('data.0.can_respond', true);

        Sanctum::actingAs($this->otherOwner);
        $this->postJson("/api/v1/field-owner/complaints/{$id}/respond", ['response' => 'Tôi không liên quan.'])->assertStatus(403);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/field-owner/complaints/{$id}/respond", ['response' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/field-owner/complaints/{$id}/respond", ['response' => 'Sân đúng mô tả, đèn đã thay hôm trước.'])
            ->assertOk()->assertJsonPath('data.can_respond', false);
        $this->postJson("/api/v1/field-owner/complaints/{$id}/respond", ['response' => 'Bổ sung thêm ý kiến.'])->assertStatus(409);
        $this->assertNotNull(Complaint::sole()->responded_at);
    }

    public function test_respondent_cannot_respond_after_the_complaint_is_closed(): void
    {
        $booking = $this->playedBooking();
        $this->ownerComplains($booking)->assertCreated();
        $complaint = Complaint::sole();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/complaints/{$complaint->id}/handle", ['status' => 'rejected', 'resolution' => 'Không đủ chứng cứ.'])->assertOk();

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/complaints/{$complaint->id}/respond", ['response' => 'Tôi muốn giải thích.'])->assertStatus(409);
    }

    public function test_accepted_owner_complaint_penalizes_customer_and_both_sides_are_mailed(): void
    {
        $booking = $this->playedBooking();
        $this->ownerComplains($booking)->assertCreated();
        $complaint = Complaint::sole();

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/complaints/{$complaint->id}/respond", ['response' => 'Đèn đã hỏng từ trước khi tôi vào.'])->assertOk();

        Sanctum::actingAs($this->admin);
        $url = "/api/v1/admin/complaints/{$complaint->id}/handle";
        // Hoàn tiền / gỡ vắng mặt là kết quả của khiếu nại khách; trừ điểm là của khiếu nại chủ sân.
        $this->patchJson($url, ['status' => 'resolved', 'resolution' => 'Ok.', 'refund_amount' => 20000])->assertStatus(422);
        $this->patchJson($url, ['status' => 'resolved', 'resolution' => 'Ok.', 'trust_penalty' => 2])->assertStatus(422);
        $this->patchJson($url, ['status' => 'rejected', 'resolution' => 'Không có cơ sở.', 'trust_penalty' => 20])->assertStatus(422);
        $this->assertSame(100, $this->customer->refresh()->trust_score);

        $this->patchJson($url, ['status' => 'resolved', 'resolution' => 'Có biên bản thiệt hại.', 'trust_penalty' => 20])
            ->assertOk()->assertJsonPath('data.trust_penalty', 20);

        $this->assertSame(80, $this->customer->refresh()->trust_score);
        $this->assertDatabaseHas('trust_score_logs', ['user_id' => $this->customer->id, 'booking_id' => $booking->id, 'change' => -20]);
        Mail::assertQueued(ComplaintHandled::class, fn ($m) => $m->hasTo($this->owner->email) && ! $m->asRespondent);
        Mail::assertQueued(ComplaintHandled::class, fn ($m) => $m->hasTo($this->customer->email) && $m->asRespondent);
    }

    public function test_trust_penalty_does_not_apply_to_customer_complaints(): void
    {
        $booking = $this->playedBooking();
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Sân không đúng mô tả.'])->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/v1/admin/complaints/'.Complaint::sole()->id.'/handle', ['status' => 'resolved', 'resolution' => 'Ok.', 'trust_penalty' => 20])->assertStatus(422);
    }

    public function test_owner_complaint_does_not_hold_the_owners_own_payout_but_customer_complaint_does(): void
    {
        $ownerComplained = $this->playedBooking();
        $customerComplained = $this->playedBooking();
        $this->ownerComplains($ownerComplained)->assertCreated();
        Complaint::create([
            'user_id' => $this->customer->id, 'direction' => 'customer_to_owner', 'against_user_id' => $this->owner->id,
            'booking_id' => $customerComplained->id, 'category' => 'field_mismatch', 'content' => 'Sân không đúng mô tả.', 'status' => 'open',
        ]);

        app(StatementService::class)->generate(now()->subMonth()->format('Y-m'));

        $statement = OwnerStatement::where('owner_profile_id', $this->owner->fieldOwnerProfile->id)->sole();
        $this->assertSame(1, $statement->booking_count);
        $this->assertSame($statement->id, $ownerComplained->refresh()->statement_id);
        $this->assertNull($customerComplained->refresh()->statement_id);
    }

    public function test_admin_sees_stats_of_both_parties_including_owner_cancellations(): void
    {
        $cancelled = $this->playedBooking(['status' => 'confirmed', 'checked_in_at' => null, 'booking_date' => now()->addDays(2)->toDateString()]);
        $cancelled->markCancelled('field_owner', 'Sân bảo trì.');
        $this->assertSame('field_owner', $cancelled->refresh()->cancelled_by);

        $booking = $this->playedBooking();
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'owner_cancelled', 'content' => 'Chủ sân huỷ sát giờ.'])->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/complaints')->assertOk()
            ->assertJsonPath('data.0.parties.respondent.owner_cancellations_90d', 1)
            ->assertJsonPath('data.0.parties.respondent.complaints_received', 1);
    }

    public function test_bookings_show_the_viewer_their_own_complaint_and_the_one_against_them(): void
    {
        $booking = $this->playedBooking();
        $this->ownerComplains($booking)->assertCreated();

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/customer/bookings')->assertOk()
            ->assertJsonPath('data.0.complaint', null)
            ->assertJsonPath('data.0.complaint_against.status', 'open')
            ->assertJsonPath('data.0.complaint_against.can_respond', true);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/field-owner/bookings')->assertOk()
            ->assertJsonPath('data.0.complaint.status', 'open')
            ->assertJsonPath('data.0.complaint_against', null);
    }
}
