<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    /**
     * Lấy danh sách cấu hình công khai phục vụ hiển thị chính sách và màn đặt sân.
     */
    public function publicSettings(): JsonResponse
    {
        return response()->json([
            'data' => Settings::public(),
        ]);
    }
}
