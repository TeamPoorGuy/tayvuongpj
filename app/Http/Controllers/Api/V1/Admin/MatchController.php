<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MatchRequestResource;
use App\Services\AdminService;
use App\Services\MatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchController extends Controller
{
    public function __construct(private readonly AdminService $service, private readonly MatchmakingService $matches) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:open,full,completed,cancelled'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        return MatchRequestResource::collection($this->service->matches($request->input('status'), $request->input('keyword')));
    }

    /** Admin gỡ phòng vi phạm; người đã được chấp nhận nhận mail như khi chủ phòng huỷ. */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $match = $this->matches->cancel($request->user(), $id);

        return response()->json([
            'message' => 'Đã huỷ phòng tìm đối.',
            'data' => new MatchRequestResource($match->load(['host', 'sportCategory', 'sportsField', 'participants.user'])),
        ]);
    }
}
