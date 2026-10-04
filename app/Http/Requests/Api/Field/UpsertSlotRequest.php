<?php

namespace App\Http\Requests\Api\Field;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'price_per_hour' => ['nullable', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
