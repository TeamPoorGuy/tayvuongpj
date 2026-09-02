<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateOwnerProfileRequest;
use App\Http\Resources\Api\OwnerProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $service) {}

    public function show(Request $request): OwnerProfileResource
    {
        return new OwnerProfileResource($this->service->ownerProfile($request->user()));
    }

    public function update(UpdateOwnerProfileRequest $request): JsonResponse
    {
        $profile = $this->service->updateOwner($request->user(), $request->validated());

        return response()->json(['message' => 'Đã cập nhật hồ sơ cơ sở.', 'data' => new OwnerProfileResource($profile)]);
    }
}
