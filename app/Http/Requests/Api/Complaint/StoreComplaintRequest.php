<?php

namespace App\Http\Requests\Api\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'subject_type' => ['nullable', 'string', 'in:booking,contact'],
            'subject_id' => ['nullable', 'string', 'max:100'],
            'category' => ['required', 'in:refund_pending,field_mismatch,owner_cancelled,bad_behavior,wrong_no_show,other'],
            'content' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_id.exists' => 'Đơn đặt sân được khiếu nại không tồn tại.',
            'category.required' => 'Vui lòng chọn loại vấn đề cần khiếu nại.',
            'content.required' => 'Vui lòng nhập nội dung chi tiết của khiếu nại.',
            'content.min' => 'Nội dung khiếu nại tối thiểu 5 ký tự.',
        ];
    }
}
