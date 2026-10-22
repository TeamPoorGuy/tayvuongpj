<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentResource;
use App\Http\Resources\Api\WithdrawalResource;
use App\Services\EarningService;
use App\Services\PaymentService;
use App\Services\WithdrawalService;
use App\Support\Banks;
use App\Support\InputRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ví SportHub của người đang đăng nhập (khách hoặc chủ sân): số dư, lịch sử, nạp tiền. */
class WalletController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly WithdrawalService $withdrawals,
        private readonly EarningService $earnings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $user->walletTransactions()->latest('id')->paginate(15);

        return response()->json([
            'data' => [
                'balance' => (float) $user->wallet_balance,
                // Chủ sân: tiền các đơn đã thanh toán nhưng chưa tới hạn cộng vào ví (null với khách thường).
                'pending_earnings' => $user->isApprovedOwner() ? $this->earnings->pendingFor($user) : null,
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

    /** Lệnh rút của chính mình (mới nhất trước), kèm tài khoản ngân hàng đã lưu để điền sẵn. */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = $request->user();
        $owner = $user->isApprovedOwner();
        $profile = $owner ? $user->fieldOwnerProfile : null;
        $bin = $owner ? ($profile->bank_bin ?? Banks::guessBin($profile->bank_name)) : $user->bank_bin;
        $bank = $bin ? [
            'bank_bin' => $bin,
            'account_number' => $owner ? $profile->bank_account_number : $user->bank_account_number,
            'account_holder' => $owner ? $profile->bank_account_holder : $user->bank_account_holder,
        ] : null;

        return response()->json([
            'data' => WithdrawalResource::collection($user->withdrawals()->latest('id')->limit(20)->get()),
            'saved_bank' => $bank,
            // Chủ sân luôn rút về tài khoản trong hồ sơ cơ sở, không nhập lại.
            'bank_locked' => $owner,
            'auto_limit' => (int) \App\Support\Settings::getInt('withdraw_auto_limit', 2000000),
            'min_amount' => WithdrawalService::MIN_AMOUNT,
        ]);
    }

    /** Rút ví về ngân hàng. Chủ sân dùng tài khoản trong hồ sơ cơ sở; khách nhập ngân hàng, số tài khoản, tên chủ tài khoản. */
    public function withdraw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => InputRules::money(WithdrawalService::MIN_AMOUNT),
            'bank_bin' => ['nullable', 'string', 'in:'.implode(',', Banks::bins())],
            'account_number' => ['nullable', InputRules::BANK_ACCOUNT],
            'account_holder' => ['nullable', 'string', 'max:50', InputRules::BANK_HOLDER],
        ], [
            'amount.min' => 'Số tiền rút tối thiểu 50.000đ.',
            'bank_bin.in' => 'Ngân hàng không hợp lệ.',
            'account_number.regex' => 'Số tài khoản chỉ gồm chữ số (6–20 số).',
            'account_holder.regex' => 'Tên chủ tài khoản viết in hoa, không dấu.',
        ]);

        $withdrawal = $this->withdrawals->request($request->user(), (float) $data['amount'], $data);

        return response()->json([
            'message' => $withdrawal->status === 'paid'
                ? 'Đã chuyển tiền về tài khoản của bạn.'
                : 'Đã gửi lệnh rút, chờ Ban quản trị chuyển khoản. Tiền đã được giữ khỏi ví.',
            'data' => new WithdrawalResource($withdrawal),
        ], 201);
    }
}
