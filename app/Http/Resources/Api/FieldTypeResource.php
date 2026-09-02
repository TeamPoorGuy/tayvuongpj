<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sport_category_id' => $this->sport_category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'category' => $this->whenLoaded('sportCategory', fn () => [
                'id' => $this->sportCategory->id,
                'name' => $this->sportCategory->name,
                'slug' => $this->sportCategory->slug,
                'icon' => $this->sportCategory->icon,
            ]),
        ];
    }
}
