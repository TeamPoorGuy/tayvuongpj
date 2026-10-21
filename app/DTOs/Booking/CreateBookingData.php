<?php

namespace App\DTOs\Booking;

use App\Http\Requests\Api\Booking\CreateBookingRequest;

readonly class CreateBookingData
{
    /** @param list<array{sports_field_id:int, time_slot_id?:int|null, start_time?:string, end_time?:string}> $items */
    public function __construct(
        public string $bookingDate,
        public array $items,
        public string $paymentOption,
        public ?string $notes,
    ) {}

    public static function fromRequest(CreateBookingRequest $request): self
    {
        $items = $request->has('items')
            ? array_map(fn (array $item) => [
                'sports_field_id' => (int) $item['sports_field_id'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
            ], $request->input('items'))
            : [['sports_field_id' => $request->integer('sports_field_id'), 'time_slot_id' => $request->integer('time_slot_id')]];

        return new self(
            bookingDate: $request->string('booking_date')->toString(),
            items: $items,
            paymentOption: $request->input('payment_option', 'deposit'),
            notes: $request->input('notes'),
        );
    }
}
