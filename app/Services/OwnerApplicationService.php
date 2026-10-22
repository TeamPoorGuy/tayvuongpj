<?php

namespace App\Services;

use App\DTOs\Owner\SubmitApplicationData;
use App\Enums\VerificationStatus;
use App\Exceptions\ConflictException;
use App\Models\FieldOwnerProfile;
use App\Models\OwnerDocument;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OwnerApplicationService
{
    public function show(User $user): ?FieldOwnerProfile
    {
        return $user->fieldOwnerProfile?->load('documents');
    }

    public function submit(User $user, SubmitApplicationData $data): FieldOwnerProfile
    {
        $existing = $user->fieldOwnerProfile;

        if ($existing && $existing->verification_status !== VerificationStatus::Rejected) {
            throw new ConflictException('Bạn đã có hồ sơ đăng ký chủ sân đang chờ duyệt, đã được duyệt hoặc đang bị tạm khoá.');
        }

        $idNumber = $data->ownerIdNumber ?: $existing?->owner_id_number;
        $hash = FieldOwnerProfile::hashIdNumber($idNumber);
        if (FieldOwnerProfile::where('owner_id_hash', $hash)->where('user_id', '!=', $user->id)->exists()) {
            throw new ConflictException('Số CCCD này đã được dùng để đăng ký bởi tài khoản khác.');
        }

        return DB::transaction(function () use ($user, $data, $hash, $idNumber) {
            $profile = $user->fieldOwnerProfile()->updateOrCreate(['user_id' => $user->id], [
                'owner_name' => $data->ownerName,
                'owner_id_number' => $idNumber,
                'owner_id_hash' => $hash,
                'business_name' => $data->businessName,
                'business_address' => $data->businessAddress,
                'business_phone' => $data->businessPhone,
                'business_license' => $data->businessLicense,
                'description' => $data->description,
                'bank_name' => $data->bankName,
                'bank_bin' => $data->bankBin,
                'bank_account_number' => $data->bankAccountNumber,
                'bank_account_holder' => $data->bankAccountHolder,
                'terms_version' => Settings::getString('owner_terms_version', '2026-10'),
                'terms_accepted_at' => now(),

                'terms_ip' => $data->ip,
                'verification_status' => VerificationStatus::Pending,
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
            ]);

            foreach ($data->documents as $type => $file) {
                if (! in_array($type, OwnerDocument::TYPES, true)) {
                    continue;
                }
                if ($old = $profile->documents()->where('type', $type)->first()) {
                    Storage::disk('local')->delete($old->path);
                }
                // Disk private: giấy tờ chỉ được tải qua route có kiểm quyền.
                $profile->documents()->updateOrCreate(['type' => $type], [
                    'path' => $file->store('licenses', 'local'),
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            return $profile->load('documents');
        });
    }

    /** Số ngày giữ giấy tờ của hồ sơ bị từ chối trước khi tự xoá. */
    public const REJECTED_RETENTION_DAYS = 30;

    /** Xoá file và bản ghi giấy tờ của hồ sơ bị từ chối quá hạn lưu; hồ sơ vẫn giữ để tra cứu. */
    public function purgeRejectedDocuments(): int
    {
        $count = 0;

        FieldOwnerProfile::query()
            ->where('verification_status', VerificationStatus::Rejected)
            ->where('verified_at', '<', now()->subDays(self::REJECTED_RETENTION_DAYS))
            ->whereHas('documents')
            ->with('documents')
            ->each(function (FieldOwnerProfile $profile) use (&$count) {
                foreach ($profile->documents as $document) {
                    Storage::disk('local')->delete($document->path);
                    $document->delete();
                    $count++;
                }
            });

        return $count;
    }
}
