<?php

namespace App\Http\Requests\Api\Field;

use App\Support\InputRules;
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
            'name' => ['required', 'string', 'max:150'],
            'field_type_id' => ['required', 'integer', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            // Quận/thành phố: chữ, số ("Quận 1"), khoảng trắng; để trống thì suy ra từ địa chỉ.
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\d\s.\-\']+$/u'],
            'district' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\d\s.\-\']+$/u'],
            'price_per_hour' => InputRules::money(),
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
