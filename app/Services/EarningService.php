<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tiền thu hộ về ví chủ sân: mỗi đơn đã kết thúc quá hạn kháng cáo (và không có khiếu nại của khách đang mở)
 * được cộng vào ví chủ sân sau khi trừ hoàn tiền và hoa hồng. Chủ sân rút ví khi muốn.
 */
class EarningService
{
    public function __construct(private readonly WalletService $wallet) {}

    /** Chạy định kỳ: cộng ví chủ sân cho các đơn đã đến hạn. */
    public function releaseDue(): int
    {
        $hours = config('booking.no_show_appeal_hours');
        $count = 0;

        Booking::query()
            ->whereNotNull('payment_id')
            ->where('payment_status', 'paid')
            ->whereNull('earning_released_at')
            ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Cancelled->value])
            ->whereDate('booking_date', '<=', now()->subHours($hours)->toDateString())
            ->whereDoesntHave('complaints', fn ($c) => $c->holdingPayout())
            ->lazyById()
            ->each(function (Booking $booking) use ($hours, &$count) {
                if ($booking->endsAt()->addHours($hours)->isPast() && $this->release($booking)) {
                    $count++;
                }
            });

        return $count;
    }

    /** Cộng phần chủ sân được hưởng của một đơn vào ví (một lần duy nhất). */
    public function release(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $owner = $this->ownerOf($booking);
            if ($booking->earning_released_at !== null || ! $owner) {
                return false;
            }

            $share = $booking->ownerShare();
            $booking->update(['earning_amount' => $share['earning'], 'commission_amount' => $share['commission'], 'earning_released_at' => now()]);
            if ($share['earning'] > 0) {
                $this->wallet->credit($owner, $share['earning'], 'owner_earning', "Tiền đơn #BK-{$booking->id} (đã trừ hoa hồng {$booking->commission_rate}%)", ['booking_id' => $booking->id]);
            }

            return true;
        });
    }

    /**
     * Số tiền của đơn đã đổi sau khi cộng ví (hoàn tiền hoặc gỡ vắng mặt theo khiếu nại): điều chỉnh phần chênh vào ví chủ sân.
     * Ví chủ sân được phép âm; âm thì chưa rút được tới khi có thêm tiền về.
     */
    public function reconcile(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $owner = $this->ownerOf($booking);
            if ($booking->earning_released_at === null || ! $owner) {
                return;
            }

            $share = $booking->ownerShare();
            $delta = $share['earning'] - (float) $booking->earning_amount;
            $booking->update(['earning_amount' => $share['earning'], 'commission_amount' => $share['commission']]);
            if ($delta > 0) {
                $this->wallet->credit($owner, $delta, 'owner_adjustment', "Điều chỉnh đơn #BK-{$booking->id}", ['booking_id' => $booking->id]);
            } elseif ($delta < 0) {
                $this->wallet->debit($owner, -$delta, 'owner_adjustment', "Điều chỉnh đơn #BK-{$booking->id} (hoàn tiền cho khách)", ['booking_id' => $booking->id], allowNegative: true);
            }
        });
    }

    /** Tiền sắp về ví chủ sân: đơn đã thanh toán nhưng chưa tới hạn cộng ví. */
    public function pendingFor(User $owner): float
    {
        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');

        return (float) Booking::whereIn('sports_field_id', $fieldIds)
            ->whereNotNull('payment_id')
            ->where('payment_status', 'paid')
            ->whereNull('earning_released_at')
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value, BookingStatus::Cancelled->value])
            ->get()
            ->sum(fn (Booking $b) => $b->ownerShare()['earning']);
    }

    private function ownerOf(Booking $booking): ?User
    {
        $ownerId = SportsField::withTrashed()->whereKey($booking->sports_field_id)->value('field_owner_id');

        return $ownerId ? User::find($ownerId) : null;
    }
}
