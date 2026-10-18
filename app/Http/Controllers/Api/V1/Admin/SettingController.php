<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $all = Settings::public();
        $all['confirmed_cancel_window_hours'] = Settings::getInt('confirmed_cancel_window_hours', 2);
        $all['late_cancel_hours'] = Settings::getInt('late_cancel_hours', 4);

        return response()->json([
            'data' => $all,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_percent' => ['nullable', 'integer', 'between:0,100'],
            'statement_due_days' => ['nullable', 'integer', 'between:1,30'],
            'deposit_percent' => ['nullable', 'integer', 'between:0,100'],
            'payment_window_hours' => ['nullable', 'integer', 'between:1,72'],
            'free_cancel_hours' => ['nullable', 'integer', 'between:1,72'],
            'confirmed_cancel_window_hours' => ['nullable', 'integer', 'between:1,24'],
            'max_pending_bookings' => ['nullable', 'integer', 'between:1,20'],
            'max_advance_days' => ['nullable', 'integer', 'between:1,365'],
            'platform_bank_name' => ['nullable', 'string', 'max:100'],
            'platform_bank_account' => ['nullable', 'string', 'max:50'],
            'platform_bank_holder' => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:100'],
            'support_hotline' => ['nullable', 'string', 'max:50'],
        ]);

        Settings::setMany($validated);
        Settings::clearCache();

        AdminLog::record('update_settings', null, $validated, $request->user());

        return response()->json([
            'message' => 'Cập nhật cấu hình hệ thống thành công.',
            'data' => Settings::public(),
        ]);
    }
}
