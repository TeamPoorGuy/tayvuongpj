<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SportsField;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // Chủ sân cũng có role=customer nên phải loại ra, tránh tự đặt sân của chính mình.
        $customers = User::where('role', 'customer')->whereDoesntHave('fieldOwnerProfile')->get();
        $fields = SportsField::with('timeSlots')->get();

        if ($fields->isEmpty() || $customers->isEmpty()) {
            return;
        }

        $reviewsComments = [
            5 => [
                'Sân rất đẹp, cỏ nhân tạo êm ái, đèn sáng rõ ban đêm. Chủ sân cực kỳ thân thiện!',
                'Chất lượng sân tuyệt vời, phòng thay đồ và vệ sinh vô cùng sạch sẽ. Sẽ quay lại thường xuyên!',
                'Khung giờ đặt rất chuẩn, sân không bị chồng lịch. Đánh giá 5 sao cho chất lượng dịch vụ.',
                'Sân thi đấu đạt chuẩn, dịch vụ mượn bóng và áo lưới rất chu đáo.',
            ],
            4 => [
                'Sân đẹp và thoáng mát. Tuy nhiên bãi gửi xe hơi đông vào giờ cao điểm.',
                'Mặt sân khá tốt, hệ thống đèn sáng. Giá thuê hợp lý so với mặt bằng chung.',
                'Chất lượng tốt, chỉ là căng tin hơi ít đồ uống.',
            ],
            3 => [
                'Mặt sân bình thường, có vài chỗ cỏ hơi mòn nhưng vẫn đá ổn.',
            ]
        ];

        // Chỉ dùng ngày quá khứ cho đơn đã kết thúc và ngày tương lai cho đơn đang hiệu lực,
        // để bookings:sync không tự đóng/phạt no-show dữ liệu demo.
        $pastDates = [
            Carbon::today()->subDays(4)->toDateString(),
            Carbon::today()->subDays(3)->toDateString(),
            Carbon::today()->subDays(2)->toDateString(),
        ];
        $futureDates = [
            Carbon::tomorrow()->toDateString(),
            Carbon::tomorrow()->addDays(1)->toDateString(),
            Carbon::tomorrow()->addDays(2)->toDateString(),
        ];
        $pastStatuses = ['completed', 'completed', 'cancelled', 'rejected'];
        // Đơn tương lai đã thanh toán qua SportHub (thu hộ) hoặc đã huỷ; không để đơn giữ chỗ treo không có lượt thanh toán.
        $futureStatuses = ['confirmed', 'confirmed', 'cancelled'];
        $ranges = [['17:00', '18:30'], ['18:30', '20:00'], ['20:00', '21:30']];

        $count = 0;
        foreach ($fields as $fieldIdx => $field) {
            if ($field->timeSlots->isEmpty()) continue;

            foreach (array_merge($pastDates, $futureDates) as $dateIdx => $date) {
                $isPast = $dateIdx < count($pastDates);
                [$start, $end] = $ranges[$dateIdx % count($ranges)];
                $customer = $customers[($fieldIdx + $dateIdx) % count($customers)];
                $pool = $isPast ? $pastStatuses : $futureStatuses;
                $status = $pool[($fieldIdx + $dateIdx) % count($pool)];
                $when = Carbon::parse($date)->setTime(8, 0);

                $paid = in_array($status, ['confirmed', 'completed'], true);
                $amount = ceil($field->price_per_hour * 1.5 * 0.3 / 1000) * 1000;
                // Đơn đã thanh toán có lượt thu hộ tương ứng để đối soát/dashboard khớp số.
                $payment = $paid ? Payment::create([
                    'user_id' => $customer->id,
                    'code' => 'SH'.strtoupper(Str::random(6)),
                    'option' => 'deposit',
                    'total' => $field->price_per_hour * 1.5,
                    'amount' => $amount,
                    'status' => 'paid',
                    'due_at' => $when->copy()->subDay(),
                    'paid_at' => $when->copy()->subDay(),
                    'source' => 'admin',
                ]) : null;

                $booking = Booking::create([
                    'user_id' => $customer->id,
                    'payment_id' => $payment?->id,
                    'sports_field_id' => $field->id,
                    'booking_date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => $status,
                    'total_price' => $field->price_per_hour * 1.5,
                    'payment_status' => $paid ? 'paid' : null,
                    'payment_option' => $paid ? 'deposit' : null,
                    'payment_amount' => $paid ? $amount : null,
                    'notes' => 'Đặt sân đá giải nội bộ công ty.',
                    'confirmed_at' => in_array($status, ['confirmed', 'completed'], true) ? $when->copy()->subDay() : null,
                    'checked_in_at' => $status === 'completed' ? $when : null,
                    'cancelled_at' => in_array($status, ['cancelled', 'rejected'], true) ? $when->copy()->subDay() : null,
                    'cancel_reason' => match ($status) {
                        'cancelled' => 'Khách bận việc đột xuất nên huỷ đơn.',
                        'rejected' => 'Khung giờ này sân đang bảo trì.',
                        default => null,
                    },
                ]);

                // Nếu là đơn completed thì tạo review
                if ($status === 'completed') {
                    $rating = rand(4, 5);
                    $comments = $reviewsComments[$rating];
                    $commentText = $comments[array_rand($comments)];

                    Review::create([
                        'user_id' => $customer->id,
                        'sports_field_id' => $field->id,
                        'booking_id' => $booking->id,
                        'rating' => $rating,
                        'comment' => $commentText,
                        'is_visible' => true,
                    ]);
                }

                $count++;
            }
        }
    }
}
