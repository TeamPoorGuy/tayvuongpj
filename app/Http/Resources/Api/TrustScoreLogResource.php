<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrustScoreLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'change' => (int) $this->change,
            'score_after' => (int) $this->score_after,
            'reason' => $this->reason,
            'booking_id' => $this->booking_id,
            'sports_field_name' => $this->booking?->sportsField?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
