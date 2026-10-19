<?php

namespace App\Http\Requests\Api\Field;

use Illuminate\Foundation\Http\FormRequest;

class AddImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Vui lòng chọn ít nhất 1 hình ảnh.',
            'images.max' => 'Bạn chỉ có thể tải lên tối đa 8 hình ảnh cho mỗi sân.',
            'images.*.image' => 'Tệp tải lên phải là hình ảnh hợp lệ.',
            'images.*.mimes' => 'Hình ảnh phải có định dạng JPEG, PNG, JPG hoặc WEBP.',
            'images.*.max' => 'Kích thước mỗi hình ảnh không được vượt quá 10MB.',
        ];
    }
}
