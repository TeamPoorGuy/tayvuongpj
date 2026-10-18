<?php

namespace App\Http\Requests\Api\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class HandleComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:processing,resolved,rejected'],
            'resolution' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái xử lý khiếu nại.',
            'status.in' => 'Trạng thái xử lý không hợp lệ.',
        ];
    }
}
