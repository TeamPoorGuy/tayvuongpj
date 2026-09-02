<?php

namespace App\Services;

use App\DTOs\Auth\RegisterUserData;
use App\Enums\UserRole;
use App\Exceptions\ForbiddenException;
use App\Models\FieldOwnerProfile;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(Request $request, string $email, string $password, bool $remember): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            throw new AuthenticationException('Email hoặc mật khẩu không chính xác.');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            throw new ForbiddenException('Tài khoản của bạn đã bị khóa.');
        }

        return $user->load('fieldOwnerProfile');
    }

    public function register(Request $request, RegisterUserData $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'role' => $data->role,
                'phone' => $data->phone,
                'address' => $data->address,
                'is_active' => true,
            ]);

            if ($data->role === UserRole::FieldOwner->value) {
                FieldOwnerProfile::create([
                    'user_id' => $user->id,
                    'business_name' => $data->businessName,
                    'business_address' => $data->businessAddress ?: $data->address,
                    'business_phone' => $data->businessPhone ?: $data->phone,
                    'verification_status' => 'pending',
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return $user->load('fieldOwnerProfile');
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
