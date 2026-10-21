<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Support\InputRules;
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
            'commission_percent' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'statement_due_days' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'deposit_percent' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'payment_window_hours' => ['sometimes', 'required', 'integer', 'between:1,72'],
            'free_cancel_hours' => ['sometimes', 'required', 'integer', 'between:1,72'],
            'confirmed_cancel_window_hours' => ['sometimes', 'required', 'integer', 'between:1,24'],
            'max_pending_bookings' => ['sometimes', 'required', 'integer', 'between:1,20'],
            'max_advance_days' => ['sometimes', 'required', 'integer', 'between:1,365'],
            'platform_bank_name' => ['sometimes', 'required', 'string', 'max:100'],
            'platform_bank_code' => ['sometimes', 'required', 'alpha_num', 'max:20'],
            'platform_bank_account' => ['sometimes', 'required', InputRules::BANK_ACCOUNT],
            'platform_bank_holder' => ['sometimes', 'required', 'string', 'max:50', InputRules::BANK_HOLDER],
            'support_email' => ['sometimes', 'required', 'email', 'max:100'],
            'support_hotline' => ['sometimes', 'required', 'regex:/^[0-9 ]{8,15}$/'],
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
