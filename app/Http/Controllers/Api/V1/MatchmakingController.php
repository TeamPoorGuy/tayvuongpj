<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MatchParticipantResource;
use App\Http\Resources\Api\MatchRequestResource;
use App\Models\MatchRequest;
use App\Services\MatchmakingService;
use App\Support\InputRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchmakingController extends Controller
{
    public function __construct(protected MatchmakingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $matches = $this->service->list(
            $request->only(['category_id', 'skill_level', 'status', 'date']),
            $request->boolean('mine') ? $request->user('sanctum') : null,
        );

        return MatchRequestResource::collection($matches)->response();
    }

    public function show(int $id): JsonResponse
    {
        $match = MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])->findOrFail($id);

        return response()->json(['data' => new MatchRequestResource($match)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_category_id' => ['required', 'integer', 'exists:sport_categories,id'],
            'sports_field_id' => ['nullable', 'integer', Rule::exists('sports_fields', 'id')->where('status', 'approved')->where('is_active', true)->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:150'],
            'play_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'skill_level' => ['required', 'in:beginner,intermediate,advanced,any'],
            'players_needed' => ['required', 'integer', 'min:1', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => InputRules::phone(),
        ]);

        $match = $this->service->create($request->user(), $validated);

        return response()->json([
            'message' => 'Tạo phòng tìm đối/người chơi thành công!',
            'data' => new MatchRequestResource($match->load('participants.user')),
        ], 201);
    }

    public function join(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $participant = $this->service->join($request->user(), $id, $validated['message'] ?? null);

        return response()->json([
            'message' => 'Đã gửi lời mời tham gia trận đấu đến chủ phòng.',
            'data' => new MatchParticipantResource($participant),
        ], 201);
    }

    public function updateParticipant(Request $request, int $id, int $participantId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        $participant = $this->service->updateParticipantStatus($request->user(), $id, $participantId, $validated['status']);

        $label = $validated['status'] === 'accepted' ? 'chấp nhận' : 'từ chối';

        return response()->json([
            'message' => "Đã {$label} thành viên tham gia.",
            'data' => new MatchParticipantResource($participant->load('matchRequest')),
        ]);
    }

    public function leave(Request $request, int $id): JsonResponse
    {
        $this->service->leave($request->user(), $id);

        return response()->json(['message' => 'Đã rút khỏi trận đấu.']);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $match = $this->service->cancel($request->user(), $id, $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Đã huỷ bài tìm đối.',
            'data' => new MatchRequestResource($match->load(['host', 'sportCategory', 'sportsField', 'participants.user'])),
        ]);
    }
}
