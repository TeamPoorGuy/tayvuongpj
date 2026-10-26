<?php

namespace App\Mail;

use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo kết quả lệnh rút ví: đã chuyển khoản hoặc bị từ chối (tiền đã trả lại ví). */
class WithdrawalProcessed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Withdrawal $withdrawal) {}

    public function envelope(): Envelope
    {
        $label = $this->withdrawal->status === 'paid' ? 'đã được chuyển' : 'bị từ chối';

        return new Envelope(subject: "Lệnh rút {$this->withdrawal->amount} {$label}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.withdrawal-processed');
    }
}
