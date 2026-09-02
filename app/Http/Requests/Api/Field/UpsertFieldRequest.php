<?php

namespace App\Http\Requests\Api\Field;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'field_type_id' => ['required', 'integer', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
