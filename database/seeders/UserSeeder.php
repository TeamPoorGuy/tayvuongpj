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
        // 1. Chủ sân 1 (Đã duyệt)
        $owner1 = User::create([
            'name' => 'Nguyễn Văn Chủ',
            'email' => 'owner1@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'field_owner',
            'phone' => '0988776655',
            'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
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

        // 2. Chủ sân 2 (Đã duyệt)
        $owner2 = User::create([
            'name' => 'Trần Thị Sân',
            'email' => 'owner2@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'field_owner',
            'phone' => '0911223344',
            'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
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

        // 3. Chủ sân 3 (Mới đăng ký - Chờ duyệt)
        $owner3 = User::create([
            'name' => 'Hoàng Minh Tuấn',
            'email' => 'owner3@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'field_owner',
            'phone' => '0977889900',
            'address' => '88 Đường Cầu Giấy, Hà Nội',
            'is_active' => true,
        ]);

        FieldOwnerProfile::create([
            'user_id' => $owner3->id,
            'business_name' => 'Sân Cầu Lông & Pickleball Cầu Giấy',
            'business_address' => '88 Đường Cầu Giấy, Hà Nội',
            'business_phone' => '0977889900',
            'business_license' => 'GPKD-999888',
            'description' => 'Sân Pickleball và Cầu lông mới khai trương, thảm cao cấp 100%.',
            'verification_status' => 'pending',
        ]);

        // Các Khách hàng
        $customers = [
            ['name' => 'Lê Văn Khách', 'email' => 'customer@sportfield.com', 'phone' => '0933445566', 'address' => '789 Đường Cầu Giấy, Hà Nội'],
            ['name' => 'Phạm Hoàng Nam', 'email' => 'customer2@sportfield.com', 'phone' => '0944556677', 'address' => '12 Đường Trần Phú, Hà Nội'],
            ['name' => 'Vũ Thị Hương', 'email' => 'customer3@sportfield.com', 'phone' => '0966778899', 'address' => '55 Đường Kim Mã, Ba Đình, Hà Nội'],
            ['name' => 'Đặng Anh Khoa', 'email' => 'customer4@sportfield.com', 'phone' => '0922334455', 'address' => '102 Đường Hoàng Quốc Việt, Hà Nội'],
            ['name' => 'Ngô Bảo Long', 'email' => 'customer5@sportfield.com', 'phone' => '0912345678', 'address' => '204 Đường Nguyễn Chí Thanh, Hà Nội'],
        ];

        foreach ($customers as $c) {
            User::create([
                'name' => $c['name'],
                'email' => $c['email'],
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => $c['phone'],
                'address' => $c['address'],
                'is_active' => true,
            ]);
        }
    }
}
