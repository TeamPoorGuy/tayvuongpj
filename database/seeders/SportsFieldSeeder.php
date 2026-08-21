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

        $fieldTypeFootball5 = FieldType::where('name', 'Sân 5 người')->first();
        $fieldTypeFootball7 = FieldType::where('name', 'Sân 7 người')->first();
        $fieldTypeBadminton = FieldType::where('name', 'Sân đơn / đôi tiêu chuẩn')->first();
        $fieldTypeTennis = FieldType::where('name', 'Sân cứng ngoài trời')->first();
        $fieldTypeBasketball = FieldType::where('name', 'Sân bóng rổ 5x5 toàn sân')->first();

        $fields = [
            [
                'owner_id' => $owner1->id,
                'type_id' => $fieldTypeFootball5->id,
                'name' => 'Sân Bóng Đá Tây Vương 1 (Sân 5)',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân cỏ nhân tạo chất lượng cao, hệ thống chiếu sáng LED hiện đại, có khán đài và căng tin.',
                'price' => 300000,
                'images' => [
                    'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=800&q=80',
                    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80',
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $fieldTypeFootball7->id,
                'name' => 'Sân Bóng Đá Tây Vương 2 (Sân 7)',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân 7 tiêu chuẩn thi đấu, mặt cỏ nhập khẩu đạt chuẩn FIFA Quality.',
                'price' => 550000,
                'images' => [
                    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $fieldTypeBadminton->id,
                'name' => 'Sân Cầu Lông Tây Vương A1',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Thảm Yonex chống trượt, trần cao 9m thoáng mát, có điều hòa.',
                'price' => 120000,
                'images' => [
                    'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner1->id,
                'type_id' => $fieldTypeTennis->id,
                'name' => 'Sân Tennis Tây Vương Center',
                'address' => '125 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'description' => 'Sân cứng tiêu chuẩn US Open, hệ thống đèn 1000W sáng rõ ban đêm.',
                'price' => 250000,
                'images' => [
                    'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?auto=format&fit=crop&w=800&q=80'
                ]
            ],
            [
                'owner_id' => $owner2->id,
                'type_id' => $fieldTypeBasketball->id,
                'name' => 'Sân Bóng Rổ Sao Mai Arena',
                'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'description' => 'Sân bóng rổ chuyên nghiệp trong nhà, bảng rổ kính cường lực.',
                'price' => 200000,
                'images' => [
                    'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=800&q=80'
                ]
            ],
        ];

        // Khung giờ cố định từ 06:00 đến 22:00
        $slots = [
            ['06:00', '07:30'],
            ['07:30', '09:00'],
            ['09:00', '10:30'],
            ['14:00', '15:30'],
            ['15:30', '17:00'],
            ['17:00', '18:30'],
            ['18:30', '20:00'],
            ['20:00', '21:30'],
        ];

        foreach ($fields as $fData) {
            $field = SportsField::create([
                'field_owner_id' => $fData['owner_id'],
                'field_type_id' => $fData['type_id'],
                'name' => $fData['name'],
                'slug' => Str::slug($fData['name']),
                'address' => $fData['address'],
                'description' => $fData['description'],
                'price_per_hour' => $fData['price'],
                'status' => 'approved',
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

            foreach ($slots as $slot) {
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
