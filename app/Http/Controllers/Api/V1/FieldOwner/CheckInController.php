<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function __construct(protected CheckInService $service) {}

    public function checkInBooking(Request $request, Booking $booking): JsonResponse
    {
        $booking = $this->service->checkInBooking($request->user(), $booking);

        return response()->json([
            'message' => "Đã xác nhận khách {$booking->customer->name} đến sân. Đã cộng +10 điểm uy tín.",
            'data' => new BookingResource($booking->load(Booking::resourceRelations(withCustomer: true))),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $booking = $this->service->checkIn($request->user(), $validated['code']);

        return response()->json([
            'message' => "Check-in thành công cho khách hàng {$booking->customer->name}! Đã cộng +10 điểm uy tín.",
            'data' => new BookingResource($booking),
        ]);
    }
}
