<?php

namespace App\Http\Requests\Api\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class RespondComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'response.required' => 'Vui lòng nhập nội dung phản hồi.',
            'response.min' => 'Nội dung phản hồi tối thiểu 5 ký tự.',
        ];
    }
}
