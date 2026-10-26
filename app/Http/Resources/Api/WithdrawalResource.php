<?php

namespace App\Http\Resources\Api;

use App\Support\VietQr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'auto' => (bool) $this->auto,
            'reference' => $this->reference,
            'reason' => $this->reason,
            'bank' => [
                'bin' => $this->bank_bin,
                'name' => $this->bank_name,
                'account_number' => $this->account_number,
                'account_holder' => $this->account_holder,
                // Chỉ admin cần mã QR để chuyển khoản cho lệnh đang chờ.
                'qr_url' => $this->status === 'pending' && $request->user()?->isAdmin()
                    ? VietQr::url($this->bank_bin, $this->account_number, $this->account_holder, (float) $this->amount, "SportHub rut vi #{$this->id}")
                    : null,
            ],
            'user' => $this->whenLoaded('user', fn () => $request->user()?->isAdmin() ? ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email] : null),
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
