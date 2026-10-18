<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Complaint\HandleComplaintRequest;
use App\Http\Resources\Api\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'category', 'keyword', 'per_page']);
        $complaints = $this->service->list($filters);

        return ComplaintResource::collection($complaints);
    }

    public function show(Complaint $complaint): ComplaintResource
    {
        $complaint->load(['user', 'booking.sportsField', 'handler']);

        return new ComplaintResource($complaint);
    }

    public function handle(HandleComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $updated = $this->service->handle(
            $complaint,
            $request->validated('status'),
            $request->validated('resolution'),
            $request->user()
        );

        return response()->json([
            'message' => 'Cập nhật trạng thái khiếu nại thành công.',
            'data' => new ComplaintResource($updated),
        ]);
    }
}
