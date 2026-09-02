<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\UpdateBookingStatusRequest;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
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
            'status' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        return BookingResource::collection($this->service->bookings($request->user(), $filters));
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->service->updateBookingStatus($request->user(), $booking, $request->string('status')->toString());

        return response()->json(['message' => 'Đã cập nhật trạng thái đơn.', 'data' => new BookingResource($booking)]);
    }
}
