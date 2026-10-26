<?php

namespace App\Services;

use App\DTOs\Profile\UpdateCustomerProfileData;
use App\Enums\VerificationStatus;
use App\Models\FieldOwnerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    public function updateCustomer(User $user, UpdateCustomerProfileData $data): User
    {
        $attributes = [
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'address' => $data->address,
        ];

        if ($data->avatar) {
            $attributes['avatar'] = '/storage/'.$data->avatar->store('avatars', 'public');
        }

        $user->fill($attributes);

        // Đổi email phải xác thực lại địa chỉ mới.
        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user->refresh()->load('fieldOwnerProfile');
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => Hash::make($password)]);
    }

    public function ownerProfile(User $user): FieldOwnerProfile
    {
        return $user->fieldOwnerProfile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $user->name,
                'business_address' => $user->address ?: '',
                'business_phone' => $user->phone ?: '',
                'verification_status' => VerificationStatus::Pending,
            ]
        );
    }

    public function updateOwner(User $user, array $attributes): FieldOwnerProfile
    {
        $profile = $user->fieldOwnerProfile;

        if ($profile) {
            $bankChanging = (isset($attributes['bank_name']) && $attributes['bank_name'] !== $profile->bank_name)
                || (isset($attributes['bank_bin']) && $attributes['bank_bin'] !== $profile->bank_bin)
                || (isset($attributes['bank_account_number']) && $attributes['bank_account_number'] !== $profile->bank_account_number)
                || (isset($attributes['bank_account_holder']) && $attributes['bank_account_holder'] !== $profile->bank_account_holder);

            if ($bankChanging) {
                if (\App\Models\Withdrawal::where('user_id', $user->id)->where('status', 'pending')->exists()) {
                    throw new \App\Exceptions\ConflictException('Không thể đổi tài khoản ngân hàng khi đang có lệnh rút tiền chờ xử lý.');
                }
            }
        }

        if (isset($attributes['bank_name']) && empty($attributes['bank_bin'])) {
            $attributes['bank_bin'] = \App\Support\Banks::guessBin($attributes['bank_name']);
        }

        return $user->fieldOwnerProfile()->updateOrCreate(['user_id' => $user->id], $attributes);
    }
}
