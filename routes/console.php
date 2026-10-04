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
