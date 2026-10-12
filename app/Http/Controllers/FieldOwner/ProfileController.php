<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\FieldOwnerProfile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $profile = $request->user()->fieldOwnerProfile()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'business_name' => $request->user()->name,
                'business_address' => $request->user()->address ?: '',
                'business_phone' => $request->user()->phone ?: '',
                'verification_status' => FieldOwnerProfile::STATUS_PENDING,
            ]
        );

        return view('field-owner.profile.edit', compact('profile'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'string', 'max:20'],
            'business_license' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $request->user()->fieldOwnerProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return back()->with('success', 'Đã cập nhật thông tin cơ sở kinh doanh.');
    }
}
