<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailVi extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Xác thực địa chỉ email SportHub')
            ->greeting("Xin chào {$notifiable->name},")
            ->line('Cảm ơn bạn đã đăng ký SportHub. Vui lòng bấm nút bên dưới để xác thực email.')
            ->action('Xác thực email', $this->verificationUrl($notifiable))
            ->line('Liên kết có hiệu lực trong '.config('auth.verification.expire', 60).' phút.')
            ->line('Nếu bạn không tạo tài khoản, hãy bỏ qua email này.')
            ->salutation('Trân trọng, '.config('app.name'));
    }
}
