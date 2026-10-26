<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Mail\WithdrawalProcessed;
use App\Models\AdminLog;
use App\Models\Complaint;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Banks;
use App\Support\SafeMail;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rút tiền từ ví về ngân hàng. Tiền bị trừ khỏi ví ngay khi tạo lệnh (giữ tiền); lệnh nhỏ tự được duyệt,
 * lệnh lớn hoặc của người đang có khiếu nại chờ admin chuyển khoản và bấm xác nhận.
 */
class WithdrawalService
{
    public const MIN_AMOUNT = 50000;

    public function __construct(private readonly WalletService $wallet) {}

    /** @param  array{bank_bin?: string, account_number?: string, account_holder?: string}  $bank  Chủ sân bỏ qua: dùng tài khoản trong hồ sơ cơ sở. */
    public function request(User $user, float $amount, array $bank = []): Withdrawal
    {
        if ($amount < self::MIN_AMOUNT) {
            throw new DomainException('Số tiền rút tối thiểu '.number_format(self::MIN_AMOUNT, 0, ',', '.').'đ.');
        }

        $withdrawal = DB::transaction(function () use ($user, $amount, $bank) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (Withdrawal::where('user_id', $user->id)->where('status', 'pending')->exists()) {
                throw new ConflictException('Bạn đang có một lệnh rút chờ xử lý. Vui lòng chờ lệnh đó xong rồi rút tiếp.');
            }

            [$bin, $account, $holder] = $this->destination($user, $bank);
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'bank_bin' => $bin,
                'bank_name' => Banks::findByBin($bin)['short_name'] ?? $bin,
                'account_number' => $account,
                'account_holder' => $holder,
                'status' => 'pending',
            ]);
            $this->wallet->debit($user, $amount, 'withdrawal', 'Rút tiền về ngân hàng', ['withdrawal_id' => $withdrawal->id]);

            return $withdrawal;
        });

        if ($this->canAutoApprove($user, $amount)) {
            // ponytail: giả lập cổng chi hộ ngân hàng; thật thì gọi API chi hộ ở đây rồi ghi mã giao dịch trả về.
            return $this->markPaid($withdrawal, null, 'AUTO-'.Str::upper(Str::random(8)), auto: true);
        }

        return $withdrawal;
    }

    /** Admin đã chuyển khoản (quét QR) và xác nhận. */
    public function markPaid(Withdrawal $withdrawal, ?User $admin, ?string $reference = null, bool $auto = false): Withdrawal
    {
        $paid = DB::transaction(function () use ($withdrawal, $admin, $reference, $auto) {
            $withdrawal = Withdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            if ($withdrawal->status !== 'pending') {
                throw new ConflictException('Lệnh rút này đã được xử lý.');
            }

            $withdrawal->update(['status' => 'paid', 'auto' => $auto, 'reference' => $reference, 'processed_by' => $admin?->id, 'processed_at' => now()]);

            return $withdrawal;
        });

        if ($admin) {
            AdminLog::record('pay_withdrawal', $paid, ['amount' => (float) $paid->amount, 'reference' => $reference], $admin);
        }
        SafeMail::queue($paid->user->email, new WithdrawalProcessed($paid->load('user')));

        return $paid;
    }

    /** Từ chối: tiền trả lại ví. */
    public function reject(Withdrawal $withdrawal, User $admin, string $reason): Withdrawal
    {
        $rejected = DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $withdrawal = Withdrawal::lockForUpdate()->findOrFail($withdrawal->id);
            if ($withdrawal->status !== 'pending') {
                throw new ConflictException('Lệnh rút này đã được xử lý.');
            }

            $withdrawal->update(['status' => 'rejected', 'reason' => $reason, 'processed_by' => $admin->id, 'processed_at' => now()]);
            $this->wallet->credit($withdrawal->user, (float) $withdrawal->amount, 'withdrawal_return', 'Lệnh rút bị từ chối, hoàn lại ví', ['withdrawal_id' => $withdrawal->id]);

            return $withdrawal;
        });

        AdminLog::record('reject_withdrawal', $rejected, ['amount' => (float) $rejected->amount, 'reason' => $reason], $admin);
        SafeMail::queue($rejected->user->email, new WithdrawalProcessed($rejected->load('user')));

        return $rejected;
    }

    /**
     * Tự duyệt khi tổng các lệnh tự duyệt trong 24 giờ (kể cả lệnh này) không vượt ngưỡng và người rút không có khiếu nại
     * đang mở (kể cả vai bị khiếu nại). Tính theo 24 giờ để tài khoản bị chiếm không rút cạn bằng nhiều lệnh nhỏ liên tiếp.
     */
    private function canAutoApprove(User $user, float $amount): bool
    {
        $limit = Settings::getInt('withdraw_auto_limit', 2000000);
        $alreadyAuto = (float) Withdrawal::where('user_id', $user->id)->where('auto', true)->where('processed_at', '>=', now()->subDay())->sum('amount');
        if ($amount + $alreadyAuto > $limit) {
            return false;
        }

        return ! Complaint::active()->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('against_user_id', $user->id))->exists();
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function destination(User $user, array $bank): array
    {
        if ($user->isApprovedOwner()) {
            $profile = $user->fieldOwnerProfile;
            $bin = $profile->bank_bin ?? Banks::guessBin($profile->bank_name);
            if (! $bin || ! $profile->bank_account_number || ! $profile->bank_account_holder) {
                throw new DomainException('Hồ sơ cơ sở chưa có đủ thông tin tài khoản ngân hàng. Vui lòng cập nhật trước khi rút tiền.');
            }

            return [$bin, $profile->bank_account_number, $profile->bank_account_holder];
        }

        foreach (['bank_bin', 'account_number', 'account_holder'] as $key) {
            if (empty($bank[$key])) {
                throw new DomainException('Vui lòng nhập đủ ngân hàng, số tài khoản và tên chủ tài khoản.');
            }
        }

        // Lưu lại để điền sẵn cho lần rút sau.
        $user->forceFill(['bank_bin' => $bank['bank_bin'], 'bank_account_number' => $bank['account_number'], 'bank_account_holder' => $bank['account_holder']])->save();

        return [$bank['bank_bin'], $bank['account_number'], $bank['account_holder']];
    }
}
