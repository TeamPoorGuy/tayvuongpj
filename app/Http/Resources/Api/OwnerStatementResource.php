<?php

namespace App\Http\Resources\Api;

use App\Models\OwnerStatement;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OwnerStatement
 */
class OwnerStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_profile_id' => $this->owner_profile_id,
            'period' => $this->period,
            'booking_count' => (int) $this->booking_count,
            'gmv' => (float) $this->gmv,
            'commission_amount' => (float) $this->commission_amount,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'is_overdue' => $this->isOverdue(),
            'transfer_code' => $this->transfer_code,
            'reported_at' => $this->reported_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'note' => $this->note,
            'platform_bank' => [
                'bank_name' => Settings::getString('platform_bank_name', 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)'),
                'account_number' => Settings::getString('platform_bank_account', '1234567890'),
                'account_holder' => Settings::getString('platform_bank_holder', 'CONG TY SPORTHUB VIET NAM'),
            ],
            'owner' => $this->whenLoaded('ownerProfile', fn () => [
                'id' => $this->ownerProfile->id,
                'business_name' => $this->ownerProfile->business_name,
                'user_id' => $this->ownerProfile->user_id,
                'user_name' => $this->ownerProfile->user?->name,
                'user_email' => $this->ownerProfile->user?->email,
                'user_phone' => $this->ownerProfile->user?->phone,
            ]),
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => [
                'id' => $this->confirmedBy->id,
                'name' => $this->confirmedBy->name,
            ]),
            'bookings' => $this->whenLoaded('bookings', fn () => BookingResource::collection($this->bookings)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
