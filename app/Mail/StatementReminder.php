<?php

namespace App\Mail;

use App\Models\OwnerStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StatementReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Báo chủ sân về kỳ chi trả: 'issued' = đã lập bảng, 'paid_out' = SportHub đã chuyển tiền. */
    public function __construct(public OwnerStatement $statement, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'paid_out'
            ? "SportHub đã chi trả tiền sân tháng {$this->statement->period}"
            : "Bảng đối soát & chi trả tháng {$this->statement->period}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.statement-reminder');
    }
}
