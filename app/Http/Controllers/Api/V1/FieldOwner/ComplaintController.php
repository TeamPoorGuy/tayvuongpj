<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Complaint\RespondComplaintRequest;
use App\Http\Requests\Api\Complaint\StoreComplaintRequest;
use App\Http\Resources\Api\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $service) {}

    /** Khiếu nại do chủ sân gửi và khiếu nại của khách nhắm vào chủ sân. */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection($this->service->involving($request->user()));
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->service->create($request->validated(), $request->user(), $request->file('photos', []));

        return (new ComplaintResource($complaint))->response()->setStatusCode(201);
    }

    public function respond(RespondComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $updated = $this->service->respond($complaint, $request->user(), $request->validated('response'), $request->file('photos', []));

        return response()->json([
            'message' => 'Đã gửi phản hồi của bạn tới Ban quản trị.',
            'data' => new ComplaintResource($updated),
        ]);
    }
}
