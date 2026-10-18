<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\OwnerStatementResource;
use App\Models\Booking;
use App\Models\OwnerStatement;
use App\Services\StatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    public function __construct(
        private readonly StatementService $statementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->fieldOwnerProfile;
        if (! $profile) {
            return response()->json(['data' => []]);
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 12)));

        $statements = OwnerStatement::where('owner_profile_id', $profile->id)
            ->with(['confirmedBy'])
            ->orderByDesc('period')
            ->paginate($perPage);

        return response()->json([
            'data' => OwnerStatementResource::collection($statements),
            'meta' => [
                'current_page' => $statements->currentPage(),
                'last_page' => $statements->lastPage(),
                'per_page' => $statements->perPage(),
                'total' => $statements->total(),
            ],
        ]);
    }

    public function currentSummary(Request $request): JsonResponse
    {
        $summary = $this->statementService->currentMonthSummary($request->user());

        return response()->json(['data' => $summary]);
    }

    public function show(Request $request, OwnerStatement $statement): JsonResponse
    {
        $profile = $request->user()->fieldOwnerProfile;
        abort_unless($profile && $statement->owner_profile_id === $profile->id, 403, 'Bạn không có quyền xem kỳ đối soát này.');

        $statement->load([
            'confirmedBy',
            'bookings' => fn ($q) => $q->with(Booking::resourceRelations(withCustomer: true))->latest(),
        ]);

        return response()->json([
            'data' => new OwnerStatementResource($statement),
        ]);
    }

    public function report(Request $request, OwnerStatement $statement): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->statementService->report($request->user(), $statement, $request->input('note'));

        return response()->json([
            'message' => 'Đã gửi thông báo thanh toán thành công. Ban quản trị sẽ đối soát và xác nhận.',
            'data' => new OwnerStatementResource($updated),
        ]);
    }
}
