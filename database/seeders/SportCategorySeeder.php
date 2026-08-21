<?php

namespace Database\Seeders;

use App\Models\FieldType;
use App\Models\SportCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Bóng đá',
                'slug' => 'bong-da',
                'icon' => '⚽',
                'description' => 'Sân bóng đá cỏ nhân tạo và cỏ tự nhiên các loại sân 5, 7, 11 người.',
                'types' => ['Sân 5 người', 'Sân 7 người', 'Sân 11 người']
            ],
            [
                'name' => 'Cầu lông',
                'slug' => 'cau-long',
                'icon' => '🏸',
                'description' => 'Sân cầu lông trong nhà đạt tiêu chuẩn với thảm thảm chống trơn trượt.',
                'types' => ['Sân đơn / đôi tiêu chuẩn', 'Sân VIP thảm cao cấp']
            ],
            [
                'name' => 'Tennis',
                'slug' => 'tennis',
                'icon' => '🎾',
                'description' => 'Sân quần vợt mặt sân đất nịch, sân cứng ngoài trời và trong nhà.',
                'types' => ['Sân cứng ngoài trời', 'Sân đất nịch', 'Sân trong nhà có mái che']
            ],
            [
                'name' => 'Bóng chuyền',
                'slug' => 'bong-chuyen',
                'icon' => '🏐',
                'description' => 'Sân bóng chuyền trong nhà và bãi biển.',
                'types' => ['Sân bóng chuyền tiêu chuẩn', 'Sân bóng chuyền bãi biển']
            ],
            [
                'name' => 'Bóng rổ',
                'slug' => 'bong-ro',
                'icon' => '🏀',
                'description' => 'Sân bóng rổ indoor và outdoor có trang bị trụ rổ tiêu chuẩn NBA.',
                'types' => ['Sân bóng rổ 3x3', 'Sân bóng rổ 5x5 toàn sân']
            ],
        ];

        foreach ($categories as $catData) {
            $cat = SportCategory::create([
                'name' => $catData['name'],
                'slug' => $catData['slug'],
                'icon' => $catData['icon'],
                'description' => $catData['description'],
                'is_active' => true,
            ]);

            foreach ($catData['types'] as $typeName) {
                FieldType::create([
                    'sport_category_id' => $cat->id,
                    'name' => $typeName,
                    'slug' => Str::slug($typeName),
                    'description' => 'Loại sân ' . $typeName . ' thuộc danh mục ' . $cat->name,
                    'is_active' => true,
                ]);
            }
        }
    }
}
