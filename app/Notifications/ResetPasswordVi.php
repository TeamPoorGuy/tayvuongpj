<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordVi extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = config('frontend.url').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu SportHub')
            ->greeting("Xin chào {$notifiable->name},")
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
            ->action('Đặt lại mật khẩu', $url)
            ->line('Liên kết có hiệu lực trong '.config('auth.passwords.users.expire', 60).' phút.')
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này; mật khẩu sẽ không thay đổi.')
            ->salutation('Trân trọng, '.config('app.name'));
    }
}
