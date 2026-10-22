<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
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
    public function createFor(User $user, iterable $bookings, string $option): Payment
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

        return $payment;
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

            $payment->update(['option' => $option, 'amount' => $this->amountFor((float) $payment->total, $option)]);
            $this->allocate($payment, $payment->bookings()->get());

            return $payment;
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
            if (in_array($payment->status, ['paid', 'refund_required'], true)) {
                return null; // đã ghi nhận rồi
            }

            $bookings = $payment->bookings()->with('sportsField')->lockForUpdate()->get();
            $attributes = ['paid_at' => now(), 'source' => $source, 'bank_ref' => $bankRef, 'confirmed_by' => $admin?->id];

            // Khách đã tự huỷ, hoặc hết hạn mà chỗ đã bị người khác lấy: tiền vào thẳng ví khách.
            if ($payment->status === 'cancelled' || ($payment->status === 'expired' && ! $this->stillAvailable($bookings))) {
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
            $bookings = $payment->bookings()->where('status', BookingStatus::Pending->value)->lockForUpdate()->get();
            foreach ($bookings as $booking) {
                $booking->update(['status' => BookingStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            }

            return $bookings;
        });

        foreach ($released as $booking) {
            app(WaitlistService::class)->handleBookingFreed($booking);
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
