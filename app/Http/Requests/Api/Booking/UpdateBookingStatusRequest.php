<?php

namespace App\Http\Requests\Api\Booking;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BookingStatus::class)],
            // Huỷ đơn đã xác nhận phải có lý do để báo cho khách.
            'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:500'],
        ];
    }
}
