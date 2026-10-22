<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Trả tiền đặt sân bằng ví (đủ thì xác nhận ngay, thiếu thì QR phần còn lại) và nạp ví qua chuyển khoản. */
class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private SportsField $court;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->court = SportsField::create([
            'field_owner_id' => $owner->id, 'field_type_id' => $type->id, 'name' => 'Sân 1', 'slug' => 'san-1',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
        TimeSlot::create(['sports_field_id' => $this->court->id, 'start_time' => '06:00', 'end_time' => '22:00', 'is_active' => true]);
        $this->date = today()->addDays(3)->toDateString();
    }

    private function fund(float $amount): void
    {
        app(WalletService::class)->credit($this->customer, $amount, 'topup', 'Nạp ví');
    }

    private function checkout(array $extra = [], string $start = '18:00', string $end = '19:00')
    {
        Sanctum::actingAs($this->customer);

        return $this->postJson('/api/v1/customer/bookings', [
            'booking_date' => $this->date, 'payment_option' => 'deposit',
            'items' => [['sports_field_id' => $this->court->id, 'start_time' => $start, 'end_time' => $end]],
        ] + $extra);
    }

    private function webhook(array $payload)
    {
        return $this->postJson('/api/v1/webhooks/bank', $payload, ['Authorization' => 'Apikey local-dev-key']);
    }

    public function test_enough_wallet_balance_confirms_the_booking_immediately(): void
    {
        $this->fund(100000);

        $response = $this->checkout()->assertCreated()->assertJsonPath('payment.status', 'paid')->assertJsonPath('payment.wallet_amount', 30000);

        $this->assertSame('confirmed', Booking::sole()->status->value);
        $this->assertEquals(70000, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->customer->id, 'type' => 'booking_payment', 'amount' => -30000]);
        $this->assertSame('wallet', Payment::sole()->source);
        $this->assertNotNull($response->json('payment.paid_at'));
    }

    public function test_short_balance_is_used_first_and_the_qr_covers_only_the_rest(): void
    {
        $this->fund(10000);

        $response = $this->checkout()->assertCreated()->assertJsonPath('payment.status', 'awaiting')
            ->assertJsonPath('payment.wallet_amount', 10000)->assertJsonPath('payment.due_amount', 20000);
        $this->assertStringContainsString('amount=20000', $response->json('payment.bank.qr_url'));
        $this->assertEquals(0, $this->customer->fresh()->wallet_balance);

        $payment = Payment::sole();
        // Chuyển thiếu phần còn lại: không xác nhận, tiền vào ví.
        $this->webhook(['transferAmount' => 5000, 'content' => $payment->code, 'referenceCode' => 'W1'])->assertOk();
        $this->assertSame('awaiting', $payment->fresh()->status);
        // Chuyển đúng phần còn lại thì xác nhận.
        $this->webhook(['transferAmount' => 20000, 'content' => $payment->code, 'referenceCode' => 'W2'])->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('confirmed', Booking::sole()->status->value);
    }

    public function test_wallet_can_be_skipped_and_the_balance_stays_untouched(): void
    {
        $this->fund(100000);

        $this->checkout(['use_wallet' => false])->assertCreated()->assertJsonPath('payment.status', 'awaiting')->assertJsonPath('payment.wallet_amount', 0);
        $this->assertEquals(100000, $this->customer->fresh()->wallet_balance);
    }

    public function test_expiry_and_cancel_give_the_wallet_part_back_and_late_money_goes_to_the_wallet(): void
    {
        $this->fund(10000);
        $this->checkout()->assertCreated();
        $payment = Payment::sole();

        $this->travel(16)->minutes();
        $this->artisan('bookings:sync')->assertSuccessful();
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertEquals(10000, $this->customer->fresh()->wallet_balance, 'Phần ví đã giữ được trả lại.');

        // Tiền ngân hàng đến muộn: không tự xác nhận vì phần ví đã trả, vào ví để khách tự dùng.
        $this->webhook(['transferAmount' => 20000, 'content' => $payment->code, 'referenceCode' => 'L1'])->assertOk();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertEquals(30000, $this->customer->fresh()->wallet_balance);
    }

    public function test_cancelling_an_awaiting_payment_returns_the_wallet_part(): void
    {
        $this->fund(10000);
        $this->checkout()->assertCreated();
        $payment = Payment::sole();

        $this->postJson("/api/v1/customer/payments/{$payment->id}/cancel")->assertOk();

        $this->assertEquals(10000, $this->customer->fresh()->wallet_balance);
    }

    public function test_changing_to_full_payment_recomputes_the_wallet_share(): void
    {
        $this->fund(20000);
        $this->checkout()->assertCreated()->assertJsonPath('payment.wallet_amount', 20000)->assertJsonPath('payment.due_amount', 10000);
        $payment = Payment::sole();

        // Trả đủ 100k: ví hoàn 20k rồi trừ lại 20k, còn 80k chuyển khoản.
        $this->patchJson("/api/v1/customer/payments/{$payment->id}", ['option' => 'full'])->assertOk()
            ->assertJsonPath('data.wallet_amount', 20000)->assertJsonPath('data.due_amount', 80000);
        $this->assertEquals(0, $this->customer->fresh()->wallet_balance);
    }

    public function test_topup_credits_what_was_actually_received_once(): void
    {
        Sanctum::actingAs($this->customer);
        $id = $this->postJson('/api/v1/wallet/topups', ['amount' => 200000])->assertCreated()->assertJsonPath('data.purpose', 'topup')->json('data.id');
        $payment = Payment::findOrFail($id);
        $this->postJson('/api/v1/wallet/topups', ['amount' => 500])->assertStatus(422);

        $this->webhook(['transferAmount' => 150000, 'content' => $payment->code, 'referenceCode' => 'T1'])->assertOk();
        $this->webhook(['transferAmount' => 150000, 'content' => $payment->code, 'referenceCode' => 'T1'])->assertOk(); // lặp

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertEquals(150000, $this->customer->fresh()->wallet_balance);
        Sanctum::actingAs($this->customer->fresh());
        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.balance', 150000)->assertJsonPath('data.transactions.0.type', 'topup');
    }

    public function test_admin_confirming_a_topup_credits_the_wallet(): void
    {
        Sanctum::actingAs($this->customer);
        $payment = Payment::findOrFail($this->postJson('/api/v1/wallet/topups', ['amount' => 100000])->json('data.id'));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm", ['bank_ref' => 'ADM1'])->assertOk();
        $this->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertStatus(409);

        $this->assertEquals(100000, $this->customer->fresh()->wallet_balance, 'Xác nhận lặp không được cộng thêm.');
    }

    public function test_topping_up_then_paying_the_waiting_payment_with_the_wallet(): void
    {
        $this->checkout()->assertCreated()->assertJsonPath('payment.status', 'awaiting');
        $payment = Payment::sole();
        $this->fund(50000);

        $this->postJson("/api/v1/customer/payments/{$payment->id}/use-wallet")->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame('confirmed', Booking::sole()->status->value);
        $this->assertEquals(20000, $this->customer->fresh()->wallet_balance);
        // Ví khách khác không dùng được lượt này.
        Sanctum::actingAs(User::factory()->create(['role' => 'customer', 'is_active' => true]));
        $this->postJson("/api/v1/customer/payments/{$payment->id}/use-wallet")->assertStatus(404);
    }
}
