<?php

namespace App\Http\Controllers\Api\V1\Admin;

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

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $this->statementService->exportStatementsCsv(
            $request->input('period'),
            $request->input('status'),
            $request->input('keyword')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="doi-soat-'.now()->format('Ymd_His').'.csv"',
        ]);
    }

    public function exportBookingsCsv(OwnerStatement $statement): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $this->statementService->exportStatementBookingsCsv($statement);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="chi-tiet-doi-soat-'.$statement->period.'-'.$statement->id.'.csv"',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));

        $query = OwnerStatement::query()
            ->with(['ownerProfile.user', 'confirmedBy'])
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->input('period')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('owner_profile_id'), fn ($q) => $q->where('owner_profile_id', $request->input('owner_profile_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $kw = $request->input('keyword');
                $q->where(function ($sub) use ($kw) {
                    $sub->where('transfer_code', 'like', "%{$kw}%")
                        ->orWhereHas('ownerProfile', fn ($op) => $op->where('business_name', 'like', "%{$kw}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$kw}%")
                                ->orWhere('email', 'like', "%{$kw}%")
                                ->orWhere('phone', 'like', "%{$kw}%")));
                });
            })
            ->orderByDesc('period')
            ->orderByDesc('id');

        $statements = $query->paginate($perPage);

        return response()->json([
            'data' => OwnerStatementResource::collection($statements),
            'meta' => [
                'current_page' => $statements->currentPage(),
                'last_page' => $statements->lastPage(),
                'per_page' => $statements->perPage(),
                'total' => $statements->total(),
                'totals' => $this->statementService->totals(null),
            ],
        ]);
    }

    public function show(OwnerStatement $statement): JsonResponse
    {
        $statement->load([
            'ownerProfile.user',
            'confirmedBy',
            'bookings' => fn ($q) => $q->with(Booking::resourceRelations(withCustomer: true))->latest(),
        ]);

        return response()->json([
            'data' => new OwnerStatementResource($statement),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'owner_profile_id' => ['nullable', 'integer', 'exists:field_owner_profiles,id'],
        ]);

        $period = $request->input('period');
        $profileId = $request->filled('owner_profile_id') ? (int) $request->input('owner_profile_id') : null;

        $created = $this->statementService->generate($period, $profileId);

        return response()->json([
            'message' => 'Đã tạo/cập nhật bảng đối soát cho '.count($created).' chủ sân.',
            'data' => OwnerStatementResource::collection($created),
        ]);
    }

    public function confirm(Request $request, OwnerStatement $statement): JsonResponse
    {
        $confirmed = $this->statementService->confirm($request->user(), $statement);

        return response()->json([
            'message' => 'Xác nhận thu phí hoa hồng thành công.',
            'data' => new OwnerStatementResource($confirmed),
        ]);
    }

    public function waive(Request $request, OwnerStatement $statement): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $waived = $this->statementService->waive($request->user(), $statement, $request->input('reason'));

        return response()->json([
            'message' => 'Đã miễn thu phí đối soát cho chủ sân.',
            'data' => new OwnerStatementResource($waived),
        ]);
    }

    public function rejectReport(Request $request, OwnerStatement $statement): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->statementService->rejectReport($request->user(), $statement, $request->input('reason'));

        return response()->json([
            'message' => 'Đã từ chối báo cáo thanh toán và chuyển kỳ đối soát về trạng thái chờ nộp.',
            'data' => new OwnerStatementResource($updated),
        ]);
    }
}
