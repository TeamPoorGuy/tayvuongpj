<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OwnerApplicationVerified;
use App\Models\FieldOwnerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class FieldOwnerController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([
                FieldOwnerProfile::STATUS_PENDING,
                FieldOwnerProfile::STATUS_APPROVED,
                FieldOwnerProfile::STATUS_REJECTED,
            ])],
        ]);

        $profiles = FieldOwnerProfile::with('user')
            ->when($request->input('status'), fn ($query, $status) => $query->where('verification_status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.field-owners.index', compact('profiles'));
    }

    public function verify(Request $request, FieldOwnerProfile $profile)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                FieldOwnerProfile::STATUS_APPROVED,
                FieldOwnerProfile::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ]);

        if ($profile->verification_status !== FieldOwnerProfile::STATUS_PENDING) {
            return back()->with('error', 'Hồ sơ này đã được xử lý.');
        }

        $profile->update([
            'verification_status' => $data['status'],
            'rejection_reason' => $data['status'] === FieldOwnerProfile::STATUS_REJECTED
                ? $data['rejection_reason']
                : null,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        $profile->refresh()->load('user');
        try {
            Mail::to($profile->user->email)->send(new OwnerApplicationVerified($profile));
        } catch (\Throwable $exception) {
            // Việc gửi mail không được làm hỏng kết quả duyệt hồ sơ.
            report($exception);
        }

        return back()->with('success', 'Đã xử lý hồ sơ đăng ký chủ sân.');
    }
}
