<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'is_visible' => (bool) $this->is_visible,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ]),
            'field' => $this->whenLoaded('sportsField', fn () => [
                'id' => $this->sportsField->id,
                'name' => $this->sportsField->name,
                'slug' => $this->sportsField->slug,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
