<?php

namespace App\Http\Requests\Api\Field;

use Illuminate\Foundation\Http\FormRequest;

class AvailableSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date', 'after_or_equal:today']];
    }
}
