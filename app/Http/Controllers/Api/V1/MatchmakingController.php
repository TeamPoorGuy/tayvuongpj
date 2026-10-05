<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MatchRequest;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchmakingController extends Controller
{
    public function __construct(protected MatchmakingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $matches = $this->service->list($request->only(['category_id', 'skill_level', 'status', 'date']));

        return response()->json($matches);
    }

    public function show(int $id): JsonResponse
    {
        $match = MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])->findOrFail($id);

        return response()->json(['data' => $match]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_category_id' => ['required', 'integer', 'exists:sport_categories,id'],
            'sports_field_id' => ['nullable', 'integer', 'exists:sports_fields,id'],
            'title' => ['required', 'string', 'max:150'],
            'play_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'string', 'max:10'],
            'end_time' => ['required', 'string', 'max:10'],
            'skill_level' => ['required', 'in:beginner,intermediate,advanced,any'],
            'players_needed' => ['required', 'integer', 'min:1', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['required', 'string', 'max:20'],
        ]);

        $match = $this->service->create($request->user(), $validated);

        return response()->json([
            'message' => 'Tạo phòng tìm đối/người chơi thành công!',
            'data' => $match,
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
            'data' => $participant,
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
            'data' => $participant,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $match = $this->service->cancel($request->user(), $id);

        return response()->json([
            'message' => 'Đã huỷ bài tìm đối.',
            'data' => $match,
        ]);
    }
}
