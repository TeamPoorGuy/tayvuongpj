<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Đã gửi lại email xác thực. Vui lòng kiểm tra hộp thư.']);
    }
}
