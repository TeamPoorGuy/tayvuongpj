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
            'resolution' => ['required_unless:status,processing', 'nullable', 'string', 'min:5', 'max:3000'],
            'refund_amount' => ['nullable', 'integer', 'min:1000', 'max:100000000'],
            'confirm_attendance' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái xử lý khiếu nại.',
            'status.in' => 'Trạng thái xử lý không hợp lệ.',
            'resolution.required_unless' => 'Vui lòng ghi phương án giải quyết trước khi đóng khiếu nại.',
            'resolution.min' => 'Phương án giải quyết tối thiểu 5 ký tự.',
            'refund_amount.min' => 'Số tiền hoàn tối thiểu 1.000đ.',
        ];
    }
}
