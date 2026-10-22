<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo cho bên bị khiếu nại biết để phản hồi trước khi admin quyết định. */
class ComplaintFiled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Complaint $complaint) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Bạn có khiếu nại #{$this->complaint->id} cần phản hồi – SportHub");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.complaint-filed');
    }
}
