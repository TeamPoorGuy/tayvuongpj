<?php

namespace App\Http\Resources\Api;

use App\Models\OwnerStatement;
use App\Support\Banks;
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
            'collected_amount' => (float) $this->collected_amount,
            'refunded_amount' => (float) $this->refunded_amount,
            'commission_amount' => (float) $this->commission_amount,
            'payout_amount' => (float) $this->payout_amount,
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'is_overdue' => $this->isOverdue(),
            'transfer_code' => $this->transfer_code,
            'payout_ref' => $this->payout_ref,
            'paid_at' => $this->paid_at?->toISOString(),
            'note' => $this->note,
            // Tài khoản nhận tiền chi trả của chủ sân (khai trong hồ sơ cơ sở).
            'payout_bank' => [
                'bank_name' => $this->ownerProfile?->bank_name,
                'account_number' => $this->ownerProfile?->bank_account_number,
                'account_holder' => $this->ownerProfile?->bank_account_holder,
                'qr_url' => $this->qrUrl(),
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

    /** VietQR để admin chuyển khoản chi trả cho chủ sân; null khi đã chi trả hoặc chủ sân chưa khai đủ tài khoản. */
    private function qrUrl(): ?string
    {
        $profile = $this->ownerProfile;
        $bin = $profile?->bank_bin ?? Banks::guessBin($profile?->bank_name);
        if ($this->status !== 'unpaid' || (float) $this->payout_amount <= 0 || ! $bin || ! $profile?->bank_account_number) {
            return null;
        }

        return "https://img.vietqr.io/image/{$bin}-{$profile->bank_account_number}-compact2.png?".http_build_query([
            'amount' => (int) $this->payout_amount,
            'addInfo' => $this->transfer_code,
            'accountName' => $profile->bank_account_holder,
        ]);
    }
}
