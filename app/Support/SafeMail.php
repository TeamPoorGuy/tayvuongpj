<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class SafeMail
{
    /** Đưa mail vào queue; lỗi gửi mail không được làm hỏng nghiệp vụ chính. */
    public static function queue(?string $to, Mailable $mailable): void
    {
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->queue($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
