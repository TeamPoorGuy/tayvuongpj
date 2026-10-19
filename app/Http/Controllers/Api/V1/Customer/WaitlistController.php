<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Resources\Api\BookingWaitlistResource;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function __construct(protected WaitlistService $service) {}

    public function index(Request $request): JsonResponse
    {
        $waitlists = $request->user()->bookingWaitlists()
            ->with(['sportsField.primaryImage', 'timeSlot'])
            ->latest()
            ->get();

        return response()->json(['data' => BookingWaitlistResource::collection($waitlists)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $waitlist = $this->service->join(
            $request->user(),
            (int) $validated['sports_field_id'],
            (int) $validated['time_slot_id'],
            $validated['booking_date']
        );

        return response()->json([
            'message' => 'Đã tham gia hàng đợi thông minh thành công. Chúng tôi sẽ thông báo ngay khi có chỗ!',
            'data' => new BookingWaitlistResource($waitlist),
        ], 201);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $booking = $this->service->acceptOffer($request->user(), $id);

        return response()->json([
            'message' => 'Đã nhận suất sân! Đơn đặt sân đang chờ chủ sân duyệt, sau đó bạn chuyển khoản cọc hoặc trả hết để giữ sân.',
            'data' => new BookingResource($booking),
        ], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $waitlist = $this->service->cancel($request->user(), $id);

        return response()->json([
            'message' => 'Đã huỷ hàng đợi.',
            'data' => new BookingWaitlistResource($waitlist),
        ]);
    }
}
