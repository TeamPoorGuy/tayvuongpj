<?php

namespace App\Http\Requests\Api\Owner;

use App\Models\OwnerDocument;
use App\Support\Banks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profile = $this->user()->fieldOwnerProfile;

        $rules = [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_id_number' => ['required', 'regex:/^[0-9]{12}$/'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'regex:/^[0-9]{10,11}$/'],
            'business_license' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_bin' => ['nullable', Rule::in(Banks::bins())],
            'bank_account_number' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
            'accept_terms' => ['accepted'],
            'documents' => ['sometimes', 'array'],
        ];

        foreach (OwnerDocument::TYPES as $type) {
            // Nộp lại sau khi bị từ chối: loại giấy tờ đã có thì không bắt buộc tải lại.
            $required = in_array($type, OwnerDocument::REQUIRED, true)
                && ! $profile?->documents()->where('type', $type)->exists();

            $rules["documents.$type"] = [
                Rule::requiredIf($required), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Bạn cần đồng ý với điều khoản dành cho chủ sân.',
            'owner_id_number.regex' => 'Số CCCD phải gồm đúng 12 chữ số.',
            'documents.id_front.required' => 'Vui lòng tải ảnh CCCD mặt trước.',
            'documents.id_back.required' => 'Vui lòng tải ảnh CCCD mặt sau.',
            'documents.business_license.required' => 'Vui lòng tải giấy phép/đăng ký kinh doanh.',
        ];
    }
}
