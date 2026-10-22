<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        return response()->json(['data' => new PaymentResource($payment->load(['bookings' => fn ($q) => $q->with(Booking::resourceRelations())]))]);
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $option = $request->validate(['option' => ['required', 'in:deposit,full']])['option'];
        $payment = $this->payments->changeOption($request->user(), $payment, $option);

        return response()->json(['message' => 'Đã cập nhật số tiền cần thanh toán.', 'data' => new PaymentResource($payment)]);
    }

    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        $payment = $this->payments->cancelAwaiting($request->user(), $payment);

        return response()->json(['message' => 'Đã huỷ giữ chỗ.', 'data' => new PaymentResource($payment)]);
    }

    /** Trả (nốt) bằng số dư ví cho lượt đang chờ, ví dụ sau khi nạp thêm hoặc khi tiền chuyển thiếu đã vào ví. */
    public function useWallet(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $payment = DB::transaction(fn () => $this->payments->applyWallet($payment));

        return response()->json([
            'message' => $payment->status === 'paid' ? 'Đã thanh toán bằng ví, đơn đặt sân được xác nhận.' : 'Đã trừ số dư ví, còn phần cần chuyển khoản.',
            'data' => new PaymentResource($payment->load(['bookings' => fn ($q) => $q->with(Booking::resourceRelations())])),
        ]);
    }
}
