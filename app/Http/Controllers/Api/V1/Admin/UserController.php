<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TrustScoreLogResource;
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

    public function toggle(Request $request, User $user): JsonResponse
    {
        if ($user->role === \App\Enums\UserRole::Admin->value) {
            throw new \App\Exceptions\ForbiddenException('Không thể khóa tài khoản quản trị viên.');
        }

        $isLocking = (bool) $user->is_active;

        $request->validate([
            'reason' => $isLocking
                ? ['required', 'string', 'min:3', 'max:500']
                : ['nullable', 'string', 'max:500'],
        ], [
            'reason.required' => 'Vui lòng cung cấp lý do khi khóa tài khoản người dùng.',
            'reason.min' => 'Lý do khóa tài khoản tối thiểu 3 ký tự.',
        ]);

        $updated = $this->service->toggleUser($user, $request->input('reason'), $request->user());

        return response()->json([
            'message' => 'Đã cập nhật trạng thái tài khoản.',
            'data' => new UserResource($updated),
        ]);
    }


    public function detail(User $user): JsonResponse
    {
        $detail = $this->service->userDetail($user);

        return response()->json([
            'data' => [
                'user' => new UserResource($detail['user']),
                'stats' => $detail['stats'],
                'recent_bookings' => \App\Http\Resources\Api\BookingResource::collection($detail['recent_bookings']),
                'recent_trust_scores' => TrustScoreLogResource::collection($detail['recent_trust_scores']),
            ],
        ]);
    }

    public function trustScoreLogs(User $user): AnonymousResourceCollection
    {
        return TrustScoreLogResource::collection($this->service->userTrustScoreLogs($user));
    }
}
