<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
use App\Mail\Notice;
use App\Mail\WalletCredited;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\SafeMail;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thu hộ: khách chuyển khoản vào tài khoản nền tảng, nền tảng ghi nhận (webhook ngân hàng hoặc admin)
 * rồi đơn được xác nhận ngay. Tiền chuyển cho chủ sân qua đối soát cuối kỳ sau khi trừ hoa hồng.
 */
class PaymentService
{
    /** Tạo một lần thanh toán cho các đơn vừa giữ chỗ (gọi trong transaction tạo đơn). */
    public function createFor(User $user, iterable $bookings, string $option, bool $useWallet = true): Payment
    {
        $bookings = collect($bookings);
        $total = (float) $bookings->sum('total_price');
        $earliestStart = $bookings->map(fn (Booking $b) => $b->startsAt())->min();

        $payment = Payment::create([
            'user_id' => $user->id,
            'code' => $this->uniqueCode(),
            'option' => $option,
            'total' => $total,
            'amount' => $this->amountFor($total, $option),
            'status' => 'awaiting',
            'due_at' => now()->addMinutes(Settings::getInt('payment_hold_minutes', 15))->min($earliestStart),
        ]);

        $this->allocate($payment, $bookings);
        if ($useWallet) {
            $this->applyWallet($payment);
        }

        return $payment->refresh();
    }

    /**
     * Trừ số dư ví vào lượt thanh toán đang chờ: đủ thì đơn được xác nhận ngay, thiếu thì QR chỉ còn phần chưa trả.
     * Gọi lại được (cộng dồn vào wallet_amount) khi khách nạp thêm tiền rồi bấm trả bằng ví.
     */
    public function applyWallet(Payment $payment): Payment
    {
        $payment = Payment::lockForUpdate()->findOrFail($payment->id);
        if ($payment->status !== 'awaiting' || $payment->purpose !== 'booking') {
            return $payment;
        }

        $use = min((float) $payment->user->refresh()->wallet_balance, $payment->dueAmount());
        if ($use <= 0) {
            return $payment;
        }

        app(WalletService::class)->debit($payment->user, $use, 'booking_payment', "Thanh toán {$payment->code} bằng ví", ['payment_id' => $payment->id]);
        $payment->update(['wallet_amount' => (float) $payment->wallet_amount + $use]);

        return $payment->dueAmount() <= 0 ? $this->markPaid($payment, 'wallet') : $payment->refresh();
    }

    /** Khách đổi giữa trả cọc và trả đủ khi chưa chuyển khoản. */
    public function changeOption(User $user, Payment $payment, string $option): Payment
    {
        return DB::transaction(function () use ($user, $payment, $option) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->user_id === $user->id, 404);
            if ($payment->status !== 'awaiting') {
                throw new ConflictException('Lượt thanh toán này không còn chờ chuyển khoản.');
            }

            // Hoàn phần đã trừ ví về ví rồi tính lại theo số tiền mới (ví được trừ lại nếu còn đủ).
            $this->returnWallet($payment);
            $payment->update(['option' => $option, 'amount' => $this->amountFor((float) $payment->total, $option), 'wallet_amount' => 0]);
            $this->allocate($payment, $payment->bookings()->get());

            return $this->applyWallet($payment);
        });
    }

    /** Khách huỷ khi chưa chuyển khoản: nhả toàn bộ chỗ đang giữ. */
    public function cancelAwaiting(User $user, Payment $payment, ?string $reason = null): Payment
    {
        abort_unless($payment->user_id === $user->id, 404);

        return $this->release($payment, 'cancelled', $reason ?: 'Khách huỷ trước khi thanh toán.');
    }

    /**
     * Ghi nhận tiền đã vào tài khoản nền tảng → xác nhận các đơn.
     * Tiền đến sau khi đã hết hạn giữ chỗ: còn trống thì vẫn xác nhận, đã có người khác lấy thì chờ hoàn tiền.
     */
    public function markPaid(Payment $payment, string $source, ?string $bankRef = null, ?User $admin = null): Payment
    {
        $lateRefund = null;
        $confirmed = DB::transaction(function () use ($payment, $source, $bankRef, $admin, &$lateRefund) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if (in_array($payment->status, ['paid', 'refunded'], true)) {
                return null; // đã ghi nhận rồi
            }

            if ($payment->purpose === 'topup') {
                $this->creditTopup($payment, (float) $payment->amount, ['paid_at' => now(), 'source' => $source, 'bank_ref' => $bankRef, 'confirmed_by' => $admin?->id]);

                return null;
            }

            $bookings = $payment->bookings()->with('sportsField')->lockForUpdate()->get();
            $attributes = ['paid_at' => now(), 'source' => $source, 'bank_ref' => $bankRef, 'confirmed_by' => $admin?->id];

            // Khách đã tự huỷ, hoặc hết hạn mà chỗ đã bị người khác lấy: tiền vào thẳng ví khách.
            // Hết hạn mà đã trừ ví thì phần ví đã được trả lại, nên tiền ngân hàng đến muộn chỉ hoàn lại chứ không tự xác nhận.
            if ($payment->status === 'cancelled' || ($payment->status === 'expired' && ($payment->wallet_amount > 0 || ! $this->stillAvailable($bookings)))) {
                $payment->update($attributes + ['status' => 'refunded']);
                foreach ($bookings as $booking) {
                    $booking->update(['payment_status' => 'paid', 'paid_at' => now(), 'refund_amount' => $booking->payment_amount]);
                }
                $lateRefund = $payment->dueAmount();
                app(WalletService::class)->credit($payment->user, $lateRefund, 'refund', "Hoàn tiền chuyển khoản muộn {$payment->code}", ['payment_id' => $payment->id]);
                foreach ($bookings as $booking) {
                    $booking->update(['refund_status' => 'done', 'refunded_at' => now()]);
                }

                return null;
            }

            $payment->update($attributes + ['status' => 'paid']);
            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => BookingStatus::Confirmed->value,
                    'confirmed_at' => now(),
                    'cancelled_at' => null,
                    'cancel_reason' => null,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            return $bookings;
        });

        if ($admin) {
            AdminLog::record('confirm_payment', $payment, ['code' => $payment->code, 'amount' => (float) $payment->amount], $admin);
        }

        if ($lateRefund && $payment->user?->email) {
            SafeMail::queue($payment->user->email, new WalletCredited($payment->user->refresh(), $lateRefund, "Tiền chuyển khoản {$payment->code} đến sau khi giữ chỗ đã hết hạn nên được hoàn vào ví."));
        }

        foreach ($confirmed ?? [] as $booking) {
            $booking->load(['customer', 'sportsField.owner']);
            SafeMail::queue($booking->sportsField->owner->email, new NewBookingRequest($booking));
        }
        if ($confirmed && $confirmed->isNotEmpty()) {
            SafeMail::queue($confirmed->first()->customer->email, new BookingStatusChanged($confirmed->first()));
        }

        return $payment->refresh();
    }

    /**
     * Webhook ngân hàng (định dạng SePay): tìm mã SH… trong nội dung chuyển khoản.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleBankWebhook(array $payload): ?Payment
    {
        if (($payload['transferType'] ?? 'in') !== 'in') {
            return null;
        }

        $bankRef = (string) ($payload['referenceCode'] ?? $payload['id'] ?? '');
        if ($bankRef !== '' && (Payment::where('bank_ref', $bankRef)->exists() || WalletTransaction::where('bank_ref', $bankRef)->exists())) {
            return null; // ngân hàng gửi lặp
        }

        if (! preg_match('/SH[A-Z0-9]{6}/', Str::upper((string) ($payload['content'] ?? '')), $match)) {
            return null;
        }

        $payment = Payment::where('code', $match[0])->first();
        if (! $payment) {
            return null;
        }

        $received = (float) ($payload['transferAmount'] ?? 0);
        $due = $payment->dueAmount();

        // Nạp ví: số tiền thực nhận (bất kể thiếu hay dư) vào ví; chuyển lần hai cho lượt đã nạp cũng vào ví.
        if ($payment->purpose === 'topup') {
            if ($payment->status === 'paid') {
                $this->creditStray($payment, $received, $bankRef, "Chuyển khoản {$payment->code} thêm một lần nữa nên được cộng vào ví.");
            } elseif ($received > 0) {
                DB::transaction(fn () => $this->creditTopup(Payment::lockForUpdate()->findOrFail($payment->id), $received, ['paid_at' => now(), 'source' => 'webhook', 'bank_ref' => $bankRef ?: null]));
            }

            return null;
        }

        // Chuyển thiếu, hoặc chuyển cho lượt đã thanh toán: không tự xác nhận, tiền vào ví để khách tự dùng hoặc rút.
        if (! in_array($payment->status, ['awaiting', 'expired', 'cancelled'], true) || $received < $due) {
            $this->creditStray($payment, $received, $bankRef, $received < $due && $payment->status === 'awaiting'
                ? "Chuyển khoản {$payment->code} thiếu so với số cần trả nên được cộng vào ví."
                : "Chuyển khoản {$payment->code} cho lượt đã xử lý nên được cộng vào ví.");

            return null;
        }

        $confirmed = $this->markPaid($payment, 'webhook', $bankRef ?: null);
        if ($received > $due) {
            $this->creditStray($payment, $received - $due, null, "Chuyển khoản {$payment->code} dư so với số cần trả nên phần dư được cộng vào ví.");
        }

        return $confirmed;
    }

    /** Trả phần đã trừ ví của một lượt chưa thanh toán xong về ví khách (huỷ, hết hạn hoặc đổi số tiền). */
    private function returnWallet(Payment $payment): void
    {
        if ((float) $payment->wallet_amount > 0) {
            app(WalletService::class)->credit($payment->user, (float) $payment->wallet_amount, 'booking_payment_return', "Hoàn số dư ví đã giữ cho {$payment->code}", ['payment_id' => $payment->id]);
        }
    }

    /** Khách muốn nạp ví: tạo lượt chờ chuyển khoản vào tài khoản nền tảng, không gắn đơn nào. */
    public function createTopup(User $user, int $amount): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'purpose' => 'topup',
            'code' => $this->uniqueCode(),
            'option' => 'full',
            'total' => $amount,
            'amount' => $amount,
            'status' => 'awaiting',
            'due_at' => now()->addMinutes(30),
        ]);
    }

    /** Tiền nạp đã vào tài khoản nền tảng: cộng đúng số tiền thực nhận vào ví, mã giao dịch chống cộng trùng. */
    private function creditTopup(Payment $payment, float $received, array $attributes): void
    {
        $payment->update($attributes + ['status' => 'paid']);
        app(WalletService::class)->credit($payment->user, $received, 'topup', "Nạp ví {$payment->code}", ['payment_id' => $payment->id, 'bank_ref' => $attributes['bank_ref'] ?? null]);
        SafeMail::queue($payment->user->email, new WalletCredited($payment->user->refresh(), $received, "Nạp tiền vào ví {$payment->code}."));
    }

    /** Tiền khách chuyển mà không khớp lượt nào: cộng vào ví khách, ghi mã giao dịch để không cộng trùng. */
    private function creditStray(Payment $payment, float $amount, ?string $bankRef, string $reason): void
    {
        if ($amount <= 0 || ! $payment->user) {
            return;
        }

        app(WalletService::class)->credit($payment->user, $amount, 'refund', $reason, ['payment_id' => $payment->id, 'bank_ref' => $bankRef ?: null]);
        SafeMail::queue($payment->user->email, new WalletCredited($payment->user->refresh(), $amount, $reason));
    }

    /** Chạy định kỳ: lượt thanh toán quá hạn giữ chỗ → huỷ các đơn, nhả chỗ cho người khác. */
    public function expireDue(): int
    {
        $count = 0;
        Payment::where('status', 'awaiting')->where('due_at', '<=', now())->get()
            ->each(function (Payment $payment) use (&$count) {
                $this->release($payment, 'expired', 'Quá hạn thanh toán nên đơn tự huỷ.');
                $count++;
            });

        return $count;
    }

    /** Huỷ các đơn đang giữ chỗ của một lượt thanh toán chưa trả. */
    private function release(Payment $payment, string $status, string $reason): Payment
    {
        $released = DB::transaction(function () use ($payment, $status, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'awaiting') {
                throw new ConflictException('Lượt thanh toán này không còn chờ chuyển khoản.');
            }

            $payment->update(['status' => $status]);
            $this->returnWallet($payment);
            $bookings = $payment->bookings()->where('status', BookingStatus::Pending->value)->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $booking->update(['status' => BookingStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            }

            return $bookings;
        });

        foreach ($released as $booking) {
            app(WaitlistService::class)->handleBookingFreed($booking);
        }

        // Hết hạn giữ chỗ (khách không tự huỷ): báo để khách biết chỗ đã được nhả.
        if ($status === 'expired' && $released->isNotEmpty()) {
            SafeMail::queue($payment->user->email, new Notice(
                'Giữ chỗ đã hết hạn',
                ["Lượt đặt sân mã {$payment->code} ({$released->count()} khung giờ) đã hết thời gian giữ chỗ nên được huỷ và nhả cho người khác.", 'Bạn có thể đặt lại bất cứ lúc nào. Nếu đã lỡ chuyển khoản, tiền sẽ được hoàn vào ví SportHub của bạn.'],
                'Đặt sân lại',
                '/fields',
            ));
        }

        return $payment->refresh();
    }

    /** Các khoảng giờ của đơn (đã bị huỷ do hết hạn) vẫn còn trống để xác nhận lại. */
    private function stillAvailable(Collection $bookings): bool
    {
        return $bookings->every(fn (Booking $booking) => ! $booking->sportsField->closureBlocking($booking->booking_date->toDateString(), $booking->start_time, $booking->end_time)
            && ! Booking::where('sports_field_id', $booking->sports_field_id)
                ->whereKeyNot($booking->id)
                ->whereDate('booking_date', $booking->booking_date)
                ->whereIn('status', BookingStatus::blockingCases())
                ->overlapping($booking->start_time, $booking->end_time)
                ->exists());
    }

    /** Cọc: % tổng tiền, làm tròn lên nghìn đồng; trả đủ: toàn bộ. */
    private function amountFor(float $total, string $option): float
    {
        if ($option === 'full') {
            return $total;
        }

        return min(ceil($total * Settings::getInt('deposit_percent', 30) / 100 / 1000) * 1000, $total);
    }

    /** Chia số tiền khách trả về từng đơn theo tỷ lệ giá (đơn cuối nhận phần lẻ) để tính hoàn tiền và hoa hồng. */
    private function allocate(Payment $payment, Collection $bookings): void
    {
        $remaining = (float) $payment->amount;
        $last = $bookings->count() - 1;

        foreach ($bookings->values() as $index => $booking) {
            $share = $index === $last
                ? $remaining
                : round((float) $payment->amount * (float) $booking->total_price / max((float) $payment->total, 1));
            $remaining -= $share;

            $booking->update([
                'payment_id' => $payment->id,
                'payment_status' => 'awaiting',
                'payment_option' => $payment->option,
                'payment_amount' => $share,
            ]);
        }
    }

    private function uniqueCode(): string
    {
        // Ngắn, không dấu, không khoảng trắng để ngân hàng nào cũng giữ nguyên nội dung chuyển khoản.
        do {
            $code = 'SH'.Str::upper(Str::random(6));
        } while (! preg_match('/^SH[A-Z0-9]{6}$/', $code) || Payment::where('code', $code)->exists());

        return $code;
    }
}
