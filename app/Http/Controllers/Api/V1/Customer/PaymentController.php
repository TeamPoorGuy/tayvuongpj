<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Chỉ khi chạy local, để demo không cần tiền thật: đóng vai ngân hàng báo "đã có tiền vào tài khoản nền tảng"
     * bằng đúng webhook thật, nên đơn/ví/mail đi qua cùng đường code như khi ngân hàng gọi. `amount` tuỳ chọn để thử chuyển thiếu/thừa.
     */
    public function simulateTransfer(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(app()->environment('local') && $payment->user_id === $request->user()->id, 404);
        $amount = $request->validate(['amount' => ['nullable', 'integer', 'min:1', 'max:1000000000']])['amount'] ?? (int) $payment->dueAmount();
        if ($payment->status !== 'awaiting') {
            throw new ConflictException('Lượt thanh toán này không còn chờ chuyển khoản.');
        }

        $this->payments->handleBankWebhook([
            'transferType' => 'in',
            'transferAmount' => $amount,
            'content' => "Mo phong chuyen khoan {$payment->code}",
            'referenceCode' => 'SIM-'.Str::upper(Str::random(10)),
        ]);

        return response()->json([
            'message' => 'Đã mô phỏng ngân hàng báo có tiền.',
            'data' => new PaymentResource($payment->refresh()->load(['bookings' => fn ($q) => $q->with(Booking::resourceRelations())])),
        ]);
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
