<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Thông báo tự động chung: tiêu đề, vài dòng nội dung và (tuỳ chọn) một nút dẫn tới trang trong ứng dụng. */
class Notice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $lines */
    public function __construct(
        public string $heading,
        public array $lines,
        public ?string $buttonLabel = null,
        public ?string $buttonPath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notice');
    }
}
