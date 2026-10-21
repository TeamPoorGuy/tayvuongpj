<?php

namespace App\Http\Requests\Api\Booking;

use App\Support\InputRules;
use App\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxAdvanceDays = Settings::getInt('max_advance_days', 30);
        $maxDate = today()->addDays($maxAdvanceDays)->toDateString();

        return [
            // Một khung giờ có sẵn (sports_field_id + time_slot_id), hoặc nhiều ô chọn trên lưới (items).
            'sports_field_id' => ['required_without:items', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required_without:items', 'integer', 'exists:time_slots,id'],
            'items' => ['sometimes', 'array', 'min:1', 'max:10'],
            'items.*.sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'items.*.start_time' => ['required', 'date_format:H:i', InputRules::HALF_HOUR],
            'items.*.end_time' => ['required', 'date_format:H:i', 'after:items.*.start_time', InputRules::HALF_HOUR],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$maxDate],
            'payment_option' => ['sometimes', 'in:deposit,full'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
