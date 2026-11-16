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
        \Illuminate\Database\Eloquent\Model::unguard();

        // Chuẩn bị file ảnh mẫu thật bằng GD trong private storage
        $createSampleImage = function (string $text1, string $text2, string $filename) {
            $im = imagecreatetruecolor(800, 500);
            $bg = imagecolorallocate($im, 243, 246, 244);
            $border = imagecolorallocate($im, 31, 135, 94);
            $textColor = imagecolorallocate($im, 20, 60, 40);
            $subColor = imagecolorallocate($im, 100, 120, 110);
            imagefilledrectangle($im, 0, 0, 800, 500, $bg);
            imagesetthickness($im, 4);
            imagerectangle($im, 10, 10, 790, 490, $border);
            imagestring($im, 5, 220, 220, $text1, $textColor);
            imagestring($im, 4, 240, 260, $text2, $subColor);
            ob_start();
            imagejpeg($im, null, 90);
            $data = ob_get_clean();
            imagedestroy($im);
            Storage::disk('local')->put('licenses/' . $filename, $data);
        };

        $createSampleImage('CAN CUOC CONG DAN - MAT TRUOC', 'XAC THUC SPORTHUB DEMO', 'sample_cccd_front.jpg');
        $createSampleImage('CAN CUOC CONG DAN - MAT SAU', 'XAC THUC SPORTHUB DEMO', 'sample_cccd_back.jpg');
        $createSampleImage('GIAY PHEP DANG KY KINH DOANH', 'XAC THUC SPORTHUB DEMO', 'sample_gpkd.jpg');

        // ==========================================
        // 1. CHỦ SÂN (FIELD OWNERS)
        // ==========================================

        // 1.1 Chủ sân 1: Đã duyệt (Approved) - Hoạt động bình thường
        $owner1 = User::firstOrCreate(
            ['email' => 'owner1@sportfield.com'],
            [
                'name' => 'Nguyễn Văn Chủ',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '0988776655',
                'address' => '123 Đường Lê Văn Lương, Thanh Xuân, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ]
        );

        $p1 = FieldOwnerProfile::updateOrCreate(
            ['user_id' => $owner1->id],
            [
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
            ]
        );

        $p1->documents()->delete();
        $p1->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.jpg', 'original_name' => 'cccd_mat_truoc_nguyen_van_chu.jpg'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.jpg', 'original_name' => 'cccd_mat_sau_nguyen_van_chu.jpg'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.jpg', 'original_name' => 'gpkd_tay_vuong.jpg'],
        ]);

        // 1.2 Chủ sân 2: Đã duyệt (Approved) - Hoạt động bình thường
        $owner2 = User::firstOrCreate(
            ['email' => 'owner2@sportfield.com'],
            [
                'name' => 'Trần Thị Sân',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '0911223344',
                'address' => '456 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ]
        );

        $p2 = FieldOwnerProfile::updateOrCreate(
            ['user_id' => $owner2->id],
            [
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
            ]
        );

        $p2->documents()->delete();
        $p2->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.jpg', 'original_name' => 'cccd_mat_truoc_tran_thi_san.jpg'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.jpg', 'original_name' => 'cccd_mat_sau_tran_thi_san.jpg'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.jpg', 'original_name' => 'gpkd_sao_mai.jpg'],
        ]);

        // 1.3 Chủ sân 3: Mới đăng ký - Chờ Admin duyệt (Pending)
        $owner3 = User::firstOrCreate(
            ['email' => 'owner3@sportfield.com'],
            [
                'name' => 'Hoàng Minh Tuấn',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '0977889900',
                'address' => '88 Đường Cầu Giấy, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ]
        );

        $p3 = FieldOwnerProfile::updateOrCreate(
            ['user_id' => $owner3->id],
            [
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
            ]
        );

        $p3->documents()->delete();
        $p3->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.jpg', 'original_name' => 'cccd_mat_truoc_hoang_minh_tuan.jpg'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.jpg', 'original_name' => 'cccd_mat_sau_hoang_minh_tuan.jpg'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.jpg', 'original_name' => 'gpkd_pickleball_cau_giay.jpg'],
        ]);

        // 1.4 Chủ sân 4: Bị từ chối duyệt (Rejected) - Có lý do từ chối để test nộp lại hồ sơ
        $owner4 = User::firstOrCreate(
            ['email' => 'owner4@sportfield.com'],
            [
                'name' => 'Lê Văn Trọng',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '0966554433',
                'address' => '25 Đường Quang Trung, Hà Đông, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ]
        );

        $p4 = FieldOwnerProfile::updateOrCreate(
            ['user_id' => $owner4->id],
            [
                'owner_name' => 'Lê Văn Trọng',
                'owner_id_number' => '001099003344',
                'business_name' => 'Tổ Hợp Thể Thao Hà Đông',
                'business_address' => '25 Đường Quang Trung, Hà Đông, Hà Nội',
                'business_phone' => '0966554433',
                'business_license' => 'GPKD-554433',
                'description' => 'Hệ thống sân cỏ nhân tạo và sân cầu lông tiêu chuẩn tại Hà Đông.',
                'bank_name' => 'BIDV',
                'bank_account_number' => '124100008899',
                'bank_account_holder' => 'LE VAN TRONG',
                'terms_version' => '1.0',
                'terms_accepted_at' => now()->subDays(5),
                'verification_status' => 'rejected',
                'rejection_reason' => 'Hình ảnh giấy phép kinh doanh bị mờ, thông tin số CCCD không trùng khớp với bản chụp mặt trước.',
            ]
        );

        $p4->documents()->delete();
        $p4->documents()->createMany([
            ['type' => 'id_front', 'path' => 'licenses/sample_cccd_front.jpg', 'original_name' => 'cccd_mat_truoc_le_van_trong.jpg'],
            ['type' => 'id_back', 'path' => 'licenses/sample_cccd_back.jpg', 'original_name' => 'cccd_mat_sau_le_van_trong.jpg'],
            ['type' => 'business_license', 'path' => 'licenses/sample_gpkd.jpg', 'original_name' => 'gpkd_ha_dong.jpg'],
        ]);

        // 1.5 Chủ sân 5: Đã duyệt nhưng tài khoản bị Admin KHÓA (is_active = false)
        $owner5 = User::firstOrCreate(
            ['email' => 'owner5@sportfield.com'],
            [
                'name' => 'Phạm Quốc Bảo',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '0933221100',
                'address' => '15 Đường Phạm Hùng, Nam Từ Liêm, Hà Nội',
                'is_active' => false, // Bị khóa
                'trust_score' => 80,
            ]
        );

        $p5 = FieldOwnerProfile::updateOrCreate(
            ['user_id' => $owner5->id],
            [
                'owner_name' => 'Phạm Quốc Bảo',
                'owner_id_number' => '001099008877',
                'business_name' => 'Sân Bóng Động Lực Mỹ Đình',
                'business_address' => '15 Đường Phạm Hùng, Nam Từ Liêm, Hà Nội',
                'business_phone' => '0933221100',
                'business_license' => 'GPKD-887766',
                'description' => 'Sân bóng chất lượng cao khu vực Mỹ Đình.',
                'bank_name' => 'Agribank',
                'bank_account_number' => '1400205889900',
                'bank_account_holder' => 'PHAM QUOC BAO',
                'terms_version' => '1.0',
                'terms_accepted_at' => now()->subMonths(2),
                'verification_status' => 'approved',
                'verified_at' => now()->subMonths(2),
            ]
        );

        // ==========================================
        // 2. KHÁCH HÀNG (CUSTOMERS) ĐỦ CÁC TRẠNG THÁI
        // ==========================================
        $customers = [
            [
                'name' => 'Lê Văn Khách',
                'email' => 'customer@sportfield.com',
                'phone' => '0933445566',
                'address' => '789 Đường Cầu Giấy, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ],
            [
                'name' => 'Phạm Hoàng Nam',
                'email' => 'customer2@sportfield.com',
                'phone' => '0944556677',
                'address' => '12 Đường Trần Phú, Hà Đông, Hà Nội',
                'is_active' => true,
                'trust_score' => 110, // Điểm uy tín cao
            ],
            [
                'name' => 'Vũ Thị Hương',
                'email' => 'customer3@sportfield.com',
                'phone' => '0966778899',
                'address' => '55 Đường Kim Mã, Ba Đình, Hà Nội',
                'is_active' => true,
                'trust_score' => 95,
            ],
            [
                'name' => 'Đặng Anh Khoa',
                'email' => 'customer4@sportfield.com',
                'phone' => '0922334455',
                'address' => '102 Đường Hoàng Quốc Việt, Cầu Giấy, Hà Nội',
                'is_active' => true,
                'trust_score' => 65, // Bị trừ điểm do no-show và huỷ sát giờ
            ],
            [
                'name' => 'Ngô Bảo Long',
                'email' => 'customer5@sportfield.com',
                'phone' => '0912345678',
                'address' => '204 Đường Nguyễn Chí Thanh, Đống Đa, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ],
            [
                'name' => 'Đỗ Mỹ Linh (Tài khoản bị khóa)',
                'email' => 'customer6@sportfield.com',
                'phone' => '0987654321',
                'address' => '32 Đường Láng Hạ, Đống Đa, Hà Nội',
                'is_active' => false, // Bị Admin khóa để test thông báo chặn đăng nhập
                'trust_score' => 50,
            ],
            [
                'name' => 'Bùi Tuấn Kiệt',
                'email' => 'customer7@sportfield.com',
                'phone' => '0971234567',
                'address' => '18 Đường Lạc Long Quân, Tây Hồ, Hà Nội',
                'is_active' => true,
                'trust_score' => 105,
            ],
            [
                'name' => 'Trần Đức Thắng',
                'email' => 'customer8@sportfield.com',
                'phone' => '0962345678',
                'address' => '45 Đường Minh Khai, Hai Bà Trưng, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ],
            [
                'name' => 'Nguyễn Hoàng Yến',
                'email' => 'customer9@sportfield.com',
                'phone' => '0953456789',
                'address' => '72 Đường Giải Phóng, Hoàng Mai, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ],
            [
                'name' => 'Phan Thanh Tùng',
                'email' => 'customer10@sportfield.com',
                'phone' => '0945678901',
                'address' => '99 Đường Xuân Thủy, Cầu Giấy, Hà Nội',
                'is_active' => true,
                'trust_score' => 100,
            ],
        ];

        foreach ($customers as $c) {
            User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'role' => 'customer',
                    'phone' => $c['phone'],
                    'address' => $c['address'],
                    'is_active' => $c['is_active'],
                    'trust_score' => $c['trust_score'],
                ]
            );
        }
    }
}
