<?php

use App\Mail\BookingStatusChanged;
use App\Models\Booking;
use App\Models\MatchRequest;
use App\Services\TrustScoreService;
use App\Services\WaitlistService;
use App\Support\SafeMail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:sync', function (WaitlistService $waitlistService) {
    $count = ['rejected' => 0, 'completed' => 0];
    $expireBefore = now()->subHours((int) config('booking.pending_expire_hours', 24));

    Booking::with(['timeSlot', 'customer', 'sportsField'])
        ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
        ->where(fn ($query) => $query->whereDate('booking_date', '<=', today())
            ->orWhere(fn ($query) => $query->where('status', Booking::STATUS_PENDING)->where('created_at', '<=', $expireBefore)))
        ->lazyById()
        ->each(function (Booking $booking) use (&$count, $expireBefore, $waitlistService) {
            $unanswered = $booking->created_at->lte($expireBefore);
            if ($booking->status === Booking::STATUS_PENDING && ($unanswered || $booking->startsAt()->isPast())) {
                $booking->update([
                    'status' => Booking::STATUS_REJECTED,
                    'cancelled_at' => now(),
                    'cancel_reason' => $booking->startsAt()->isPast()
                        ? 'Quá giờ chơi mà chủ sân chưa xác nhận.'
                        : 'Chủ sân không phản hồi trong '.config('booking.pending_expire_hours', 24).' giờ nên đơn tự huỷ.',
                ]);
                if ($booking->customer?->email) {
                    SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
                }
                $waitlistService->handleSlotFreed(
                    $booking->sports_field_id,
                    $booking->time_slot_id,
                    $booking->booking_date->toDateString()
                );
                $count['rejected']++;
            } elseif ($booking->status === Booking::STATUS_CONFIRMED && $booking->endsAt()->isPast()) {
                $attributes = ['status' => Booking::STATUS_COMPLETED];
                if ($booking->checked_in_at === null && $booking->no_show_at === null) {
                    $attributes['no_show_at'] = now();
                    if ($booking->customer) {
                        TrustScoreService::penalizeNoShow($booking->customer, $booking);
                    }
                }
                $booking->update($attributes);
                $count['completed']++;
            }
        });

    $this->info("Từ chối quá hạn: {$count['rejected']}, hoàn thành: {$count['completed']}");
})->purpose('Tự động xử lý đơn đặt sân quá hạn / đã kết thúc');

Schedule::command('bookings:sync')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('waitlist:expire', function (WaitlistService $waitlist) {
    $count = $waitlist->expireOffers();
    $this->info("Đã quét và chuyển slot cho {$count} lời mời waitlist quá hạn.");
})->purpose('Quét và chuyển lời mời waitlist quá hạn cho người tiếp theo');

Schedule::command('waitlist:expire')->everyMinute()->withoutOverlapping();

Artisan::command('matches:close-past', function () {
    $count = MatchRequest::whereIn('status', [MatchRequest::STATUS_OPEN, MatchRequest::STATUS_FULL])
        ->where(fn ($q) => $q->where('play_date', '<', today())
            ->orWhere(fn ($q) => $q->whereDate('play_date', today())->where('end_time', '<', now()->format('H:i:s'))))
        ->update(['status' => MatchRequest::STATUS_COMPLETED]);
    $this->info("Đã đóng {$count} trận đấu đã qua ngày thi đấu.");
})->purpose('Đóng các trận đấu tìm đối đã qua ngày');

Schedule::command('matches:close-past')->hourly()->withoutOverlapping();
