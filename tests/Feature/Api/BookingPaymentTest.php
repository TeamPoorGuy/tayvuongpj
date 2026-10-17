<?php

namespace Tests\Feature\Api;

use App\Mail\BookingPaymentReported;
use App\Mail\BookingStatusChanged;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Luồng thanh toán chuyển khoản: duyệt → khách báo đã chuyển → chủ sân xác nhận nhận tiền. */
class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ Sân', 'owner_id_number' => '001099001234',
            'business_name' => 'CLB', 'business_address' => 'Hà Nội', 'business_phone' => '0900000009',
            'business_license' => 'GPKD-1', 'verification_status' => 'approved', 'verified_at' => now(),
            'bank_name' => 'Vietcombank', 'bank_account_number' => '1029384756', 'bank_account_holder' => 'NGUYEN VAN CHU',
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);

        $cat = SportCategory::create(['name' => 'Bóng đá', 'slug' => 'bong-da']);
        $type = FieldType::create(['sport_category_id' => $cat->id, 'name' => 'Sân 7', 'slug' => 'san-7']);
        $field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân Test', 'slug' => 'san-test',
            'address' => 'Cầu Giấy', 'price_per_hour' => 250000, 'status' => 'approved', 'is_active' => true,
        ]);
        $slot = TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => '17:30', 'end_time' => '19:00', 'is_active' => true]);
        $this->booking = Booking::create([
            'user_id' => $this->customer->id, 'sports_field_id' => $field->id, 'time_slot_id' => $slot->id,
            'booking_date' => today()->addDays(3), 'status' => 'pending', 'total_price' => 375000,
        ]);
    }

    private function approve(): void
    {
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve")->assertOk();
    }

    private function report(string $option = 'deposit'): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", ['option' => $option])->assertOk();
    }

    public function test_approving_opens_a_payment_window_with_a_transfer_code(): void
    {
        $this->approve();

        $booking = $this->booking->fresh();
        $this->assertSame('pending', $booking->status->value);
        $this->assertSame('awaiting', $booking->payment_status);
        $this->assertMatchesRegularExpression('/^SH'.$booking->id.'[A-Z0-9]{4}$/', $booking->payment_code);
        $this->assertEqualsWithDelta(now()->addHours(2)->timestamp, $booking->payment_due_at->timestamp, 5);
        Mail::assertQueued(BookingStatusChanged::class, fn ($mail) => $mail->hasTo($this->customer->email));
    }

    public function test_payment_deadline_never_passes_the_start_time(): void
    {
        $this->travelTo(today()->addDays(3)->setTime(16, 30));
        $this->approve();

        $this->assertSame('17:30', $this->booking->fresh()->payment_due_at->format('H:i'));
    }

    public function test_owner_without_bank_account_cannot_approve(): void
    {
        $this->owner->fieldOwnerProfile->update(['bank_account_number' => null]);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve")->assertStatus(409);
        $this->assertNull($this->booking->fresh()->payment_status);
    }

    public function test_status_endpoint_can_no_longer_confirm_a_pending_booking(): void
    {
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/status", ['status' => 'confirmed'])->assertStatus(409);
    }

    public function test_customer_sees_bank_details_only_after_approval_and_others_never_do(): void
    {
        Sanctum::actingAs($this->customer);
        $this->getJson("/api/v1/customer/bookings/{$this->booking->id}")->assertOk()->assertJsonMissingPath('data.bank');

        $this->approve();

        Sanctum::actingAs($this->customer);
        $this->getJson("/api/v1/customer/bookings/{$this->booking->id}")->assertOk()
            ->assertJsonPath('data.bank.account_number', '1029384756')
            ->assertJsonPath('data.payment.deposit_amount', 113000)
            ->assertJsonPath('data.payment.code', $this->booking->fresh()->payment_code);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]));
        $this->getJson("/api/v1/customer/bookings/{$this->booking->id}")->assertNotFound();
    }

    public function test_customer_reports_deposit_or_full_payment_with_the_right_amount(): void
    {
        $this->approve();
        $this->report('deposit');

        $booking = $this->booking->fresh();
        $this->assertSame('reported', $booking->payment_status);
        $this->assertSame('deposit', $booking->payment_option);
        $this->assertSame(113000.0, (float) $booking->payment_amount); // 30% của 375.000 = 112.500 → làm tròn lên nghìn
        $this->assertFalse($booking->canBeCancelled());
        Mail::assertQueued(BookingPaymentReported::class, fn ($mail) => $mail->hasTo($this->owner->email));

        $booking->update(['payment_status' => 'awaiting']);
        $this->report('full');
        $this->assertSame(375000.0, (float) $this->booking->fresh()->payment_amount);
    }

    public function test_customer_cannot_report_before_approval_or_after_the_deadline(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", ['option' => 'deposit'])->assertStatus(409);

        $this->approve();
        $this->travel(3)->hours();

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/bookings/{$this->booking->id}/payment", ['option' => 'deposit'])->assertStatus(409);
        $this->assertSame('awaiting', $this->booking->fresh()->payment_status);
    }

    public function test_owner_confirming_receipt_confirms_the_booking(): void
    {
        $this->approve();
        $this->report('full');

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/payment", ['received' => true])->assertOk();

        $booking = $this->booking->fresh();
        $this->assertSame('confirmed', $booking->status->value);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertNotNull($booking->paid_at);
    }

    public function test_owner_reporting_money_not_received_reopens_the_payment_window(): void
    {
        $this->approve();
        $this->report();
        $this->travel(90)->minutes();

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/payment", ['received' => false])->assertOk();

        $booking = $this->booking->fresh();
        $this->assertSame('pending', $booking->status->value);
        $this->assertSame('awaiting', $booking->payment_status);
        $this->assertTrue($booking->payment_due_at->gt(now()->addMinutes(100)));
    }

    public function test_owner_cannot_confirm_receipt_before_the_customer_reports(): void
    {
        $this->approve();

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/payment", ['received' => true])->assertStatus(409);
        $this->assertSame('pending', $this->booking->fresh()->status->value);
    }

    public function test_another_owner_cannot_approve_or_resolve(): void
    {
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $other->id, 'owner_name' => 'Khác', 'owner_id_number' => '001099009999',
            'business_name' => 'CLB 2', 'business_address' => 'HN', 'business_phone' => '0900000008',
            'business_license' => 'GPKD-2', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);

        Sanctum::actingAs($other);
        $this->patchJson("/api/v1/field-owner/bookings/{$this->booking->id}/approve")->assertForbidden();
    }

    public function test_sync_cancels_unpaid_bookings_past_the_deadline_and_offers_the_slot_to_the_waitlist(): void
    {
        $this->approve();
        $waiter = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $waitlist = BookingWaitlist::create([
            'user_id' => $waiter->id, 'sports_field_id' => $this->booking->sports_field_id, 'time_slot_id' => $this->booking->time_slot_id,
            'booking_date' => $this->booking->booking_date, 'status' => 'waiting', 'priority_score' => 100,
        ]);

        $this->travel(121)->minutes();
        app(BookingService::class)->syncStatuses();

        $booking = $this->booking->fresh();
        $this->assertSame('cancelled', $booking->status->value);
        $this->assertStringContainsString('Quá hạn thanh toán', $booking->cancel_reason);
        $this->assertSame('offered', $waitlist->fresh()->status);
    }

    public function test_sync_keeps_a_reported_payment_until_the_slot_starts(): void
    {
        $this->approve();
        $this->report();

        $this->travel(5)->hours();
        app(BookingService::class)->syncStatuses();
        $this->assertSame('pending', $this->booking->fresh()->status->value);

        $this->travelTo($this->booking->fresh()->startsAt()->addMinute());
        app(BookingService::class)->syncStatuses();
        $booking = $this->booking->fresh();
        $this->assertSame('rejected', $booking->status->value);
        $this->assertStringContainsString('hoàn tiền', $booking->cancel_reason);
    }
}
