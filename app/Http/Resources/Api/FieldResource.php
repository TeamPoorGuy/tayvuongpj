<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reviews = $this->relationLoaded('reviews') ? $this->reviews : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'description' => $this->description,
            'price_per_hour' => (float) $this->price_per_hour,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'average_rating' => round((float) $reviews->where('is_visible', true)->avg('rating'), 1),
            'reviews_count' => $reviews->where('is_visible', true)->count(),
            'bookings_count' => $this->whenCounted('bookings'),
            'primary_image' => $this->primaryImage?->image_path,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->image_path,
                'is_primary' => (bool) $image->is_primary,
            ])),
            'field_type' => $this->whenLoaded('fieldType', fn () => new FieldTypeResource($this->fieldType)),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'phone' => $this->owner->phone,
                'business' => $this->owner->fieldOwnerProfile ? new OwnerProfileResource($this->owner->fieldOwnerProfile) : null,
            ]),
            'time_slots' => $this->whenLoaded('timeSlots', fn () => $this->timeSlots->map(fn ($slot) => [
                'id' => $slot->id,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'is_active' => (bool) $slot->is_active,
            ])),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
