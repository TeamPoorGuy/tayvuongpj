<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\UpdateBookingStatusRequest;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
use App\Services\BookingPaymentService;
use App\Services\FieldOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly FieldOwnerService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'field_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected'],
            'payment_status' => ['nullable', 'in:awaiting,reported,paid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return BookingResource::collection($this->service->bookings($request->user(), $filters));
    }

    public function approve(Request $request, Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        return response()->json([
            'message' => 'Đã duyệt đơn. Khách sẽ chuyển khoản theo thông tin tài khoản của bạn.',
            'data' => new BookingResource($payments->approve($request->user(), $booking)),
        ]);
    }

    public function payment(Request $request, Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        $received = $request->validate(['received' => ['required', 'boolean']])['received'];

        return response()->json([
            'message' => $received ? 'Đã xác nhận nhận tiền. Đơn đặt sân đã được xác nhận.' : 'Đã báo chưa nhận được tiền. Khách sẽ được yêu cầu kiểm tra lại.',
            'data' => new BookingResource($payments->resolve($request->user(), $booking, (bool) $received)),
        ]);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->service->updateBookingStatus($request->user(), $booking, $request->string('status')->toString(), $request->input('reason'));

        return response()->json(['message' => 'Đã cập nhật trạng thái đơn.', 'data' => new BookingResource($booking)]);
    }

    public function noShow(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->service->recordNoShow($request->user(), $booking);

        return response()->json([
            'message' => 'Đã ghi nhận khách vắng mặt và trừ điểm uy tín.',
            'data' => new BookingResource($booking),
        ]);
    }

    public function approveRecurring(Request $request, string $groupId, BookingPaymentService $payments): JsonResponse
    {
        $approved = $payments->approveRecurring($request->user(), $groupId);

        return response()->json([
            'message' => 'Đã duyệt toàn bộ chuỗi đặt sân cố định ('.$approved->count().' buổi).',
            'data' => BookingResource::collection($approved),
        ]);
    }

    public function rejectRecurring(Request $request, string $groupId, BookingPaymentService $payments): JsonResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $rejected = $payments->rejectRecurring($request->user(), $groupId, $reason);

        return response()->json([
            'message' => 'Đã từ chối toàn bộ chuỗi đặt sân cố định.',
            'data' => BookingResource::collection($rejected),
        ]);
    }
}
