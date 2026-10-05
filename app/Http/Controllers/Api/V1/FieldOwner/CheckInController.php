<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function __construct(protected CheckInService $service) {}

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
