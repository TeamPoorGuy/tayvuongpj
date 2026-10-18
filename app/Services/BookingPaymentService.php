<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Mail\BookingPaymentReported;
use App\Mail\BookingStatusChanged;
use App\Models\Booking;
use App\Models\User;
use App\Support\SafeMail;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Thanh toán chuyển khoản trực tiếp cho chủ sân (không qua cổng, không giữ tiền hộ):
 * chủ sân duyệt → khách chuyển khoản đúng số tiền + nội dung rồi báo → chủ sân đối chiếu và xác nhận.
 */
class BookingPaymentService
{
    public function __construct(
        private readonly StatementService $statementService = new StatementService(),
    ) {}

    /** Chủ sân duyệt đơn: mở cửa sổ thanh toán cho khách. */
    public function approve(User $owner, Booking $booking): Booking
    {
        if ($this->statementService->hasOverdueDebt($owner)) {
            throw new ConflictException('Tài khoản của bạn đang có kỳ đối soát nợ phí nền tảng quá hạn. Vui lòng thanh toán phí đối soát để tiếp tục duyệt đơn.');
        }

        $booking = $this->locked($booking, 'manage', $owner, function (Booking $booking) {
            if ($booking->status !== BookingStatus::Pending || $booking->payment_status !== null) {
                throw new ConflictException('Đơn này đã được xử lý.');
            }
            if ($booking->startsAt()->isPast()) {
                throw new ConflictException('Đơn đã quá giờ bắt đầu nên không thể duyệt. Hãy từ chối đơn này.');
            }

            $profile = $booking->sportsField->owner->fieldOwnerProfile;
            if (! $profile?->bank_name || ! $profile->bank_account_number || ! $profile->bank_account_holder) {
                throw new ConflictException('Bạn chưa khai tài khoản ngân hàng nhận tiền. Hãy cập nhật ở mục Hồ sơ cơ sở rồi duyệt lại.');
            }

            $booking->update([
                'payment_status' => 'awaiting',
                // Nội dung chuyển khoản: ngắn, không dấu, không khoảng trắng để ngân hàng nào cũng giữ nguyên.
                'payment_code' => 'SH'.$booking->id.Str::upper(Str::random(4)),
                'payment_due_at' => $this->dueAt($booking),
            ]);
        });

        SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));

        return $booking;
    }

    /** Khách báo đã chuyển khoản (cọc hoặc trả hết). */
    public function report(User $customer, Booking $booking, string $option): Booking
    {
        $booking = $this->locked($booking, 'cancel', $customer, function (Booking $booking) use ($option) {
            if ($booking->status !== BookingStatus::Pending || $booking->payment_status !== 'awaiting') {
                throw new ConflictException('Đơn này hiện không ở bước chờ thanh toán.');
            }
            if ($booking->payment_due_at?->isPast()) {
                throw new ConflictException('Đã quá hạn thanh toán của đơn này.');
            }

            $booking->update([
                'payment_status' => 'reported',
                'payment_option' => $option,
                'payment_amount' => $option === 'full' ? $booking->total_price : $booking->depositAmount(),
                'payment_reported_at' => now(),
            ]);
        });

        SafeMail::queue($booking->sportsField->owner->email, new BookingPaymentReported($booking));

        return $booking;
    }

    /** Chủ sân đối chiếu sao kê: đã nhận tiền → đơn được xác nhận; chưa nhận → trả về chờ thanh toán. */
    public function resolve(User $owner, Booking $booking, bool $received): Booking
    {
        $booking = $this->locked($booking, 'manage', $owner, function (Booking $booking) use ($received) {
            if ($booking->status !== BookingStatus::Pending || $booking->payment_status !== 'reported') {
                throw new ConflictException('Khách chưa báo chuyển khoản cho đơn này.');
            }

            $booking->update($received
                ? ['status' => BookingStatus::Confirmed->value, 'confirmed_at' => now(), 'payment_status' => 'paid', 'paid_at' => now()]
                : ['payment_status' => 'awaiting', 'payment_reported_at' => null, 'payment_due_at' => $this->dueAt($booking)]);
        });

        SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));

        return $booking;
    }

    /** Hạn chuyển khoản: N giờ kể từ bây giờ nhưng không muộn hơn giờ bắt đầu chơi. */
    private function dueAt(Booking $booking): CarbonInterface
    {
        return now()->addHours(Settings::getInt('payment_window_hours', 2))->min($booking->startsAt());
    }

    /** Khoá đơn, kiểm quyền theo BookingPolicy rồi chạy $change trong cùng transaction. */
    private function locked(Booking $booking, string $ability, User $user, Closure $change): Booking
    {
        return DB::transaction(function () use ($booking, $ability, $user, $change) {
            $booking = Booking::with(['sportsField.owner.fieldOwnerProfile', 'timeSlot', 'customer'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            Gate::forUser($user)->authorize($ability, $booking);
            $change($booking);

            return $booking->refresh()->load([...Booking::resourceRelations(withCustomer: true), 'sportsField.owner.fieldOwnerProfile']);
        });
    }
}
