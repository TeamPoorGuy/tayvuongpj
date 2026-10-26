<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Mail\BookingStatusChanged;
use App\Mail\Notice;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\Payment;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\SafeMail;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BookingService
{
    public function customerBookings(User $user, ?string $status = null): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(Booking::resourceRelations())
            ->latest()
            ->paginate(10);
    }

    public function __construct(private readonly PaymentService $payments = new PaymentService) {}

    /**
     * Giữ chỗ một hay nhiều khoảng giờ (có thể khác sân) và tạo một lượt thanh toán chung.
     * Đơn ở trạng thái chờ cho tới khi tiền vào tài khoản nền tảng.
     */
    public function create(User $user, CreateBookingData $data): Payment
    {
        $this->assertWithinAdvanceLimit($data->bookingDate);
        $date = Carbon::parse($data->bookingDate)->toDateString();

        return DB::transaction(function () use ($user, $data, $date) {
            $this->assertPendingLimit($user);

            // Khoá các sân theo thứ tự id để hai lượt đặt đồng thời không chờ nhau vòng tròn.
            $fieldIds = collect($data->items)->pluck('sports_field_id')->unique()->sort()->values();
            $fields = SportsField::query()->approved()->whereIn('id', $fieldIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $bookings = [];
            foreach ($data->items as $item) {
                $field = $fields->get($item['sports_field_id'])
                    ?? throw new ConflictException('Sân chưa được duyệt hoặc đang dừng hoạt động.');
                [$start, $end, $slotId] = $this->resolveRange($field, $date, $item);
                $bookings[] = $this->reserve($user, $field, $date, $start, $end, $slotId, $data->notes);
            }

            return $this->payments->createFor($user, $bookings, $data->paymentOption, $data->useWallet)->load('bookings');
        });
    }

    /** Khoảng giờ của một mục: theo khung giờ có sẵn, hoặc giờ tự chọn trên lưới 30 phút. */
    private function resolveRange(SportsField $field, string $date, array $item): array
    {
        if (! empty($item['time_slot_id'])) {
            $slot = TimeSlot::query()->where('sports_field_id', $field->id)->where('is_active', true)->findOrFail($item['time_slot_id']);
            if (! $slot->appliesTo($date)) {
                throw new ConflictException('Khung giờ này không áp dụng cho ngày đã chọn.');
            }

            return [$slot->start_time, $slot->end_time, $slot->id];
        }

        $start = Booking::normalizeTime($item['start_time']);
        $end = Booking::normalizeTime($item['end_time']);
        $minutes = Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
        $minMinutes = Settings::getInt('min_minutes', 60);
        if ($minutes < $minMinutes) {
            throw new ConflictException("Mỗi lượt đặt tối thiểu {$minMinutes} phút ({$field->name}).");
        }

        return [$start, $end, null];
    }

    /** Kiểm tra và giữ chỗ một khoảng giờ trên một sân đã khoá dòng. */
    private function reserve(User $user, SportsField $field, string $date, string $start, string $end, ?int $slotId, ?string $notes, ?string $groupId = null): Booking
    {
        if ($field->field_owner_id === $user->id) {
            throw new ForbiddenException('Bạn không thể đặt sân của chính mình.');
        }

        if ($closure = $field->closureOn($date)) {
            throw new ConflictException("Sân tạm đóng cửa từ {$closure->start_date->format('d/m/Y')} đến {$closure->end_date->format('d/m/Y')}: {$closure->reason}");
        }
        if ($lock = $field->closureBlocking($date, $start, $end)) {
            throw new ConflictException("Sân tạm khoá khung giờ này: {$lock->reason}");
        }

        if ($date.' '.$start <= now()->format('Y-m-d H:i:s')) {
            throw new ConflictException('Khung giờ này đã qua. Vui lòng chọn giờ khác.');
        }

        $isBooked = Booking::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', BookingStatus::blockingCases())
            ->overlapping($start, $end)
            ->lockForUpdate()
            ->exists();

        if ($isBooked) {
            throw new ConflictException("Khung giờ {$this->label($start, $end)} ở {$field->name} đã có người đặt. Vui lòng chọn giờ khác.");
        }

        // Đang giữ 15 phút cho người đứng đầu hàng đợi: không cho người khác chen ngang.
        $offer = BookingWaitlist::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->where('status', 'offered')
            ->where('offer_expires_at', '>', now())
            ->overlapping($start, $end)
            ->lockForUpdate()
            ->first();

        if ($offer && $offer->user_id !== $user->id) {
            throw new ConflictException('Khung giờ đang được giữ cho người trong hàng đợi đến '.$offer->offer_expires_at->format('H:i').'. Vui lòng thử lại sau.');
        }
        $offer?->update(['status' => 'accepted']);

        return Booking::create([
            'user_id' => $user->id,
            'sports_field_id' => $field->id,
            'time_slot_id' => $slotId,
            'booking_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => BookingStatus::Pending->value,
            'total_price' => $field->priceFor($date, $start, $end),
            'notes' => $notes,
            'recurring_group_id' => $groupId,
        ]);
    }

    private function assertWithinAdvanceLimit(string $date): void
    {
        $maxAdvanceDays = Settings::getInt('max_advance_days', 30);
        if (Carbon::parse($date)->gt(today()->addDays($maxAdvanceDays))) {
            throw new ConflictException("Chỉ được đặt sân trước tối đa {$maxAdvanceDays} ngày.");
        }
    }

    /** Giới hạn số lượt giữ chỗ chưa thanh toán (mỗi lượt có thể gồm nhiều ô). */
    private function assertPendingLimit(User $user): void
    {
        $maxPending = Settings::getInt('max_pending_bookings', 3);
        $pendingCount = Payment::where('user_id', $user->id)->where('purpose', 'booking')->where('status', 'awaiting')->lockForUpdate()->count();

        if ($pendingCount >= $maxPending) {
            throw new ConflictException("Bạn đang có {$pendingCount} lượt đặt chờ thanh toán. Tối đa chỉ được có {$maxPending} lượt cùng lúc.");
        }
    }

    /** Khách huỷ đơn đã thanh toán: báo chủ sân và gửi khách xác nhận số tiền đã hoàn vào ví. */
    private function notifyCancelled(Booking $booking): void
    {
        $booking->loadMissing(['sportsField.owner', 'customer']);
        $when = $booking->booking_date->format('d/m/Y').' '.$this->label($booking->start_time, $booking->end_time);

        SafeMail::queue($booking->sportsField->owner?->email, new Notice(
            "Khách đã huỷ đơn #BK-{$booking->id}",
            ["Khách {$booking->customer->name} đã huỷ đơn đặt {$booking->sportsField->name} ({$when}). Khung giờ này đã được mở lại cho khách khác."],
            'Xem đơn đặt sân',
            '/owner/bookings',
        ));

        $refund = $booking->refundedAmount();
        SafeMail::queue($booking->customer->email, new Notice(
            "Đã huỷ đơn #BK-{$booking->id}",
            [
                "Đơn đặt {$booking->sportsField->name} ({$when}) đã được huỷ.",
                $refund > 0
                    ? 'SportHub đã hoàn '.number_format($refund, 0, ',', '.').'đ vào ví của bạn.'
                    : 'Đơn huỷ sát giờ nên không được hoàn tiền theo chính sách huỷ.',
            ],
            'Xem ví SportHub',
            '/wallet',
        ));
    }

    /** Nhắc khách trước giờ chơi (mặc định 2 giờ) kèm mã check-in; mỗi đơn chỉ nhắc một lần. */
    public function sendReminders(int $hoursBefore = 2): int
    {
        $count = 0;
        Booking::with(['customer', 'sportsField'])
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('reminded_at')
            ->whereDate('booking_date', '>=', today())
            ->whereDate('booking_date', '<=', today()->addDay())
            ->lazyById()
            ->each(function (Booking $booking) use ($hoursBefore, &$count) {
                $start = $booking->startsAt();
                if ($start->isPast() || $start->gt(now()->addHours($hoursBefore))) {
                    return;
                }

                $booking->update(['reminded_at' => now()]);
                SafeMail::queue($booking->customer->email, new Notice(
                    "Sắp đến giờ chơi tại {$booking->sportsField->name}",
                    [
                        "Đơn #BK-{$booking->id} của bạn bắt đầu lúc {$this->label($booking->start_time, $booking->end_time)} hôm nay.",
                        $booking->check_in_code ? "Mã check-in khi đến sân: {$booking->check_in_code}" : 'Hãy đến đúng giờ và check-in với chủ sân.',
                        'Không đến mà không huỷ sẽ bị ghi vắng mặt và trừ điểm uy tín.',
                    ],
                    'Xem đơn đặt sân',
                    '/bookings',
                ));
                $count++;
            });

        return $count;
    }

    private function label(string $start, string $end): string
    {
        return substr($start, 0, 5).'–'.substr($end, 0, 5);
    }

    public function cancel(User $user, Booking $booking, ?string $reason): Booking
    {
        Gate::forUser($user)->authorize('cancel', $booking);

        if (! $booking->canBeCancelled()) {
            throw new ConflictException('Đơn đặt sân này không còn đủ điều kiện để hủy.');
        }

        // Chưa thanh toán: huỷ cả lượt giữ chỗ (mọi ô đặt cùng lúc), không phạt.
        if ($booking->status === BookingStatus::Pending && $booking->payment?->status === 'awaiting') {
            $this->payments->cancelAwaiting($user, $booking->payment, $reason);

            return $booking->refresh()->load(Booking::resourceRelations());
        }

        if ($booking->isLateToCancel()) {
            TrustScoreService::penalizeLateCancellation($user, $booking);
        }

        $booking->markCancelled('customer', $reason ?: 'Khách hàng tự hủy đơn.');

        app(WaitlistService::class)->handleBookingFreed($booking);
        $this->notifyCancelled($booking);

        return $booking->refresh()->load(Booking::resourceRelations());
    }

    /**
     * Chạy định kỳ: lượt thanh toán quá hạn giữ chỗ → huỷ đơn; đơn đã xác nhận quá giờ kết thúc → hoàn thành.
     *
     * @return array{rejected:int, completed:int}
     */
    public function syncStatuses(): array
    {
        $count = ['rejected' => $this->payments->expireDue(), 'completed' => 0];

        // Đơn chờ từ trước khi có thu hộ (không gắn lượt thanh toán) đã tới giờ chơi thì đóng lại.
        Booking::with('customer')
            ->where('status', BookingStatus::Pending->value)
            ->whereNull('payment_id')
            ->whereDate('booking_date', '<=', today())
            ->lazyById()
            ->each(function (Booking $booking) use (&$count) {
                if (! $booking->startsAt()->isPast()) {
                    return;
                }
                $booking->markCancelled('system', 'Quá giờ chơi mà đơn chưa được thanh toán.', BookingStatus::Rejected);
                SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
                $count['rejected']++;
            });

        Booking::with('customer')
            ->where('status', BookingStatus::Confirmed->value)
            ->whereDate('booking_date', '<=', today())
            ->lazyById()
            ->each(function (Booking $booking) use (&$count) {
                if (! $booking->endsAt()->isPast()) {
                    return;
                }
                $attributes = ['status' => BookingStatus::Completed->value];
                // Đã xác nhận nhưng không check-in: ghi nhận vắng mặt và trừ điểm uy tín đúng 1 lần.
                if ($booking->checked_in_at === null && $booking->no_show_at === null) {
                    $attributes['no_show_at'] = now();
                    TrustScoreService::penalizeNoShow($booking->customer, $booking);
                }
                $booking->update($attributes);
                $count['completed']++;

                // Đã chơi thật (có check-in): mời đánh giá.
                if ($booking->checked_in_at !== null) {
                    SafeMail::queue($booking->customer->email, new Notice(
                        'Bạn thấy trận đấu hôm nay thế nào?',
                        ["Cảm ơn bạn đã chơi tại đơn #BK-{$booking->id}. Đánh giá của bạn giúp những người chơi khác chọn sân tốt hơn."],
                        'Đánh giá sân',
                        '/bookings',
                    ));
                }
            });

        return $count;
    }

    /** Đặt cố định hàng tuần (2 - 8 tuần). */
    public function createRecurring(User $user, array $data): array
    {
        $sportsFieldId = (int) $data['sports_field_id'];
        $timeSlotId = isset($data['time_slot_id']) ? (int) $data['time_slot_id'] : null;
        $startDate = Carbon::parse($data['start_date'])->toDateString();
        $weeks = max(2, min(8, (int) ($data['weeks'] ?? 4)));
        $skipConflicts = (bool) ($data['skip_conflicts'] ?? false);
        $notes = $data['notes'] ?? null;
        $paymentOption = $data['payment_option'] ?? 'deposit';
        $useWallet = (bool) ($data['use_wallet'] ?? true);

        $maxAdvanceDays = Settings::getInt('max_advance_days', 60);
        $lastDate = Carbon::parse($startDate)->addWeeks($weeks - 1);
        if ($lastDate->gt(today()->addDays($maxAdvanceDays))) {
            throw new ConflictException("Chuỗi đặt cố định vượt quá giới hạn đặt trước ({$maxAdvanceDays} ngày).");
        }

        return DB::transaction(function () use ($user, $data, $sportsFieldId, $timeSlotId, $startDate, $weeks, $skipConflicts, $notes, $paymentOption, $useWallet) {
            $field = SportsField::query()->approved()->lockForUpdate()->find($sportsFieldId);
            if (! $field) {
                throw new ConflictException('Sân chưa được duyệt hoặc đang dừng hoạt động.');
            }
            if ($field->field_owner_id === $user->id) {
                throw new ForbiddenException('Bạn không thể đặt sân của chính mình.');
            }

            $this->assertPendingLimit($user);

            // Một khung giờ có sẵn, hoặc một khoảng giờ chọn trên lưới 30 phút (lặp lại mỗi tuần).
            if ($timeSlotId) {
                $slot = TimeSlot::query()
                    ->where('sports_field_id', $field->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($timeSlotId);
            } else {
                [$start, $end] = $this->resolveRange($field, $startDate, $data);
                $slot = new TimeSlot(['start_time' => $start, 'end_time' => $end]);
            }

            $conflicts = [];
            $availableDates = [];

            for ($i = 0; $i < $weeks; $i++) {
                $currentDate = Carbon::parse($startDate)->addWeeks($i)->toDateString();

                if ($currentDate.' '.$slot->start_time <= now()->format('Y-m-d H:i:s')) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => 'Khung giờ này đã trôi qua.'];
                    continue;
                }

                if (! $slot->appliesTo($currentDate)) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => 'Khung giờ này không áp dụng cho ngày đó.'];
                    continue;
                }

                if ($closure = $field->closureBlocking($currentDate, $slot->start_time, $slot->end_time)) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => "Sân đóng cửa: {$closure->reason}"];
                    continue;
                }

                try {
                    $field->priceFor($currentDate, $slot->start_time, $slot->end_time);
                } catch (DomainException) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => 'Ngoài giờ mở cửa của sân ngày đó.'];
                    continue;
                }

                $isBooked = Booking::query()
                    ->where('sports_field_id', $field->id)
                    ->whereDate('booking_date', $currentDate)
                    ->whereIn('status', BookingStatus::blockingCases())
                    ->overlapping($slot->start_time, $slot->end_time)
                    ->lockForUpdate()
                    ->exists();

                if ($isBooked) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => 'Khung giờ này đã có người đặt.'];
                    continue;
                }

                $offer = BookingWaitlist::query()
                    ->where('sports_field_id', $field->id)
                    ->whereDate('booking_date', $currentDate)
                    ->where('status', 'offered')
                    ->where('offer_expires_at', '>', now())
                    ->overlapping($slot->start_time, $slot->end_time)
                    ->lockForUpdate()
                    ->first();

                if ($offer && $offer->user_id !== $user->id) {
                    $conflicts[] = ['date' => $currentDate, 'reason' => 'Khung giờ đang được giữ cho người trong hàng đợi.'];
                    continue;
                }

                $availableDates[] = ['date' => $currentDate, 'offer' => $offer];
            }

            if (count($conflicts) > 0 && ! $skipConflicts) {
                return [
                    'has_conflicts' => true,
                    'conflicts' => $conflicts,
                    'available_dates' => array_column($availableDates, 'date'),
                    'message' => 'Có '.count($conflicts).' ngày trong chuỗi bị trùng lịch hoặc sân nghỉ.',
                ];
            }

            if (empty($availableDates)) {
                throw new ConflictException('Tất cả các ngày trong chuỗi lặp lại đều đã có người đặt hoặc sân nghỉ.');
            }

            $groupId = (string) Str::uuid();

            $created = [];
            foreach ($availableDates as $item) {
                // Giá tính theo từng ngày vì khung giờ có thể có giá riêng cho cuối tuần.
                $totalPrice = $field->priceFor($item['date'], $slot->start_time, $slot->end_time);
                $booking = Booking::create([
                    'user_id' => $user->id,
                    'sports_field_id' => $field->id,
                    'time_slot_id' => $slot->id,
                    'booking_date' => $item['date'],
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'status' => BookingStatus::Pending->value,
                    'total_price' => $totalPrice,
                    'notes' => $notes,
                    'recurring_group_id' => $groupId,
                ]);
                $item['offer']?->update(['status' => 'accepted']);
                $created[] = $booking;
            }

            // Cả chuỗi trả trong một lần chuyển khoản.
            $payment = $this->payments->createFor($user, $created, $paymentOption, $useWallet);

            return [
                'has_conflicts' => false,
                'recurring_group_id' => $groupId,
                'payment' => $payment,
                'total_bookings' => count($created),
                'bookings' => Booking::where('recurring_group_id', $groupId)->with(Booking::resourceRelations(withCustomer: true))->get(),
                'skipped_dates' => $conflicts,
            ];
        });
    }

    /** Khách huỷ các buổi sắp tới trong chuỗi đặt cố định. */
    public function cancelRecurring(User $user, string $groupId, ?string $reason = null): Collection
    {
        $bookings = Booking::where('recurring_group_id', $groupId)
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->whereDate('booking_date', '>=', today())
            ->get();

        if ($bookings->isEmpty()) {
            throw new ConflictException('Không có buổi nào sắp tới trong chuỗi để huỷ.');
        }

        $cancelled = collect();
        foreach ($bookings as $booking) {
            // Huỷ một buổi chưa thanh toán sẽ huỷ cả lượt giữ chỗ, nên đọc lại trạng thái mới nhất.
            $booking->refresh();
            if ($booking->status === BookingStatus::Cancelled) {
                $cancelled->push($booking);
            } elseif ($booking->canBeCancelled()) {
                $cancelled->push($this->cancel($user, $booking, $reason ?: 'Khách huỷ chuỗi đặt cố định.'));
            }
        }

        if ($cancelled->isEmpty()) {
            throw new ConflictException('Các buổi sắp tới trong chuỗi không còn đủ điều kiện để huỷ.');
        }

        return new Collection($cancelled->all());
    }
}
