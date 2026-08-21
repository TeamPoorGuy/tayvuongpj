<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FieldOwnerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:customer,field_owner'],
            'phone' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            
            // Validation cho chủ sân
            'business_name' => ['required_if:role,field_owner', 'nullable', 'string', 'max:255'],
            'business_address' => ['required_if:role,field_owner', 'nullable', 'string', 'max:255'],
            'business_phone' => ['required_if:role,field_owner', 'nullable', 'string', 'max:20'],
        ], [
            'phone.regex' => 'Số điện thoại phải bao gồm 10-11 chữ số.',
            'business_name.required_if' => 'Vui lòng nhập tên cơ sở kinh doanh.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
            'address' => $request->address,
            'is_active' => true,
        ]);

        if ($user->role === 'field_owner') {
            FieldOwnerProfile::create([
                'user_id' => $user->id,
                'business_name' => $request->business_name,
                'business_address' => $request->business_address ?? $request->address,
                'business_phone' => $request->business_phone ?? $request->phone,
                'verification_status' => 'pending',
            ]);
        }

        Auth::login($user);

        if ($user->isFieldOwner()) {
            return redirect()->route('field-owner.dashboard')->with('success', 'Đăng ký tài khoản chủ sân thành công! Hồ sơ đang chờ duyệt.');
        }

        return redirect()->route('home')->with('success', 'Đăng ký tài khoản thành công!');
    }
}
