<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Luôn trả cùng một thông báo để không lộ email nào đã đăng ký.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Nếu email tồn tại, chúng tôi đã gửi liên kết đặt lại mật khẩu.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function ($user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            // Người nhận được mail reset đã chứng minh sở hữu email.
            $user->markEmailAsVerified();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.']]);
        }

        return response()->json(['message' => 'Đặt lại mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.']);
    }
}
