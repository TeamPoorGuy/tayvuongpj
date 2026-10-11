<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trang tìm đối là công khai: không trả nguyên model User (email, SĐT, địa chỉ, google_id...).
 */
class MatchRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $isHost = $viewer && $viewer->id === $this->user_id;

        $participants = $this->relationLoaded('participants') ? $this->participants : collect();
        $isAccepted = $viewer && $participants->contains(fn ($p) => $p->user_id === $viewer->id && $p->status === 'accepted');

        // Chủ phòng thấy tất cả; người khác chỉ thấy người đã được chấp nhận và yêu cầu của chính mình.
        $visible = $isHost
            ? $participants
            : $participants->filter(fn ($p) => $p->status === 'accepted' || ($viewer && $p->user_id === $viewer->id));

        $visible->each(fn ($p) => $p->setRelation('matchRequest', $this->resource));

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'sport_category_id' => $this->sport_category_id,
            'sports_field_id' => $this->sports_field_id,
            'title' => $this->title,
            'play_date' => $this->play_date?->format('Y-m-d'),
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'skill_level' => $this->skill_level,
            'players_needed' => $this->players_needed,
            'joined_count' => $this->joined_count,
            'description' => $this->description,
            // SĐT chỉ hiện cho chủ phòng và người đã được chấp nhận.
            'contact_phone' => ($isHost || $isAccepted) ? $this->contact_phone : null,
            'status' => $this->status,
            'host' => $this->whenLoaded('host', fn () => [
                'id' => $this->host->id,
                'name' => $this->host->name,
                'avatar' => $this->host->avatar,
                'trust_score' => $this->host->trust_score,
            ]),
            'sport_category' => $this->whenLoaded('sportCategory'),
            'sports_field' => $this->whenLoaded('sportsField', fn () => $this->sportsField ? [
                'id' => $this->sportsField->id,
                'name' => $this->sportsField->name,
                'slug' => $this->sportsField->slug,
                'address' => $this->sportsField->address,
            ] : null),
            'participants' => $this->whenLoaded('participants', fn () => MatchParticipantResource::collection($visible->values())),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
