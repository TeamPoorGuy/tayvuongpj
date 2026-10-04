<?php

namespace App\Mail;

use App\Models\FieldOwnerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerApplicationVerified extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FieldOwnerProfile $profile) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->profile->verification_status->value) {
            'approved' => 'Hồ sơ chủ sân của bạn đã được duyệt',
            'suspended' => 'Quyền chủ sân của bạn đã bị tạm khoá',
            default => 'Hồ sơ chủ sân của bạn đã bị từ chối',
        });
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.owner-application-verified',
        );
    }
}
