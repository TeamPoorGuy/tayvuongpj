<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use App\Models\SportsField;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
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

        $dates = [
            Carbon::yesterday()->subDays(3)->toDateString(),
            Carbon::yesterday()->subDays(2)->toDateString(),
            Carbon::yesterday()->toDateString(),
            Carbon::today()->toDateString(),
            Carbon::tomorrow()->toDateString(),
            Carbon::tomorrow()->addDays(2)->toDateString(),
        ];

        $statuses = ['completed', 'confirmed', 'pending', 'cancelled', 'rejected'];

        $count = 0;
        foreach ($fields as $fieldIdx => $field) {
            $slots = $field->timeSlots;
            if ($slots->isEmpty()) continue;

            foreach ($dates as $dateIdx => $date) {
                $slot = $slots[$dateIdx % count($slots)];
                $customer = $customers[($fieldIdx + $dateIdx) % count($customers)];
                $status = $statuses[($fieldIdx + $dateIdx) % count($statuses)];

                $booking = Booking::create([
                    'user_id' => $customer->id,
                    'sports_field_id' => $field->id,
                    'time_slot_id' => $slot->id,
                    'booking_date' => $date,
                    'status' => $status,
                    'total_price' => $field->price_per_hour * 1.5,
                    'notes' => 'Đặt sân đá giải nội bộ công ty.',
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
