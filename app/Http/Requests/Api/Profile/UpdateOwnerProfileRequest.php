<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'string', 'max:20'],
            'business_license' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
