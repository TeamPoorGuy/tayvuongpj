<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Ví SportHub: mọi thay đổi số dư đi qua đây, khoá dòng người dùng và ghi một dòng sổ cái.
 * Số tiền là VND, làm tròn tới đồng.
 */
class WalletService
{
    /** @param  array{booking_id?: int|null, payment_id?: int|null, withdrawal_id?: int|null, bank_ref?: string|null}  $refs */
    public function credit(User $user, float $amount, string $type, string $description, array $refs = []): WalletTransaction
    {
        return $this->apply($user, round($amount), $type, $description, $refs);
    }

    /**
     * Trừ ví. Mặc định không cho âm; điều chỉnh tiền chủ sân sau khiếu nại thì được âm (rút bị chặn tới khi dương lại).
     *
     * @param  array{booking_id?: int|null, payment_id?: int|null, withdrawal_id?: int|null, bank_ref?: string|null}  $refs
     */
    public function debit(User $user, float $amount, string $type, string $description, array $refs = [], bool $allowNegative = false): WalletTransaction
    {
        return $this->apply($user, -round($amount), $type, $description, $refs, $allowNegative);
    }

    /**
     * Hoàn tiền một đơn thẳng vào ví khách rồi đánh dấu đã hoàn. Người gọi tự đặt `refund_amount` (tổng đã hoàn của đơn)
     * trước; `$amount` chỉ là phần cộng thêm lần này.
     */
    public function refundBooking(Booking $booking, float $amount, string $description): WalletTransaction
    {
        $customer = $booking->customer ?? User::findOrFail($booking->user_id);
        $transaction = $this->credit($customer, $amount, 'refund', $description, ['booking_id' => $booking->id]);
        $booking->update(['refund_status' => 'done', 'refunded_at' => now()]);

        return $transaction;
    }

    /** Số ngày tiền vừa nạp bị giữ lại, chưa cho rút về ngân hàng (vẫn dùng đặt sân được ngay). */
    public const TOPUP_HOLD_DAYS = 7;

    /**
     * Số tiền được rút về ngân hàng: số dư trừ tiền nạp trong TOPUP_HOLD_DAYS ngày gần nhất. Tiền hoàn, tiền chủ sân và
     * tiền chuyển lạc không bị giữ. Chặn việc nạp rồi rút ngay như một kênh chuyển tiền.
     * ponytail: trừ theo tổng số dư chứ không theo dõi từng đồng; tiền hoàn của chính tiền vừa nạp cũng bị giữ tới hết hạn.
     */
    public function withdrawable(User $user): float
    {
        $balance = (float) User::whereKey($user->id)->value('wallet_balance');
        $recentTopups = (float) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'topup')
            ->where('created_at', '>', now()->subDays(self::TOPUP_HOLD_DAYS))
            ->sum('amount');

        return max(0.0, $balance - $recentTopups);
    }

    private function apply(User $user, float $delta, string $type, string $description, array $refs, bool $allowNegative = false): WalletTransaction
    {
        if ($delta == 0.0) {
            throw new DomainException('Số tiền giao dịch ví phải khác 0.');
        }

        return DB::transaction(function () use ($user, $delta, $type, $description, $refs, $allowNegative) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $after = (float) $locked->wallet_balance + $delta;
            if ($after < 0 && ! $allowNegative) {
                throw new DomainException('Số dư ví không đủ.');
            }

            $locked->forceFill(['wallet_balance' => $after])->save();
            $user->setAttribute('wallet_balance', $locked->wallet_balance);

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $after,
                'booking_id' => $refs['booking_id'] ?? null,
                'payment_id' => $refs['payment_id'] ?? null,
                'withdrawal_id' => $refs['withdrawal_id'] ?? null,
                'bank_ref' => $refs['bank_ref'] ?? null,
                'description' => $description,
            ]);
        });
    }
}
