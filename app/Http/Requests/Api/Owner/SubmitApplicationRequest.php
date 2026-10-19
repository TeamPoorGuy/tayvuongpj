<?php

namespace App\Http\Requests\Api\Owner;

use App\Models\OwnerDocument;
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
                Rule::requiredIf($required), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240',
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
            'documents.id_front.mimes' => 'Ảnh CCCD mặt trước phải là định dạng: JPG, PNG, WEBP hoặc PDF.',
            'documents.id_front.max' => 'Ảnh CCCD mặt trước dung lượng tối đa 10MB.',
            'documents.id_back.required' => 'Vui lòng tải ảnh CCCD mặt sau.',
            'documents.id_back.mimes' => 'Ảnh CCCD mặt sau phải là định dạng: JPG, PNG, WEBP hoặc PDF.',
            'documents.id_back.max' => 'Ảnh CCCD mặt sau dung lượng tối đa 10MB.',
            'documents.business_license.required' => 'Vui lòng tải giấy phép/đăng ký kinh doanh.',
            'documents.business_license.mimes' => 'Giấy phép kinh doanh phải là định dạng: JPG, PNG, WEBP hoặc PDF.',
            'documents.business_license.max' => 'Giấy phép kinh doanh dung lượng tối đa 10MB.',
            'documents.land_right.mimes' => 'Giấy tờ quyền sân phải là định dạng: JPG, PNG, WEBP hoặc PDF.',
            'documents.land_right.max' => 'Giấy tờ quyền sân dung lượng tối đa 10MB.',
        ];
    }
}
