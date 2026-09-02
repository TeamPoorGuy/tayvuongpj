<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        return response()->json(['message' => 'Đăng nhập thành công.', 'data' => new UserResource($user)]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request, RegisterUserData::fromRequest($request));

        return response()->json(['message' => 'Đăng ký tài khoản thành công.', 'data' => new UserResource($user)], 201);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('fieldOwnerProfile'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }
}
