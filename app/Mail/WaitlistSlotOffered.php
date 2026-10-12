<?php

namespace App\Mail;

use App\Models\BookingWaitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistSlotOffered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public BookingWaitlist $waitlist) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚡ Cơ hội nhận sân: Khung giờ bạn chờ tại {$this->waitlist->sportsField->name} đã có chỗ!"
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.waitlist-slot-offered');
    }
}
