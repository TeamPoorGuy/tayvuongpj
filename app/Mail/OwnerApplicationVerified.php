<?php

namespace App\Mail;

use App\Models\FieldOwnerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerApplicationVerified extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FieldOwnerProfile $profile) {}

    public function envelope(): Envelope
    {
        $approved = $this->profile->verification_status->value === 'approved';

        return new Envelope(
            subject: $approved ? 'Hồ sơ chủ sân của bạn đã được duyệt' : 'Hồ sơ chủ sân của bạn đã bị từ chối',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.owner-application-verified',
        );
    }
}
