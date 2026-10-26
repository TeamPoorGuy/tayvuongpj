<?php

namespace App\Http\Requests\Api\Field;

use App\Support\InputRules;
use Illuminate\Foundation\Http\FormRequest;

class UpsertFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'field_type_id' => ['required', 'integer', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            // Quận/thành phố: chữ, số ("Quận 1"), khoảng trắng; để trống thì suy ra từ địa chỉ.
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\d\s.\-\']+$/u'],
            'district' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\d\s.\-\']+$/u'],
            'price_per_hour' => InputRules::money(),
            'description' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên sân.',
            'field_type_id.required' => 'Vui lòng chọn loại sân thể thao.',
            'address.required' => 'Vui lòng nhập địa chỉ cụ thể của sân.',
            'price_per_hour.required' => 'Vui lòng nhập giá thuê mỗi giờ.',
            'price_per_hour.gt' => 'Giá thuê mỗi giờ phải lớn hơn 0.',
            'images.max' => 'Bạn chỉ có thể tải lên tối đa 8 hình ảnh cho sân.',
            'images.*.image' => 'Tệp tải lên phải là hình ảnh hợp lệ.',
            'images.*.mimes' => 'Hình ảnh phải có định dạng JPEG, PNG, JPG hoặc WEBP.',
            'images.*.max' => 'Kích thước mỗi hình ảnh không được vượt quá 10MB.',
        ];
    }
}
