<?php

namespace Tests\Feature\Api;

use App\Mail\WithdrawalProcessed;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Rút ví về ngân hàng: lệnh nhỏ tự duyệt, lệnh lớn hoặc người đang có khiếu nại chờ admin; tiền bị giữ ngay khi tạo lệnh. */
class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $owner;

    private User $admin;

    private array $bank = ['bank_bin' => '970436', 'account_number' => '0123456789', 'account_holder' => 'LE VAN KHACH'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Settings::clearCache(); // cài đặt là cache tĩnh, không để test trước ảnh hưởng test sau

        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
            'bank_name' => 'Vietcombank', 'bank_bin' => '970436', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'NGUYEN CHU SAN',
        ]);
    }

    private function fund(User $user, float $amount): void
    {
        app(WalletService::class)->credit($user, $amount, 'topup', 'Nạp ví');
    }

    private function withdraw(User $user, array $payload)
    {
        Sanctum::actingAs($user->fresh());

        return $this->postJson('/api/v1/wallet/withdrawals', $payload);
    }

    public function test_small_withdrawal_is_paid_automatically_and_the_bank_is_remembered(): void
    {
        $this->fund($this->customer, 500000);

        $this->withdraw($this->customer, ['amount' => 200000] + $this->bank)->assertCreated()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.auto', true);

        $this->assertEquals(300000, $this->customer->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->customer->id, 'type' => 'withdrawal', 'amount' => -200000]);
        $this->assertSame('0123456789', $this->customer->fresh()->bank_account_number);
        Mail::assertQueued(WithdrawalProcessed::class, fn ($m) => $m->hasTo($this->customer->email));

        Sanctum::actingAs($this->customer->fresh());
        $this->getJson('/api/v1/wallet/withdrawals')->assertOk()->assertJsonPath('saved_bank.account_holder', 'LE VAN KHACH');
    }

    public function test_large_withdrawal_waits_for_admin_then_is_paid_with_a_qr(): void
    {
        $this->fund($this->customer, 5000000);

        $this->withdraw($this->customer, ['amount' => 3000000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertEquals(2000000, $this->customer->fresh()->wallet_balance, 'Tiền bị giữ khỏi ví ngay khi tạo lệnh.');
        $withdrawal = Withdrawal::sole();

        Sanctum::actingAs($this->admin);
        $list = $this->getJson('/api/v1/admin/withdrawals?status=pending')->assertOk();
        $this->assertStringContainsString('970436-0123456789', $list->json('data.0.bank.qr_url'));
        $this->assertStringContainsString('amount=3000000', $list->json('data.0.bank.qr_url'));

        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/paid", ['reference' => 'FT777'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/paid")->assertStatus(409);
        $this->assertDatabaseHas('admin_logs', ['action' => 'pay_withdrawal']);
        $this->assertEquals(2000000, $this->customer->fresh()->wallet_balance);
    }

    public function test_the_auto_limit_is_an_admin_setting(): void
    {
        Settings::set('withdraw_auto_limit', 100000);
        $this->fund($this->customer, 1000000);

        $this->withdraw($this->customer, ['amount' => 200000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'pending');
    }

    public function test_auto_approval_is_capped_per_24_hours_so_small_withdrawals_cannot_drain_a_wallet(): void
    {
        Settings::set('withdraw_auto_limit', 300000);
        $this->fund($this->customer, 2000000);

        $this->withdraw($this->customer, ['amount' => 200000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'paid');
        // Lệnh thứ hai làm tổng tự duyệt trong ngày vượt hạn mức: chờ admin.
        $this->withdraw($this->customer, ['amount' => 200000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'pending');

        // Sau 24 giờ hạn mức tự duyệt được tính lại.
        Withdrawal::where('status', 'pending')->delete();
        $this->travel(25)->hours();
        $this->withdraw($this->customer, ['amount' => 200000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'paid');
    }

    public function test_rejecting_returns_the_money_to_the_wallet(): void
    {
        $this->fund($this->customer, 5000000);
        $this->withdraw($this->customer, ['amount' => 3000000] + $this->bank)->assertCreated();
        $withdrawal = Withdrawal::sole();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/reject", ['reason' => 'Tên chủ tài khoản không khớp.'])->assertOk();

        $this->assertEquals(5000000, $this->customer->fresh()->wallet_balance);
        $this->assertSame('rejected', $withdrawal->fresh()->status);
        Mail::assertQueued(WithdrawalProcessed::class, fn ($m) => $m->withdrawal->status === 'rejected');
    }

    public function test_user_with_an_open_complaint_is_not_auto_approved(): void
    {
        $this->fund($this->customer, 500000);
        Complaint::create(['user_id' => $this->customer->id, 'category' => 'other', 'content' => 'Cần hỗ trợ.', 'status' => 'open']);

        $this->withdraw($this->customer, ['amount' => 100000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'pending');
    }

    public function test_validation_limits_balance_and_one_pending_at_a_time(): void
    {
        $this->fund($this->customer, 5000000);

        $this->withdraw($this->customer, ['amount' => 10000] + $this->bank)->assertStatus(422);
        $this->withdraw($this->customer, ['amount' => 100000, 'bank_bin' => '999999', 'account_number' => '0123456789', 'account_holder' => 'LE VAN KHACH'])->assertStatus(422)->assertJsonValidationErrors('bank_bin');
        $this->withdraw($this->customer, ['amount' => 100000, 'bank_bin' => '970436', 'account_number' => '12ab', 'account_holder' => 'LE VAN KHACH'])->assertStatus(422)->assertJsonValidationErrors('account_number');
        $this->withdraw($this->customer, ['amount' => 100000, 'bank_bin' => '970436', 'account_number' => '0123456789', 'account_holder' => 'Lê Văn Khách'])->assertStatus(422)->assertJsonValidationErrors('account_holder');
        $this->withdraw($this->customer, ['amount' => 100000])->assertStatus(422); // thiếu tài khoản
        $this->withdraw($this->customer, ['amount' => 9000000] + $this->bank)->assertStatus(422); // quá số dư

        $this->withdraw($this->customer, ['amount' => 3000000] + $this->bank)->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->withdraw($this->customer, ['amount' => 100000] + $this->bank)->assertStatus(409);
    }

    public function test_owner_withdraws_to_the_profile_account_without_entering_one(): void
    {
        $this->fund($this->owner, 400000);

        $this->withdraw($this->owner, ['amount' => 150000])->assertCreated()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.bank.account_number', '1234567890')->assertJsonPath('data.bank.account_holder', 'NGUYEN CHU SAN');
        $this->assertNull($this->owner->fresh()->bank_account_number, 'Không lưu tài khoản khách cho chủ sân.');
    }

    public function test_negative_owner_balance_cannot_be_withdrawn(): void
    {
        app(WalletService::class)->debit($this->owner, 100000, 'owner_adjustment', 'Hoàn sau khiếu nại', [], allowNegative: true);

        $this->withdraw($this->owner, ['amount' => 50000])->assertStatus(422);
    }

    public function test_only_admins_can_see_the_withdrawal_queue(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/admin/withdrawals')->assertStatus(403);
    }
}
