<?php

use App\Services\BookingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:sync', function (BookingService $bookings) {
    $result = $bookings->syncStatuses();
    $this->info("Từ chối quá hạn: {$result['rejected']}, hoàn thành: {$result['completed']}");
})->purpose('Tự động xử lý đơn đặt sân quá hạn / đã kết thúc');

Schedule::command('bookings:sync')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('waitlist:expire', function (\App\Services\WaitlistService $waitlist) {
    $count = $waitlist->expireOffers();
    $this->info("Đã quét và chuyển slot cho {$count} lời mời waitlist quá hạn.");
})->purpose('Quét và chuyển lời mời waitlist quá hạn cho người tiếp theo');

Schedule::command('waitlist:expire')->everyMinute()->withoutOverlapping();

Artisan::command('matches:close-past', function () {
    // Đóng phòng đã qua ngày, hoặc hôm nay nhưng đã qua giờ kết thúc.
    $count = \App\Models\MatchRequest::whereIn('status', ['open', 'full'])
        ->where(fn ($q) => $q->where('play_date', '<', today())
            ->orWhere(fn ($q) => $q->whereDate('play_date', today())->where('end_time', '<', now()->format('H:i:s'))))
        ->update(['status' => 'completed']);
    $this->info("Đã đóng {$count} trận đấu đã qua ngày thi đấu.");
})->purpose('Đóng các trận đấu tìm đối đã qua ngày');

Schedule::command('matches:close-past')->hourly()->withoutOverlapping();
