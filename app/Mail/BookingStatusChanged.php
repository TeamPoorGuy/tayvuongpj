<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $label = match ($this->booking->status->value) {
            'confirmed' => 'đã được xác nhận',
            'rejected' => 'bị từ chối',
            'cancelled' => 'đã bị huỷ',
            default => 'đã được cập nhật',
        };

        return new Envelope(subject: "Đơn đặt sân #{$this->booking->id} {$label}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-status');
    }
}
