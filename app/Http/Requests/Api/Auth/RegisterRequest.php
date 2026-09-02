<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in([UserRole::Customer->value, UserRole::FieldOwner->value])],
            'phone' => ['required', 'regex:/^[0-9]{10,11}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required_if:role,field_owner', 'nullable', 'string', 'max:255'],
            'business_address' => ['required_if:role,field_owner', 'nullable', 'string', 'max:255'],
            'business_phone' => ['required_if:role,field_owner', 'nullable', 'string', 'max:20'],
        ];
    }
}
