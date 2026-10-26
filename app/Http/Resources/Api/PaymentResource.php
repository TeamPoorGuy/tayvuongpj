<?php

namespace App\Http\Resources\Api;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bankCode = Settings::getString('platform_bank_code', 'VCB');
        $account = Settings::getString('platform_bank_account', '1234567890');

        return [
            'id' => $this->id,
            'purpose' => $this->purpose ?? 'booking',
            'code' => $this->code,
            'option' => $this->option,
            'total' => (float) $this->total,
            'amount' => (float) $this->amount,
            // Phần đã trừ từ ví và phần còn phải chuyển khoản.
            'wallet_amount' => (float) $this->wallet_amount,
            'due_amount' => $this->dueAmount(),
            'deposit_percent' => Settings::getInt('deposit_percent', 30),
            'status' => $this->status,
            'due_at' => $this->due_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'source' => $this->source,
            'bank_ref' => $this->bank_ref,
            // Tài khoản nền tảng (thu hộ) — khách không chuyển thẳng cho chủ sân.
            'bank' => [
                'name' => Settings::getString('platform_bank_name', 'Vietcombank'),
                'code' => $bankCode,
                'account_number' => $account,
                'account_holder' => Settings::getString('platform_bank_holder', 'CONG TY SPORTHUB VIET NAM'),
                'qr_url' => $this->status === 'awaiting'
                    ? "https://img.vietqr.io/image/{$bankCode}-{$account}-compact2.png?amount=".(int) $this->dueAmount().'&addInfo='.$this->code
                    : null,
            ],
            'customer' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
