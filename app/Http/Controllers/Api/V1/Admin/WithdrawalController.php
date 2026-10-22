<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\WithdrawalResource;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    /** Lệnh rút cần admin chuyển khoản (mặc định chỉ lệnh đang chờ), kèm mã QR tới tài khoản người rút. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['status' => ['nullable', 'in:pending,paid,rejected']]);

        return WithdrawalResource::collection(Withdrawal::with('user')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20));
    }

    public function paid(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $reference = $request->validate(['reference' => ['nullable', 'string', 'max:100']])['reference'] ?? null;
        $withdrawal = $this->withdrawals->markPaid($withdrawal, $request->user(), $reference);

        return response()->json(['message' => 'Đã ghi nhận chuyển khoản cho lệnh rút.', 'data' => new WithdrawalResource($withdrawal->load('user'))]);
    }

    public function reject(Request $request, Withdrawal $withdrawal): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.required' => 'Vui lòng ghi lý do từ chối.'])['reason'];
        $withdrawal = $this->withdrawals->reject($withdrawal, $request->user(), $reason);

        return response()->json(['message' => 'Đã từ chối lệnh rút và trả tiền về ví.', 'data' => new WithdrawalResource($withdrawal->load('user'))]);
    }
}
