<?php

namespace Database\Seeders;

use App\Models\FieldOwnerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 2 Chủ sân
        $owner1 = User::create([
            'name' => 'Nguyễn Văn Chủ',
            'email' => 'owner1@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'field_owner',
            'phone' => '0988776655',
            'address' => '123 Đường Lê Văn Lương, Hà Nội',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner1->id,
            'business_name' => 'CLB Thể Thao Tây Vương',
            'business_address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
            'business_phone' => '0988776655',
            'business_license' => 'GPKD-123456',
            'description' => 'Trung tâm thể thao đa năng gồm bóng đá, cầu lông và tennis hiện đại.',
            'verification_status' => 'approved',
        ]);

        $owner2 = User::create([
            'name' => 'Trần Thị Sân',
            'email' => 'owner2@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'field_owner',
            'phone' => '0911223344',
            'address' => '456 Đường Nguyễn Trãi, Hà Nội',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner2->id,
            'business_name' => 'Khu Phức Hợp Thể Thao Sao Mai',
            'business_address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
            'business_phone' => '0911223344',
            'business_license' => 'GPKD-789012',
            'description' => 'Hệ thống sân bóng rổ và bóng chuyền đạt tiêu chuẩn thi đấu.',
            'verification_status' => 'approved',
        ]);

        // 3 Khách hàng
        User::create([
            'name' => 'Lê Văn Khách',
            'email' => 'customer@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '0933445566',
            'address' => '789 Đường Cầu Giấy, Hà Nội',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Phạm Hoàng Nam',
            'email' => 'customer2@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '0944556677',
            'address' => '12 Đường Trần Phú, Hà Nội',
            'is_active' => true,
        ]);
    }
}
