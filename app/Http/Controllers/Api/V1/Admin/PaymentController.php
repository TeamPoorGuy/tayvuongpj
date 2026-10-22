<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Giao dịch cần đối chiếu: đang chờ chuyển khoản hoặc tiền về muộn cần hoàn. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:awaiting,paid,expired,cancelled,refunded'],
            'keyword' => ['nullable', 'string', 'max:50'],
        ]);

        return PaymentResource::collection(Payment::query()
            ->with(['user', 'bookings' => fn ($q) => $q->with(Booking::resourceRelations())])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['keyword'] ?? null, fn ($q, $kw) => $q->where('code', 'like', '%'.addcslashes($kw, '%_\\').'%'))
            ->latest()
            ->paginate(20));
    }

    /** Admin đã thấy tiền trong sao kê nhưng webhook không bắt được (ghi sai nội dung…). */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $bankRef = $request->validate(['bank_ref' => ['nullable', 'string', 'max:100', 'unique:payments,bank_ref']])['bank_ref'] ?? null;
        if (! in_array($payment->status, ['awaiting', 'expired', 'cancelled'], true)) {
            throw new ConflictException('Giao dịch này đã được ghi nhận trước đó.');
        }

        $payment = $this->payments->markPaid($payment, 'admin', $bankRef, $request->user());

        return response()->json([
            'message' => $payment->status === 'paid' ? 'Đã ghi nhận tiền và xác nhận đơn.' : 'Đã ghi nhận tiền; chỗ đã bị người khác đặt nên cần hoàn tiền cho khách.',
            'data' => new PaymentResource($payment->load('bookings')),
        ]);
    }
}
