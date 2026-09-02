<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_address' => $this->business_address,
            'business_phone' => $this->business_phone,
            'business_license' => $this->business_license,
            'description' => $this->description,
            'verification_status' => $this->verification_status,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
