<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_price' => (float) $this->total_price,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'can_be_cancelled' => $this->canBeCancelled(),
            'field' => $this->whenLoaded('sportsField', fn () => new FieldResource($this->sportsField)),
            'customer' => $this->whenLoaded('customer', fn () => new UserResource($this->customer)),
            'time_slot' => $this->whenLoaded('timeSlot', fn () => [
                'id' => $this->timeSlot->id,
                'start_time' => substr($this->timeSlot->start_time, 0, 5),
                'end_time' => substr($this->timeSlot->end_time, 0, 5),
            ]),
            'review' => $this->whenLoaded('review', fn () => $this->review ? new ReviewResource($this->review) : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
