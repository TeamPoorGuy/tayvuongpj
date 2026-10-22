<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingWaitlistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sports_field_id' => $this->sports_field_id,
            'time_slot_id' => $this->time_slot_id,
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            'status' => $this->status,
            'priority_score' => $this->priority_score,
            'offered_at' => $this->offered_at?->toISOString(),
            'offer_expires_at' => $this->offer_expires_at?->toISOString(),
            'sports_field' => $this->whenLoaded('sportsField', fn () => [
                'id' => $this->sportsField->id,
                'name' => $this->sportsField->name,
                'slug' => $this->sportsField->slug,
                'address' => $this->sportsField->address,
                'primary_image' => $this->sportsField->relationLoaded('primaryImage') ? $this->sportsField->primaryImage?->image_path : null,
            ]),
            // Giờ lấy từ chính lượt chờ (chờ theo khoảng giờ trên lưới không gắn khung giờ nào).
            'time_slot' => [
                'id' => $this->time_slot_id,
                'start_time' => substr((string) $this->start_time, 0, 5),
                'end_time' => substr((string) $this->end_time, 0, 5),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
