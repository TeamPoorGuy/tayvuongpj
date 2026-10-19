<?php

namespace App\Support;

class Banks
{
    /**
     * Danh sách cố định ~20 ngân hàng lớn tại Việt Nam kèm mã BIN chuyển khoản Napas / VietQR.
     *
     * @var array<int, array{bin: string, short_name: string, name: string}>
     */
    public const LIST = [
        ['bin' => '970436', 'short_name' => 'Vietcombank', 'name' => 'Ngân hàng TMCP Ngoại thương Việt Nam'],
        ['bin' => '970422', 'short_name' => 'MBBank', 'name' => 'Ngân hàng TMCP Quân đội'],
        ['bin' => '970407', 'short_name' => 'Techcombank', 'name' => 'Ngân hàng TMCP Kỹ thương Việt Nam'],
        ['bin' => '970415', 'short_name' => 'VietinBank', 'name' => 'Ngân hàng TMCP Công thương Việt Nam'],
        ['bin' => '970418', 'short_name' => 'BIDV', 'name' => 'Ngân hàng TMCP Đầu tư và Phát triển Việt Nam'],
        ['bin' => '970416', 'short_name' => 'ACB', 'name' => 'Ngân hàng TMCP Á Châu'],
        ['bin' => '970432', 'short_name' => 'VPBank', 'name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng'],
        ['bin' => '970423', 'short_name' => 'TPBank', 'name' => 'Ngân hàng TMCP Tiên Phong'],
        ['bin' => '970403', 'short_name' => 'Sacombank', 'name' => 'Ngân hàng TMCP Sài Gòn Thương Tín'],
        ['bin' => '970441', 'short_name' => 'VIB', 'name' => 'Ngân hàng TMCP Quốc tế Việt Nam'],
        ['bin' => '970437', 'short_name' => 'HDBank', 'name' => 'Ngân hàng TMCP Phát triển TP.HCM'],
        ['bin' => '970443', 'short_name' => 'SHB', 'name' => 'Ngân hàng TMCP Sài Gòn - Hà Nội'],
        ['bin' => '970440', 'short_name' => 'SeABank', 'name' => 'Ngân hàng TMCP Đông Nam Á'],
        ['bin' => '970448', 'short_name' => 'OCB', 'name' => 'Ngân hàng TMCP Phương Đông'],
        ['bin' => '970426', 'short_name' => 'MSB', 'name' => 'Ngân hàng TMCP Hàng Hải Việt Nam'],
        ['bin' => '970431', 'short_name' => 'Eximbank', 'name' => 'Ngân hàng TMCP Xuất Nhập Khẩu Việt Nam'],
        ['bin' => '970449', 'short_name' => 'LPBank', 'name' => 'Ngân hàng TMCP Bưu điện Liên Việt'],
        ['bin' => '970428', 'short_name' => 'Nam A Bank', 'name' => 'Ngân hàng TMCP Nam Á'],
        ['bin' => '970409', 'short_name' => 'Bac A Bank', 'name' => 'Ngân hàng TMCP Bắc Á'],
        ['bin' => '970412', 'short_name' => 'PVcomBank', 'name' => 'Ngân hàng TMCP Đại Chúng Việt Nam'],
        ['bin' => '546034', 'short_name' => 'Cake by VPBank', 'name' => 'Ngân hàng số Cake by VPBank'],
        ['bin' => '963388', 'short_name' => 'Timo', 'name' => 'Ngân hàng số Timo by BVBank'],
    ];

    /**
     * @return array<int, array{bin: string, short_name: string, name: string}>
     */
    public static function all(): array
    {
        return self::LIST;
    }

    /**
     * @return list<string>
     */
    public static function bins(): array
    {
        return array_column(self::LIST, 'bin');
    }

    /**
     * @return array{bin: string, short_name: string, name: string}|null
     */
    public static function findByBin(?string $bin): ?array
    {
        if (! $bin) {
            return null;
        }

        foreach (self::LIST as $bank) {
            if ($bank['bin'] === $bin) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Tìm mã BIN dựa trên tên ngân hàng (dùng khi tương thích ngược dữ liệu cũ)
     */
    public static function guessBin(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $normalized = mb_strtolower(trim($name));

        foreach (self::LIST as $bank) {
            if (mb_strtolower($bank['short_name']) === $normalized || mb_strtolower($bank['name']) === $normalized) {
                return $bank['bin'];
            }
            if (str_contains($normalized, mb_strtolower($bank['short_name']))) {
                return $bank['bin'];
            }
        }

        return null;
    }
}
