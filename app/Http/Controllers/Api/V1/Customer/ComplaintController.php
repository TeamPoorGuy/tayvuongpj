<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Complaint\StoreComplaintRequest;
use App\Http\Resources\Api\ComplaintResource;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $complaints = $this->service->userComplaints($request->user());

        return ComplaintResource::collection($complaints);
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->service->create($request->validated(), $request->user());

        return (new ComplaintResource($complaint))
            ->response()
            ->setStatusCode(201);
    }
}
