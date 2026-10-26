<?php

namespace App\Http\Requests\Api\Field;

use App\Support\InputRules;
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
            'start_time' => ['required', 'date_format:H:i', InputRules::HALF_HOUR],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time', InputRules::HALF_HOUR],
            'price_per_hour' => ['nullable', 'integer', 'min:1000', 'max:'.InputRules::MAX_MONEY],
            'days_of_week' => ['nullable', 'array', 'max:7'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
