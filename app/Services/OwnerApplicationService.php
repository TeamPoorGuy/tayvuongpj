<?php

namespace App\Services;

use App\DTOs\Owner\SubmitApplicationData;
use App\Enums\VerificationStatus;
use App\Exceptions\ConflictException;
use App\Models\FieldOwnerProfile;
use App\Models\User;

class OwnerApplicationService
{
    public function show(User $user): ?FieldOwnerProfile
    {
        return $user->fieldOwnerProfile;
    }

    public function submit(User $user, SubmitApplicationData $data): FieldOwnerProfile
    {
        $existing = $user->fieldOwnerProfile;

        if ($existing && $existing->verification_status !== VerificationStatus::Rejected) {
            throw new ConflictException('Bạn đã có hồ sơ đăng ký chủ sân đang chờ duyệt hoặc đã được duyệt.');
        }

        $attributes = [
            'owner_name' => $data->ownerName,
            'owner_id_number' => $data->ownerIdNumber,
            'business_name' => $data->businessName,
            'business_address' => $data->businessAddress,
            'business_phone' => $data->businessPhone,
            'business_license' => $data->businessLicense,
            'description' => $data->description,
            'verification_status' => VerificationStatus::Pending,
            'rejection_reason' => null,
            'verified_at' => null,
            'verified_by' => null,
        ];

        if ($data->licenseFile) {
            $attributes['verification_documents'] = '/storage/'.$data->licenseFile->store('licenses', 'public');
        }

        return $user->fieldOwnerProfile()->updateOrCreate(['user_id' => $user->id], $attributes);
    }
}
