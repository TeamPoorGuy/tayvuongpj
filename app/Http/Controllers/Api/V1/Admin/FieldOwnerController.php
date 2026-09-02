<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\VerifyRequest;
use App\Http\Resources\Api\OwnerProfileResource;
use App\Models\FieldOwnerProfile;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FieldOwnerController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return OwnerProfileResource::collection($this->service->ownerProfiles());
    }

    public function verify(VerifyRequest $request, FieldOwnerProfile $profile): JsonResponse
    {
        return response()->json(['message' => 'Đã xử lý hồ sơ chủ sân.', 'data' => new OwnerProfileResource($this->service->verifyOwner($profile, $request->string('status')->toString()))]);
    }
}
