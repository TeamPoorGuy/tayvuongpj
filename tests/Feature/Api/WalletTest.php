<?php

namespace Tests\Feature\Api;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lõi ví: số dư luôn khớp sổ cái, không trừ quá số dư. */
class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->wallet = app(WalletService::class);
    }

    public function test_credit_and_debit_update_balance_and_ledger(): void
    {
        $this->wallet->credit($this->user, 100000, 'topup', 'Nạp ví');
        $this->wallet->debit($this->user, 30000, 'booking_payment', 'Trả đơn');

        $this->assertEquals(70000, $this->user->refresh()->wallet_balance);
        $this->assertSame(2, WalletTransaction::where('user_id', $this->user->id)->count());
        $last = WalletTransaction::latest('id')->first();
        $this->assertEquals(-30000, $last->amount);
        $this->assertEquals(70000, $last->balance_after);
        // Số dư bằng tổng sổ cái.
        $this->assertEquals($this->user->wallet_balance, WalletTransaction::where('user_id', $this->user->id)->sum('amount'));
    }

    public function test_debit_beyond_balance_is_rejected_and_changes_nothing(): void
    {
        $this->wallet->credit($this->user, 50000, 'topup', 'Nạp ví');

        try {
            $this->wallet->debit($this->user, 60000, 'withdrawal', 'Rút');
            $this->fail('Phải từ chối khi số dư không đủ.');
        } catch (DomainException $e) {
            $this->assertSame('Số dư ví không đủ.', $e->getMessage());
        }

        $this->assertEquals(50000, $this->user->refresh()->wallet_balance);
        $this->assertSame(1, WalletTransaction::count());
    }

    public function test_second_debit_uses_the_fresh_balance_not_a_stale_model(): void
    {
        $this->wallet->credit($this->user, 100000, 'topup', 'Nạp ví');
        $stale = User::find($this->user->id); // bản sao cũ, số dư 100k
        $this->wallet->debit($this->user, 80000, 'booking_payment', 'Trả đơn');

        $this->expectException(DomainException::class);
        $this->wallet->debit($stale, 80000, 'booking_payment', 'Trả đơn lần 2');
    }

    public function test_owner_adjustment_may_go_negative_only_when_allowed(): void
    {
        $this->wallet->credit($this->user, 10000, 'owner_earning', 'Tiền đơn');
        $this->wallet->debit($this->user, 25000, 'owner_adjustment', 'Hoàn sau khiếu nại', allowNegative: true);

        $this->assertEquals(-15000, $this->user->refresh()->wallet_balance);
    }

    public function test_balance_is_not_mass_assignable(): void
    {
        $this->user->fill(['wallet_balance' => 999999])->save();

        $this->assertEquals(0, $this->user->refresh()->wallet_balance);
    }
}
