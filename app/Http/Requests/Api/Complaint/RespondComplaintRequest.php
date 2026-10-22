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
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'response.required' => 'Vui lòng nhập nội dung phản hồi.',
            'photos.max' => 'Chỉ đính kèm tối đa 3 ảnh chứng cứ.',
            'photos.*.image' => 'Chứng cứ phải là ảnh JPG, PNG hoặc WEBP.',
            'photos.*.max' => 'Mỗi ảnh tối đa 5MB.',
            'response.min' => 'Nội dung phản hồi tối thiểu 5 ký tự.',
        ];
    }
}
