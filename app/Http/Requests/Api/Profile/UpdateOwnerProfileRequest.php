<?php

namespace App\Http\Requests\Api\Profile;

use App\Support\Banks;
use App\Support\InputRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'business_phone' => InputRules::phone(),
            'description' => ['nullable', 'string', 'max:2000'],
            // Tài khoản nhận tiền chuyển khoản của khách; bắt buộc để đối soát và thanh toán.
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_bin' => ['nullable', Rule::in(Banks::bins())],
            'bank_account_number' => ['required', InputRules::BANK_ACCOUNT],
            'bank_account_holder' => InputRules::bankHolder(),
        ];
    }
}
