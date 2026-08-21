<?php

namespace Database\Seeders;

use App\Models\FieldImage;
use App\Models\FieldType;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportsFieldSeeder extends Seeder
{
    public function run(): void
    {
        $owner1 = User::where('email', 'owner1@sportfield.com')->first();
        $owner2 = User::where('email', 'owner2@sportfield.com')->first();
        $owner3 = User::where('email', 'owner3@sportfield.com')->first();

        $types = [
            'f5' => FieldType::where('name', 'Sân 5 người')->first(),
            'f7' => FieldType::where('name', 'Sân 7 người')->first(),
            'f11' => FieldType::where('name', 'Sân 11 người')->first(),
            'badminton' => FieldType::where('name', 'Sân đơn / đôi tiêu chuẩn')->first(),
            'tennis' => FieldType::where('name', 'Sân cứng ngoài trời')->first(),
            'basketball' => FieldType::where('name', 'Sân bóng rổ 5x5 toàn sân')->first(),
            'volleyball' => FieldType::where('name', 'Sân bóng chuyền tiêu chuẩn')->first(),
        ];

        $fieldsData = [
            [
                'owner_id' => $owner1->id,
                'type_id' => $types['f5']->id,
                'name' => 'Sân Bóng Đá Tây Vương 1 (Sân 5)',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân cỏ nhân tạo chất lượng cao nhập khẩu từ Hà Lan, hệ thống đèn LED 800W siêu sáng, có căng tin nước giải khát và phòng thay đồ sạch sẽ.',
                'price' => 300000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $types['f7']->id,
                'name' => 'Sân Bóng Đá Tây Vương 2 (Sân 7)',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân 7 tiêu chuẩn thi đấu chuyên nghiệp, mặt cỏ FIFA Quality, thoát nước siêu nhanh kể cả mưa lớn.',
                'price' => 550000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $types['badminton']->id,
                'name' => 'Sân Cầu Lông Tây Vương A1',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Thảm Yonex chống trượt tiêu chuẩn quốc tế, trần nhà cao 10m thoáng mát, trang bị máy điều hòa không khí.',
                'price' => 120000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $types['tennis']->id,
                'name' => 'Sân Tennis Tây Vương Center',
                'address' => '125 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân cứng sơn Plexipave tiêu chuẩn US Open, hệ thống chiếu sáng không chói mắt.',
                'price' => 250000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner2->id,
                'type_id' => $types['basketball']->id,
                'name' => 'Sân Bóng Rổ Sao Mai Arena',
                'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'description' => 'Sân bóng rổ trong nhà sàn gỗ Maple cao cấp, bảng rổ kính cường lực nhảy cực bốc.',
                'price' => 200000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner2->id,
                'type_id' => $types['volleyball']->id,
                'name' => 'Sân Bóng Chuyền Sao Mai Indoor',
                'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'description' => 'Sân bóng chuyền trong nhà trang bị lưới căng tiêu chuẩn thi đấu giải vô địch quốc gia.',
                'price' => 180000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner3->id,
                'type_id' => $types['badminton']->id,
                'name' => 'Sân Cầu Lông Cầu Giấy Pro',
                'address' => '88 Đường Cầu Giấy, Hà Nội',
                'description' => 'Cụm 8 sân cầu lông mới tinh vừa hoàn thiện, có huấn luyện viên hướng dẫn kỹ thuật.',
                'price' => 110000,
                'status' => 'pending',
                'images' => [
                    'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $types['f11']->id,
                'name' => 'Sân Bóng Đá 11 Người Tây Vương Stadium',
                'address' => '130 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân bóng lớn 11 người mặt cỏ tự nhiên xanh mướt, trang bị khán đài 500 chỗ ngồi.',
                'price' => 1200000,
                'status' => 'approved',
                'images' => [
                    'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=800&q=80'
                ]
            ],
        ];

        $slotsConfig = [
            ['06:00', '07:30'],
            ['07:30', '09:00'],
            ['09:00', '10:30'],
            ['14:00', '15:30'],
            ['15:30', '17:00'],
            ['17:00', '18:30'],
            ['18:30', '20:00'],
            ['20:00', '21:30'],
        ];

        foreach ($fieldsData as $fData) {
            $field = SportsField::create([
                'field_owner_id' => $fData['owner_id'],
                'field_type_id' => $fData['type_id'],
                'name' => $fData['name'],
                'slug' => Str::slug($fData['name']),
                'address' => $fData['address'],
                'description' => $fData['description'],
                'price_per_hour' => $fData['price'],
                'status' => $fData['status'],
                'is_active' => true,
            ]);

            foreach ($fData['images'] as $idx => $imgPath) {
                FieldImage::create([
                    'sports_field_id' => $field->id,
                    'image_path' => $imgPath,
                    'is_primary' => $idx === 0,
                    'sort_order' => $idx,
                ]);
            }

            foreach ($slotsConfig as $slot) {
                TimeSlot::create([
                    'sports_field_id' => $field->id,
                    'start_time' => $slot[0],
                    'end_time' => $slot[1],
                    'is_active' => true,
                ]);
            }
        }
    }
}
