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
            'field_owner_profile' => $this->whenLoaded('fieldOwnerProfile', fn () => new OwnerProfileResource($this->fieldOwnerProfile)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
