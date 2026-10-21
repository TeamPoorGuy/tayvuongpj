<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\InputRules;
use Illuminate\Foundation\Http\FormRequest;
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
            'name' => InputRules::personName(),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => InputRules::phone(),
            'address' => ['nullable', 'string', 'max:255'],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }
}
