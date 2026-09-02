<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['keyword' => ['nullable', 'string'], 'role' => ['nullable', 'in:customer,field_owner']]);

        return UserResource::collection($this->service->users($filters));
    }

    public function toggle(User $user): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật trạng thái tài khoản.', 'data' => new UserResource($this->service->toggleUser($user))]);
    }
}
