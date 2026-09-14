<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\DTOs\Owner\SubmitApplicationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Owner\SubmitApplicationRequest;
use App\Http\Resources\Api\OwnerProfileResource;
use App\Services\OwnerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerApplicationController extends Controller
{
    public function __construct(private readonly OwnerApplicationService $service) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->service->show($request->user());

        return response()->json(['data' => $profile ? new OwnerProfileResource($profile) : null]);
    }

    public function store(SubmitApplicationRequest $request): JsonResponse
    {
        $profile = $this->service->submit($request->user(), SubmitApplicationData::fromRequest($request));

        return response()->json(['message' => 'Đã gửi hồ sơ đăng ký làm chủ sân.', 'data' => new OwnerProfileResource($profile)], 201);
    }
}
