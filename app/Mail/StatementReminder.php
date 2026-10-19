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

    /** @param 'issued'|'due_soon'|'overdue' $kind */
    public function __construct(public OwnerStatement $statement, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'issued' => "Bảng đối soát phí nền tảng tháng {$this->statement->period}",
            'due_soon' => "Sắp đến hạn nộp phí đối soát tháng {$this->statement->period}",
            default => "Phí đối soát tháng {$this->statement->period} đã quá hạn",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.statement-reminder');
    }
}
