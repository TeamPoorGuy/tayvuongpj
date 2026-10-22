<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintHandled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Complaint $complaint, public bool $asRespondent = false) {}

    public function envelope(): Envelope
    {
        $statusLabel = match ($this->complaint->status) {
            'resolved' => 'đã được giải quyết',
            'rejected' => 'đã được phản hồi (từ chối)',
            default => 'đã được cập nhật',
        };

        return new Envelope(subject: "Khiếu nại #{$this->complaint->id} {$statusLabel} – SportHub");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.complaint-handled');
    }
}
