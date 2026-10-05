<?php

namespace Database\Seeders;

use App\Models\FieldOwnerProfile;
use App\Models\OwnerDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard(); // seeders set role/is_active directly

        // Chuẩn bị file tài liệu mẫu trong private storage
        Storage::disk('local')->put('licenses/sample_cccd_front.pdf', '%PDF-1.4 Sample CCCD Front');
        Storage::disk('local')->put('licenses/sample_cccd_back.pdf', '%PDF-1.4 Sample CCCD Back');
        Storage::disk('local')->put('licenses/sample_gpkd.pdf', '%PDF-1.4 Sample Business License');

        // 1. Chủ sân 1 (Đã duyệt) — role vẫn là customer, quyền chủ sân suy ra từ verification_status.
        $owner1 = User::create([
            'name' => 'Nguyễn Văn Chủ',
            'email_verified_at' => now(),
            'email' => 'owner1@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '0988776655',
            'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
            'is_active' => true,
        ]);

        $p1 = FieldOwnerProfile::create([
            'user_id' => $owner1->id,
            'owner_name' => 'Nguyễn Văn Chủ',
            'owner_id_number' => '001099001234',
            'business_name' => 'CLB Thể Thao Tây Vương',
            'business_address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
            'business_phone' => '0988776655',
            'business_license' => 'GPKD-123456',
            'description' => 'Trung tâm thể thao đa năng gồm bóng đá, cầu lông và tennis hiện đại.',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '1029384756',
            'bank_account_holder' => 'NGUYEN VAN CHU',
            'terms_version' => '1.0',
            'terms_accepted_at' => now()->subMonths(3),
            'verification_status' => 'approved',
            'verified_at' => now()->subMonths(3),
        ]);

        $p1->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.pdf', 'original_name' => 'cccd_mat_truoc_nguyen_van_chu.pdf'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.pdf', 'original_name' => 'cccd_mat_sau_nguyen_van_chu.pdf'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.pdf', 'original_name' => 'gpkd_tay_vuong.pdf'],
        ]);

        // 2. Chủ sân 2 (Đã duyệt)
        $owner2 = User::create([
            'name' => 'Trần Thị Sân',
            'email_verified_at' => now(),
            'email' => 'owner2@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '0911223344',
            'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
            'is_active' => true,
        ]);

        $p2 = FieldOwnerProfile::create([
            'user_id' => $owner2->id,
            'owner_name' => 'Trần Thị Sân',
            'owner_id_number' => '001099005678',
            'business_name' => 'Khu Phức Hợp Thể Thao Sao Mai',
            'business_address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
            'business_phone' => '0911223344',
            'business_license' => 'GPKD-789012',
            'description' => 'Hệ thống sân bóng rổ và bóng chuyền đạt tiêu chuẩn thi đấu.',
            'bank_name' => 'MB Bank',
            'bank_account_number' => '999888777666',
            'bank_account_holder' => 'TRAN THI SAN',
            'terms_version' => '1.0',
            'terms_accepted_at' => now()->subMonths(1),
            'verification_status' => 'approved',
            'verified_at' => now()->subMonths(1),
        ]);

        $p2->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.pdf', 'original_name' => 'cccd_mat_truoc_tran_thi_san.pdf'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.pdf', 'original_name' => 'cccd_mat_sau_tran_thi_san.pdf'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.pdf', 'original_name' => 'gpkd_sao_mai.pdf'],
        ]);

        // 3. Chủ sân 3 (Mới đăng ký - Chờ duyệt)
        $owner3 = User::create([
            'name' => 'Hoàng Minh Tuấn',
            'email_verified_at' => now(),
            'email' => 'owner3@sportfield.com',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '0977889900',
            'address' => '88 Đường Cầu Giấy, Hà Nội',
            'is_active' => true,
        ]);

        $p3 = FieldOwnerProfile::create([
            'user_id' => $owner3->id,
            'owner_name' => 'Hoàng Minh Tuấn',
            'owner_id_number' => '001099009012',
            'business_name' => 'Sân Cầu Lông & Pickleball Cầu Giấy',
            'business_address' => '88 Đường Cầu Giấy, Hà Nội',
            'business_phone' => '0977889900',
            'business_license' => 'GPKD-999888',
            'description' => 'Sân Pickleball và Cầu lông mới khai trương, thảm cao cấp 100%.',
            'bank_name' => 'Techcombank',
            'bank_account_number' => '1903456789012',
            'bank_account_holder' => 'HOANG MINH TUAN',
            'terms_version' => '1.0',
            'terms_accepted_at' => now(),
            'verification_status' => 'pending',
        ]);

        $p3->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.pdf', 'original_name' => 'cccd_mat_truoc_hoang_minh_tuan.pdf'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.pdf', 'original_name' => 'cccd_mat_sau_hoang_minh_tuan.pdf'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.pdf', 'original_name' => 'gpkd_pickleball_cau_giay.pdf'],
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
                'email_verified_at' => now(),
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
