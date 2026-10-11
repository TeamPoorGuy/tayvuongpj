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
        return $user->fieldOwnerProfile()->updateOrCreate(['user_id' => $user->id], $attributes);
    }
}
