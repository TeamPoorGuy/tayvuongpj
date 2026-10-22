<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentResource;
use App\Services\PaymentService;
use App\Support\InputRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ví SportHub của người đang đăng nhập (khách hoặc chủ sân): số dư, lịch sử, nạp tiền. */
class WalletController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $user->walletTransactions()->latest('id')->paginate(15);

        return response()->json([
            'data' => [
                'balance' => (float) $user->wallet_balance,
                'transactions' => $transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'description' => $t->description,
                    'booking_id' => $t->booking_id,
                    'created_at' => $t->created_at?->toISOString(),
                ])->values(),
            ],
            'meta' => ['current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'total' => $transactions->total()],
        ]);
    }

    /** Nạp ví: tạo lượt chờ chuyển khoản vào tài khoản nền tảng; tiền về qua webhook thì tự cộng vào ví. */
    public function topup(Request $request): JsonResponse
    {
        $amount = $request->validate(['amount' => InputRules::money(10000)], [
            'amount.min' => 'Số tiền nạp tối thiểu 10.000đ.',
            'amount.max' => 'Số tiền nạp tối đa 100.000.000đ.',
        ])['amount'];

        $payment = $this->payments->createTopup($request->user(), (int) $amount);

        return response()->json(['message' => 'Đã tạo lệnh nạp ví. Quét mã QR để chuyển khoản.', 'data' => new PaymentResource($payment)], 201);
    }
}
