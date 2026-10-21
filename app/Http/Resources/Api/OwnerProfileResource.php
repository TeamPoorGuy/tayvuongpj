<?php

namespace App\Http\Resources\Api;

use App\Support\Banks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_name' => $this->owner_name,
            'owner_id_number' => $request->user()?->isAdmin() ? $this->owner_id_number : $this->maskedIdNumber(),
            'business_name' => $this->business_name,
            'business_address' => $this->business_address,
            'business_phone' => $this->business_phone,
            'business_license' => $this->business_license,
            'description' => $this->description,
            'verification_status' => $this->verification_status,
            'bank_name' => $this->bank_name,
            'bank_bin' => $this->bank_bin ?? Banks::guessBin($this->bank_name),
            'bank_account_number' => $this->bank_account_number,
            'bank_account_holder' => $this->bank_account_holder,
            'terms_version' => $this->terms_version,
            'terms_accepted_at' => $this->terms_accepted_at?->toISOString(),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'type' => $doc->type,
                'original_name' => $doc->original_name,
                'url' => $request->user()?->isAdmin()
                    ? url("/api/v1/admin/field-owners/{$this->id}/documents/{$doc->id}")
                    : url("/api/v1/customer/owner-application/documents/{$doc->id}"),
            ])),
            'rejection_reason' => $this->rejection_reason,
            'verified_at' => $this->verified_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }

    /** Chỉ lộ 4 số cuối để người nộp nhận ra số của mình, admin mới thấy đủ. */
    private function maskedIdNumber(): ?string
    {
        $number = $this->owner_id_number;

        return $number ? str_repeat('*', max(strlen($number) - 4, 0)).substr($number, -4) : null;
    }
}
