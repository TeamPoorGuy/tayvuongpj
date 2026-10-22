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

    /** Báo chủ sân đã có báo cáo đối soát của kỳ (kind: 'issued'). */
    public function __construct(public OwnerStatement $statement, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Báo cáo đối soát tháng {$this->statement->period}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.statement-reminder');
    }
}
