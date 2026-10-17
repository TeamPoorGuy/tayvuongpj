<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'address' => $this->address,
            'is_active' => (bool) $this->is_active,
            'trust_score' => (int) ($this->trust_score ?? 100),
            'email_verified' => ! config('auth.require_email_verification') || $this->hasVerifiedEmail(),
            'has_password' => $this->password !== null,
            'field_owner_profile' => $this->whenLoaded('fieldOwnerProfile', fn () => new OwnerProfileResource($this->fieldOwnerProfile)),
            'is_field_owner' => (bool) ($this->is_field_owner ?? $this->isApprovedOwner()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
