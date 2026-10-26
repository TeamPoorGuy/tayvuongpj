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

// Mỗi phút: lượt giữ chỗ chưa thanh toán phải nhả đúng hạn (mặc định 15 phút), không được kẹt thêm một chu kỳ.
Schedule::command('bookings:sync')->everyMinute()->withoutOverlapping();

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

Artisan::command('statements:generate {period?}', function (\App\Services\StatementService $statementService) {
    $period = $this->argument('period') ?? now()->subMonth()->format('Y-m');
    $this->info("Đang lập bảng đối soát & chi trả cho kỳ: {$period}...");
    $statements = $statementService->generate($period);
    $this->info("Đã sinh/cập nhật bảng đối soát cho ".count($statements)." chủ sân.");
})->purpose('Lập bảng đối soát & chi trả hàng tháng cho các chủ sân');

Schedule::command('statements:generate')->monthlyOn(1, '01:00')->withoutOverlapping();

Artisan::command('owners:purge-documents', function (\App\Services\OwnerApplicationService $service) {
    $count = $service->purgeRejectedDocuments();
    $this->info("Đã xoá {$count} giấy tờ của hồ sơ chủ sân bị từ chối quá hạn lưu.");
})->purpose('Xoá giấy tờ của hồ sơ chủ sân bị từ chối quá 30 ngày');

Schedule::command('owners:purge-documents')->dailyAt('02:00')->withoutOverlapping();

Artisan::command('wallet:release-earnings', function (\App\Services\EarningService $earnings) {
    $count = $earnings->releaseDue();
    $this->info("Đã cộng tiền vào ví chủ sân cho {$count} đơn.");
})->purpose('Cộng tiền thu hộ vào ví chủ sân cho các đơn đã quá hạn kháng cáo');
Schedule::command('wallet:release-earnings')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('bookings:remind', function (BookingService $bookings) {
    $count = $bookings->sendReminders();
    $this->info("Đã nhắc {$count} khách sắp đến giờ chơi.");
})->purpose('Gửi mail nhắc khách 2 giờ trước giờ chơi kèm mã check-in');
Schedule::command('bookings:remind')->everyFifteenMinutes()->withoutOverlapping();
