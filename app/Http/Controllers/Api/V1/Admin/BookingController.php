<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return BookingResource::collection($this->service->bookings(
            $request->input('status'),
            $request->input('keyword'),
            $request->input('date'),
        ));
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Vui lòng cung cấp lý do huỷ đơn.',
        ]);

        $updated = $this->service->cancelBookingAsAdmin(
            $booking,
            $request->input('reason'),
            $request->user()
        );

        return response()->json([
            'message' => 'Đã huỷ đơn đặt sân thành công.',
            'data' => new BookingResource($updated),
        ]);
    }

    public function updateRefund(Request $request, Booking $booking): JsonResponse
    {
        $request->validate([
            'refund_status' => ['required', 'in:pending,done,waived'],
            'refund_amount' => ['nullable', 'integer', 'min:0', 'max:'.(int) ($booking->payment_amount ?? $booking->total_price)],
        ], [
            'refund_status.required' => 'Vui lòng chọn trạng thái hoàn tiền.',
            'refund_status.in' => 'Trạng thái hoàn tiền không hợp lệ.',
        ]);

        $updated = $this->service->updateBookingRefund(
            $booking,
            $request->input('refund_status'),
            $request->has('refund_amount') ? (float) $request->input('refund_amount') : null,
            $request->user()
        );

        return response()->json([
            'message' => 'Cập nhật trạng thái hoàn tiền thành công.',
            'data' => new BookingResource($updated),
        ]);
    }

    public function exportCsv(Request $request): Response
    {
        $csv = $this->service->exportBookingsCsv(
            $request->input('status'),
            $request->input('keyword'),
            $request->input('date')
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="danh-sach-don-'.now()->format('Ymd_His').'.csv"',
        ]);
    }
}
