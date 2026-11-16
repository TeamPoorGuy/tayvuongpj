<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\MatchParticipant;
use App\Models\MatchRequest;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TrustScoreLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SmartMatchdaySeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        $customers = User::where('role', 'customer')->whereDoesntHave('fieldOwnerProfile')->get();
        if ($customers->count() < 4) {
            return;
        }

        $customer1 = $customers->firstWhere('email', 'customer@sportfield.com') ?? $customers[0];
        $customer2 = $customers->firstWhere('email', 'customer2@sportfield.com') ?? $customers[1];
        $customer3 = $customers->firstWhere('email', 'customer3@sportfield.com') ?? $customers[2];
        $customer4 = $customers->firstWhere('email', 'customer4@sportfield.com') ?? $customers[3];
        $customer7 = $customers->firstWhere('email', 'customer7@sportfield.com') ?? $customers[0];
        $customer8 = $customers->firstWhere('email', 'customer8@sportfield.com') ?? $customers[1];

        $footballCat = SportCategory::where('name', 'Bóng đá')->first() ?? SportCategory::first();
        $badmintonCat = SportCategory::where('name', 'Cầu lông')->first() ?? SportCategory::first();
        $basketballCat = SportCategory::where('name', 'Bóng rổ')->first() ?? SportCategory::first();

        $field1 = SportsField::where('name', 'like', '%Tây Vương 1%')->first() ?? SportsField::first();
        $fieldBadminton = SportsField::where('name', 'like', '%Cầu Lông Tây Vương A1%')->first() ?? $field1;
        $fieldBasketball = SportsField::where('name', 'like', '%Sao Mai Arena%')->first() ?? $field1;

        // ==========================================
        // 1. GHÉP ĐỘI / TÌM ĐỐI THỦ (MATCH REQUESTS)
        // ==========================================

        // Trận 1: Đang mở tìm đối thủ (Open) - Cần thêm 3 người, đã có 2 người tham gia
        $m1 = MatchRequest::create([
            'user_id' => $customer2->id, // Host: Phạm Hoàng Nam
            'sport_category_id' => $footballCat->id,
            'sports_field_id' => $field1->id,
            'title' => 'Giao lưu bóng đá 7 người - Tìm đối cứng tối Thứ 7',
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:30',
            'skill_level' => 'intermediate',
            'players_needed' => 3,
            'joined_count' => 2, // host (1) + customer3 (1)
            'description' => 'Đội bóng văn phòng công nghệ, đá vui vẻ fair-play, chia tiền sân nước ngọt đều. Cần tìm thêm 2-3 bạn đá tiền vệ/hậu vệ.',
            'contact_phone' => $customer2->phone,
            'status' => 'open',
        ]);

        MatchParticipant::create([
            'match_request_id' => $m1->id,
            'user_id' => $customer3->id,
            'status' => 'accepted',
            'message' => 'Mình đá vị trí hậu vệ cánh, xin 1 slot nhé!',
        ]);

        MatchParticipant::create([
            'match_request_id' => $m1->id,
            'user_id' => $customer7->id,
            'status' => 'pending', // Chờ host duyệt
            'message' => 'Mình đăng ký tham gia giao lưu cùng anh em.',
        ]);

        // Trận 2: Đã ghép đủ người (Matched / Full)
        $m2 = MatchRequest::create([
            'user_id' => $customer1->id, // Host: Lê Văn Khách
            'sport_category_id' => $badmintonCat->id,
            'sports_field_id' => $fieldBadminton->id,
            'title' => 'Cầu lông đôi nam nữ trình độ trung bình khá',
            'play_date' => Carbon::tomorrow()->addDays(1)->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:30',
            'skill_level' => 'intermediate',
            'players_needed' => 2,
            'joined_count' => 3, // host (1) + 2 người đã duyệt = đủ
            'description' => 'Đánh giao lưu cọ xát nâng cao thể lực, dùng cầu Hải Yến đỏ.',
            'contact_phone' => $customer1->phone,
            'status' => 'matched',
        ]);

        MatchParticipant::create([
            'match_request_id' => $m2->id,
            'user_id' => $customer4->id,
            'status' => 'accepted',
            'message' => 'Mình tham gia đánh đôi nam.',
        ]);

        MatchParticipant::create([
            'match_request_id' => $m2->id,
            'user_id' => $customer8->id,
            'status' => 'accepted',
            'message' => 'Mình đăng ký 1 slot nữ.',
        ]);

        // Trận 3: Đã bị huỷ (Cancelled) - Để test Admin / Khách xem lý do huỷ
        MatchRequest::create([
            'user_id' => $customer1->id,
            'sport_category_id' => $basketballCat->id,
            'sports_field_id' => $fieldBasketball->id,
            'title' => 'Giao lưu bóng rổ 3x3 cuối tuần',
            'play_date' => Carbon::yesterday()->toDateString(),
            'start_time' => '07:30',
            'end_time' => '09:00',
            'skill_level' => 'any',
            'players_needed' => 4,
            'joined_count' => 1,
            'description' => 'Giao hữu ném bóng rổ buổi sáng.',
            'contact_phone' => $customer1->phone,
            'status' => 'cancelled',
        ]);

        // ==========================================
        // 2. HÀNG ĐỢI THÔNG MINH (WAITLIST)
        // ==========================================
        $slot1 = $field1->timeSlots()->first();
        if ($slot1) {
            // Khách 3 đang chờ slot (Waiting)
            BookingWaitlist::create([
                'user_id' => $customer3->id,
                'sports_field_id' => $field1->id,
                'time_slot_id' => $slot1->id,
                'booking_date' => Carbon::tomorrow()->toDateString(),
                'status' => 'waiting',
                'priority_score' => 100,
            ]);

            // Khách 2 được gửi lời mời nhận slot trống (Offered)
            BookingWaitlist::create([
                'user_id' => $customer2->id,
                'sports_field_id' => $field1->id,
                'time_slot_id' => $slot1->id,
                'booking_date' => Carbon::tomorrow()->addDays(2)->toDateString(),
                'status' => 'offered',
                'priority_score' => 110,
                'offered_at' => now(),
                'offer_expires_at' => now()->addMinutes(30),
            ]);
        }

        // ==========================================
        // 3. NHẬT KÝ ĐIỂM UY TÍN (TRUST SCORE LOGS)
        // ==========================================
        $recentBooking = Booking::where('user_id', $customer4->id)->first();

        // Log phạt khách 4
        TrustScoreLog::create([
            'user_id' => $customer4->id,
            'change' => -20,
            'score_after' => 80,
            'reason' => 'Phạt no-show: Không đến sân theo lịch đặt sân đã xác nhận.',
            'booking_id' => $recentBooking?->id,
            'created_at' => now()->subDays(3),
        ]);

        TrustScoreLog::create([
            'user_id' => $customer4->id,
            'change' => -15,
            'score_after' => 65,
            'reason' => 'Huỷ đơn đặt sân sát giờ thi đấu (< 2 tiếng trước giờ bóng lăn).',
            'created_at' => now()->subDays(1),
        ]);

        // Log thưởng khách 2
        TrustScoreLog::create([
            'user_id' => $customer2->id,
            'change' => 5,
            'score_after' => 105,
            'reason' => 'Hoàn thành 3 trận đấu liên tiếp đúng hẹn không vi phạm.',
            'created_at' => now()->subDays(4),
        ]);

        TrustScoreLog::create([
            'user_id' => $customer2->id,
            'change' => 5,
            'score_after' => 110,
            'reason' => 'Tổ chức phòng ghép đội thi đấu thành công, nhận đánh giá 5 sao từ các thành viên.',
            'created_at' => now()->subDays(2),
        ]);
    }
}
