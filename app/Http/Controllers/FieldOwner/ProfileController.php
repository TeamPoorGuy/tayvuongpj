<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\FieldOwnerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $profile = FieldOwnerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $user->name,
                'business_address' => $user->address ?? '',
                'business_phone' => $user->phone ?? '',
                'verification_status' => 'pending',
            ]
        );

        return view('field-owner.profile.edit', compact('user', 'profile'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:255'],
            'business_phone' => ['required', 'string', 'max:20'],
            'business_license' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $user->fieldOwnerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['business_name', 'business_address', 'business_phone', 'business_license', 'description'])
        );

        return back()->with('success', 'Đã cập nhật thông tin cơ sở kinh doanh thành công.');
    }
}
