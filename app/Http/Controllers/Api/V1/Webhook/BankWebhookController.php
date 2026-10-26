<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ngân hàng / SePay báo có giao dịch vào tài khoản nền tảng. */
class BankWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $expected = 'Apikey '.config('services.bank_webhook.key');
        abort_unless(hash_equals($expected, (string) $request->header('Authorization')), 401, 'Sai khoá webhook.');

        $payload = $request->validate([
            'id' => ['nullable'],
            'transferType' => ['nullable', 'string'],
            'transferAmount' => ['required', 'numeric', 'min:0'],
            'content' => ['nullable', 'string', 'max:500'],
            'referenceCode' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $payments->handleBankWebhook($payload);

        // Luôn trả 200 để ngân hàng không gửi lại; giao dịch không khớp sẽ được admin đối chiếu tay.
        return response()->json(['success' => true, 'matched' => $payment?->code]);
    }
}
