<?php

namespace App\DTOs\Booking;

use App\Http\Requests\Api\Booking\CreateBookingRequest;

readonly class CreateBookingData
{
    public function __construct(
        public int $sportsFieldId,
        public int $timeSlotId,
        public string $bookingDate,
        public ?string $notes,
    ) {}

    public static function fromRequest(CreateBookingRequest $request): self
    {
        return new self(
            sportsFieldId: $request->integer('sports_field_id'),
            timeSlotId: $request->integer('time_slot_id'),
            bookingDate: $request->string('booking_date')->toString(),
            notes: $request->input('notes'),
        );
    }
}
