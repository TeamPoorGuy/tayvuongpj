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
                || (isset($attributes['bank_account_number']) && $attributes['bank_account_number'] !== $profile->bank_account_number)
                || (isset($attributes['bank_account_holder']) && $attributes['bank_account_holder'] !== $profile->bank_account_holder);

            if ($bankChanging) {
                $fieldIds = \App\Models\SportsField::withTrashed()->where('field_owner_id', $user->id)->pluck('id');
                $hasPendingPayments = \App\Models\Booking::whereIn('sports_field_id', $fieldIds)
                    ->whereIn('payment_status', ['awaiting', 'reported'])
                    ->whereIn('status', [\App\Enums\BookingStatus::Pending->value, \App\Enums\BookingStatus::Confirmed->value])
                    ->exists();

                if ($hasPendingPayments) {
                    throw new \App\Exceptions\ConflictException('Không thể thay đổi thông tin tài khoản ngân hàng khi đang có đơn chờ thanh toán hoặc đang xác nhận chuyển khoản.');
                }
            }
        }

        return $user->fieldOwnerProfile()->updateOrCreate(['user_id' => $user->id], $attributes);
    }
}
