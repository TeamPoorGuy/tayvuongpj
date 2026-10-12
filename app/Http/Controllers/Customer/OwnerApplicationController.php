<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FieldOwnerProfile;
use Illuminate\Http\Request;

class OwnerApplicationController extends Controller
{
    public function edit(Request $request)
    {
        $profile = $request->user()->fieldOwnerProfile;

        return view('customer.owner-application.edit', compact('profile'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_id_number' => ['required', 'regex:/^[0-9]{9,12}$/'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'string', 'max:20'],
            'business_license' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'license_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $existing = $request->user()->fieldOwnerProfile;
        if ($existing && $existing->verification_status !== FieldOwnerProfile::STATUS_REJECTED) {
            return back()->withInput()->with(
                'error',
                'Bạn đã có hồ sơ đăng ký chủ sân đang chờ duyệt hoặc đã được duyệt.'
            );
        }

        unset($data['license_file']);
        $data['verification_status'] = FieldOwnerProfile::STATUS_PENDING;
        $data['rejection_reason'] = null;
        $data['verified_at'] = null;
        $data['verified_by'] = null;

        if ($request->hasFile('license_file')) {
            $data['verification_documents'] = '/storage/'.$request->file('license_file')->store('licenses', 'public');
        }

        $request->user()->fieldOwnerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return redirect()->route('customer.owner-application.edit')
            ->with('success', 'Đã gửi hồ sơ đăng ký làm chủ sân.');
    }
}
