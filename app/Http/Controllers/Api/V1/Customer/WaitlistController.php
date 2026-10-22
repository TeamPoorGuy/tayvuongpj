<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Http\Resources\Api\BookingWaitlistResource;
use App\Services\WaitlistService;
use App\Support\InputRules;
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
            // Một khung giờ có sẵn, hoặc một khoảng giờ trên lưới 30 phút.
            'time_slot_id' => ['required_without:start_time', 'nullable', 'integer', 'exists:time_slots,id'],
            'start_time' => ['required_without:time_slot_id', 'nullable', 'date_format:H:i', InputRules::HALF_HOUR],
            'end_time' => ['required_with:start_time', 'nullable', 'date_format:H:i', 'after:start_time', InputRules::HALF_HOUR],
            'booking_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $waitlist = $this->service->join(
            $request->user(),
            (int) $validated['sports_field_id'],
            isset($validated['time_slot_id']) ? (int) $validated['time_slot_id'] : null,
            $validated['booking_date'],
            $validated['start_time'] ?? null,
            $validated['end_time'] ?? null,
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
            'message' => 'Đã nhận suất sân! Chỗ được giữ tạm, hãy chuyển khoản cọc hoặc trả hết để hoàn tất đặt sân.',
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
