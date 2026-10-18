<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxAdvanceDays = \App\Support\Settings::getInt('max_advance_days', 30);
        $maxDate = today()->addDays($maxAdvanceDays)->toDateString();

        return [
            'sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.$maxDate],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
