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
        $customer1 = User::where('email', 'customer@sportfield.com')->first();
        $customer2 = User::where('email', 'customer2@sportfield.com')->first();
        $fields = SportsField::with('timeSlots')->get();

        if ($fields->isEmpty() || !$customer1) {
            return;
        }

        $field1 = $fields[0];
        $slot1 = $field1->timeSlots->first();
        $slot2 = $field1->timeSlots->skip(1)->first();

        // 1. Đơn hoàn thành + Đánh giá
        $b1 = Booking::create([
            'user_id' => $customer1->id,
            'sports_field_id' => $field1->id,
            'time_slot_id' => $slot1->id,
            'booking_date' => Carbon::yesterday()->toDateString(),
            'status' => 'completed',
            'total_price' => $field1->price_per_hour * 1.5,
            'notes' => 'Cần mượn thêm 2 quả bóng.',
        ]);

        Review::create([
            'user_id' => $customer1->id,
            'sports_field_id' => $field1->id,
            'booking_id' => $b1->id,
            'rating' => 5,
            'comment' => 'Sân chất lượng rất tốt, mặt cỏ êm, đèn sáng rõ. Nhân viên nhiệt tình!',
            'is_visible' => true,
        ]);

        // 2. Đơn đã xác nhận (Hôm nay)
        Booking::create([
            'user_id' => $customer1->id,
            'sports_field_id' => $field1->id,
            'time_slot_id' => $slot2->id,
            'booking_date' => Carbon::today()->toDateString(),
            'status' => 'confirmed',
            'total_price' => $field1->price_per_hour * 1.5,
            'notes' => 'Thanh toán tiền mặt tại sân.',
        ]);

        // 3. Đơn chờ xác nhận (Ngày mai)
        if ($customer2 && isset($fields[1])) {
            $field2 = $fields[1];
            $slotField2 = $field2->timeSlots->first();

            Booking::create([
                'user_id' => $customer2->id,
                'sports_field_id' => $field2->id,
                'time_slot_id' => $slotField2->id,
                'booking_date' => Carbon::tomorrow()->toDateString(),
                'status' => 'pending',
                'total_price' => $field2->price_per_hour * 1.5,
                'notes' => 'Đặt sân đá giao hữu công ty.',
            ]);
        }
    }
}
