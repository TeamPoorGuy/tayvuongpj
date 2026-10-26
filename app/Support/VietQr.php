<?php

namespace App\Support;

class VietQr
{
    /** Ảnh VietQR chuyển khoản tới một tài khoản kèm số tiền và nội dung. */
    public static function url(string $bin, string $account, ?string $holder, float $amount, string $info): string
    {
        return "https://img.vietqr.io/image/{$bin}-{$account}-compact2.png?".http_build_query(array_filter([
            'amount' => (int) $amount,
            'addInfo' => $info,
            'accountName' => $holder,
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
