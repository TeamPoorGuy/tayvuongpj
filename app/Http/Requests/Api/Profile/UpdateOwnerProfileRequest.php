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
            'description' => ['nullable', 'string'],
            // Tài khoản nhận tiền chuyển khoản của khách; bắt buộc để đối soát và thanh toán.
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
        ];
    }
}
