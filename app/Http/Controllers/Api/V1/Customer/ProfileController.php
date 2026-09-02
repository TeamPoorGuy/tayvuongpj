<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\DTOs\Profile\UpdateCustomerProfileData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateCustomerProfileRequest;
use App\Http\Requests\Api\Profile\UpdatePasswordRequest;
use App\Http\Resources\Api\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('fieldOwnerProfile'));
    }

    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateCustomer($request->user(), UpdateCustomerProfileData::fromRequest($request));

        return response()->json(['message' => 'Đã cập nhật hồ sơ.', 'data' => new UserResource($user)]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->profileService->updatePassword($request->user(), $request->string('password')->toString());

        return response()->json(['message' => 'Đã đổi mật khẩu.']);
    }
}
