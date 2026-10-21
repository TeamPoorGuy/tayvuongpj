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

    /** Thông báo cụ thể theo từng ô để giao diện hiện ngay dưới ô sai. */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'name.regex' => 'Họ tên chỉ gồm chữ cái, không chứa chữ số hoặc ký tự đặc biệt.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email chưa đúng định dạng.',
            'email.unique' => 'Email này đã được đăng ký. Hãy đăng nhập hoặc dùng email khác.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại gồm 10–11 chữ số và bắt đầu bằng 0.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'terms_accepted.accepted' => 'Bạn cần đồng ý với Điều khoản dịch vụ và Chính sách bảo mật.',
            'terms_accepted.required' => 'Bạn cần đồng ý với Điều khoản dịch vụ và Chính sách bảo mật.',
        ];
    }
}
