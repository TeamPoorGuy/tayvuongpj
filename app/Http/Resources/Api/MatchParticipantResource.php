<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chỉ lộ tên/ảnh/điểm uy tín; SĐT của người xin tham gia chỉ chủ phòng thấy.
 */
class MatchParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $isHost = $viewer && $this->relationLoaded('matchRequest') && $this->matchRequest?->user_id === $viewer->id;
        $isSelf = $viewer && $viewer->id === $this->user_id;

        return [
            'id' => $this->id,
            'match_request_id' => $this->match_request_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'message' => ($isHost || $isSelf) ? $this->message : null,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
                'trust_score' => $this->user->trust_score,
                'phone' => $isHost ? $this->user->phone : null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
