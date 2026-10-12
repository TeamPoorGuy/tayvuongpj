<?php

namespace App\Mail;

use App\Models\MatchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatchCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public MatchRequest $match, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Trận \"{$this->match->title}\" đã bị huỷ");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.match-cancelled');
    }
}
