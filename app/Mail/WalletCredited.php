<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo khách có tiền vừa vào ví SportHub (hoàn tiền, chuyển thiếu, chuyển muộn...). */
class WalletCredited extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public float $amount, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ví SportHub vừa nhận '.number_format($this->amount, 0, ',', '.').'đ');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.wallet-credited');
    }
}
