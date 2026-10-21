<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Thu hộ: giữ chỗ → chuyển khoản vào tài khoản nền tảng → xác nhận; hết hạn nhả chỗ; hoàn tiền theo bậc giờ. */
class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private SportsField $courtA;

    private SportsField $courtB;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);

        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        foreach (['courtA' => 'Sân 1', 'courtB' => 'Sân 2'] as $prop => $name) {
            $this->{$prop} = SportsField::create([
                'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => $name, 'slug' => str()->slug($name),
                'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
            ]);
            TimeSlot::create(['sports_field_id' => $this->{$prop}->id, 'start_time' => '06:00', 'end_time' => '22:00', 'is_active' => true]);
        }

        $this->date = today()->addDays(3)->toDateString();
    }

    private function checkout(array $items, string $option = 'deposit')
    {
        Sanctum::actingAs($this->customer);

        return $this->postJson('/api/v1/customer/bookings', ['booking_date' => $this->date, 'items' => $items, 'payment_option' => $option]);
    }

    private function item(SportsField $field, string $start, string $end): array
    {
        return ['sports_field_id' => $field->id, 'start_time' => $start, 'end_time' => $end];
    }

    private function webhook(array $payload, string $key = 'local-dev-key')
    {
        return $this->postJson('/api/v1/webhooks/bank', $payload, ['Authorization' => "Apikey {$key}"]);
    }

    public function test_checkout_of_several_ranges_on_two_courts_creates_one_payment(): void
    {
        $res = $this->checkout([
            $this->item($this->courtA, '18:00', '20:00'),
            $this->item($this->courtB, '18:00', '19:00'),
        ])->assertCreated();

        $payment = Payment::findOrFail($res->json('payment.id'));
        $this->assertSame(2, $payment->bookings()->count());
        $this->assertEquals(300000, $payment->total);
        $this->assertEquals(90000, $payment->amount); // cọc 30%
        $this->assertEquals(90000, $payment->bookings()->sum('payment_amount'));
        $this->assertSame('awaiting', $payment->status);
        $this->assertTrue($payment->due_at->lte(now()->addMinutes(15)));
        $res->assertJsonPath('payment.bank.account_number', Settings::getString('platform_bank_account', '1234567890'));
    }

    public function test_items_that_overlap_each_other_or_an_existing_booking_are_refused(): void
    {
        $this->checkout([$this->item($this->courtA, '18:00', '19:30'), $this->item($this->courtA, '19:00', '20:00')])->assertStatus(409);
        $this->assertSame(0, Booking::count()); // cả lượt bị huỷ, không giữ một nửa

        $this->checkout([$this->item($this->courtA, '18:00', '19:00')])->assertCreated();
        $this->checkout([$this->item($this->courtA, '18:30', '19:30')])->assertStatus(409);
    }

    public function test_range_shorter_than_minimum_or_off_the_half_hour_is_refused(): void
    {
        $this->checkout([$this->item($this->courtA, '18:00', '18:30')])->assertStatus(409);
        $this->checkout([$this->item($this->courtA, '18:15', '19:15')])->assertStatus(422)->assertJsonValidationErrors('items.0.start_time');
        $this->checkout([$this->item($this->courtA, '21:30', '23:00')])->assertStatus(422); // ngoài giờ mở cửa
    }

    public function test_full_payment_option_and_switching_option_before_paying(): void
    {
        $paymentId = $this->checkout([$this->item($this->courtA, '18:00', '20:00')], 'full')->json('payment.id');
        $this->assertEquals(200000, Payment::find($paymentId)->amount);

        $this->patchJson("/api/v1/customer/payments/{$paymentId}", ['option' => 'deposit'])->assertOk()->assertJsonPath('data.amount', 60000);
        $this->assertEquals(60000, Booking::first()->payment_amount);
    }

    public function test_webhook_with_matching_code_confirms_all_bookings_once(): void
    {
        $payment = Payment::findOrFail($this->checkout([
            $this->item($this->courtA, '18:00', '19:00'),
            $this->item($this->courtB, '18:00', '19:00'),
        ])->json('payment.id'));

        $payload = ['id' => 1, 'transferType' => 'in', 'transferAmount' => 60000, 'content' => "chuyen tien {$payment->code} dat san", 'referenceCode' => 'FT001'];
        $this->webhook($payload)->assertOk()->assertJsonPath('matched', $payment->code);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(['confirmed', 'confirmed'], Booking::pluck('status')->map->value->all());

        // Ngân hàng gửi lặp: không xử lý lại.
        $this->webhook($payload)->assertOk()->assertJsonPath('matched', null);
    }

    public function test_webhook_rejects_wrong_key_and_ignores_short_transfers(): void
    {
        $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '19:00')])->json('payment.id'));

        $this->webhook(['transferAmount' => 30000, 'content' => $payment->code], 'sai-khoa')->assertStatus(401);
        $this->webhook(['transferAmount' => 10000, 'content' => $payment->code, 'referenceCode' => 'FT2'])->assertOk();

        $this->assertSame('awaiting', $payment->fresh()->status);
    }

    public function test_unpaid_hold_expires_and_frees_the_courts(): void
    {
        $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '19:00')])->json('payment.id'));

        $this->travel(16)->minutes();
        $this->artisan('bookings:sync')->assertSuccessful();

        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('cancelled', Booking::first()->status->value);

        // Chỗ đã trống: người khác đặt được.
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]));
        $this->postJson('/api/v1/customer/bookings', ['booking_date' => $this->date, 'items' => [$this->item($this->courtA, '18:00', '19:00')]])->assertCreated();
    }

    public function test_late_money_confirms_if_still_free_otherwise_needs_refund(): void
    {
        $first = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '19:00')])->json('payment.id'));
        $second = Payment::findOrFail($this->checkout([$this->item($this->courtB, '18:00', '19:00')])->json('payment.id'));
        $this->travel(16)->minutes();
        $this->artisan('bookings:sync');

        // Sân 2 bị người khác đặt mất trong lúc tiền chưa về.
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]));
        $this->postJson('/api/v1/customer/bookings', ['booking_date' => $this->date, 'items' => [$this->item($this->courtB, '18:00', '19:00')]])->assertCreated();

        $this->webhook(['transferAmount' => 30000, 'content' => $first->code, 'referenceCode' => 'L1']);
        $this->webhook(['transferAmount' => 30000, 'content' => $second->code, 'referenceCode' => 'L2']);

        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('confirmed', $first->bookings()->first()->status->value);
        $this->assertSame('refund_required', $second->fresh()->status);
        $this->assertSame('pending', $second->bookings()->first()->refund_status);
    }

    public function test_admin_can_confirm_a_payment_by_hand(): void
    {
        $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '19:00')])->json('payment.id'));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->getJson('/api/v1/admin/payments?status=awaiting')->assertOk()->assertJsonPath('data.0.code', $payment->code);
        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm", ['bank_ref' => 'FT999'])->assertOk();
        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertStatus(409);

        $this->assertSame('admin', $payment->fresh()->source);
        $this->assertDatabaseHas('admin_logs', ['action' => 'confirm_payment']);
    }

    public function test_customer_cancelling_an_unpaid_hold_releases_every_booking_in_it(): void
    {
        $res = $this->checkout([$this->item($this->courtA, '18:00', '19:00'), $this->item($this->courtB, '18:00', '19:00')]);
        $bookingId = $res->json('data.id');

        $this->patchJson("/api/v1/customer/bookings/{$bookingId}/cancel")->assertOk();

        $this->assertSame(['cancelled', 'cancelled'], Booking::pluck('status')->map->value->all());
        $this->assertSame('cancelled', Payment::first()->status);
    }

    public function test_refund_follows_hour_tiers_when_customer_cancels_a_paid_booking(): void
    {
        $cases = [30 => 60000.0, 10 => 30000.0, 2 => null]; // giờ còn lại → số tiền hoàn (cọc 60k)
        foreach ($cases as $hoursLeft => $expected) {
            $this->travelBack();
            $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '20:00')])->json('payment.id'));
            app(\App\Services\PaymentService::class)->markPaid($payment, 'admin');
            $booking = $payment->bookings()->first();

            $this->travelTo($booking->startsAt()->subHours($hoursLeft));
            $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel")->assertOk();

            $this->assertEquals($expected, $booking->fresh()->refund_amount, "Huỷ trước {$hoursLeft} giờ");
            Booking::query()->delete();
            Payment::query()->delete();
        }
    }

    public function test_booking_shows_the_refund_the_customer_would_get_if_cancelling_now(): void
    {
        $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '20:00')])->json('payment.id'));
        $booking = $payment->bookings()->first();

        // Chưa thanh toán: chưa có khoản hoàn.
        $this->getJson("/api/v1/customer/bookings/{$booking->id}")->assertOk()->assertJsonPath('data.refund_if_cancelled', null);

        app(\App\Services\PaymentService::class)->markPaid($payment, 'admin');
        $this->getJson("/api/v1/customer/bookings/{$booking->id}")->assertJsonPath('data.refund_if_cancelled', 60000);

        $this->travelTo($booking->startsAt()->subHours(10));
        $this->getJson("/api/v1/customer/bookings/{$booking->id}")->assertJsonPath('data.refund_if_cancelled', 30000);
    }

    public function test_owner_cancelling_a_paid_booking_refunds_in_full(): void
    {
        $payment = Payment::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '20:00')])->json('payment.id'));
        app(\App\Services\PaymentService::class)->markPaid($payment, 'admin');
        $booking = $payment->bookings()->first();

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'cancelled', 'reason' => 'Mất điện'])->assertOk();

        $this->assertEquals(60000, $booking->fresh()->refund_amount);
    }

    public function test_owner_cannot_reject_a_hold_waiting_for_payment(): void
    {
        $booking = Booking::findOrFail($this->checkout([$this->item($this->courtA, '18:00', '19:00')])->json('data.id'));

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'rejected'])->assertStatus(409);
    }

    public function test_accepting_a_waitlist_offer_creates_a_hold_to_pay(): void
    {
        $slot = TimeSlot::where('sports_field_id', $this->courtA->id)->first();
        $wait = BookingWaitlist::create(['user_id' => $this->customer->id, 'sports_field_id' => $this->courtA->id, 'time_slot_id' => $slot->id,
            'booking_date' => $this->date, 'start_time' => '18:00', 'end_time' => '19:00', 'status' => 'offered',
            'offered_at' => now(), 'offer_expires_at' => now()->addMinutes(15), 'priority_score' => 100]);

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/v1/customer/waitlist/{$wait->id}/accept")->assertSuccessful();

        $this->assertSame('awaiting', Booking::first()->payment?->status);
    }
}
