<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $targetUser,
        public bool $isActive,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->isActive ? 'đã được mở khoá' : 'đã bị tạm khoá';

        return new Envelope(subject: "Tài khoản SportHub của bạn {$status}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.account-status-changed');
    }
}
