<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\DTOs\Booking\CreateBookingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CancelBookingRequest;
use App\Http\Requests\Api\Booking\CreateBookingRequest;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
use App\Services\BookingPaymentService;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected']]);

        return BookingResource::collection($this->bookingService->customerBookings($request->user(), $request->input('status')));
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->user(), CreateBookingData::fromRequest($request));

        return response()->json(['message' => 'Yêu cầu đặt sân đã được gửi.', 'data' => new BookingResource($booking)], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        return response()->json(['data' => new BookingResource(
            $booking->load([...Booking::resourceRelations(), 'sportsField.owner.fieldOwnerProfile'])
        )]);
    }

    public function reportPayment(Request $request, Booking $booking, BookingPaymentService $payments): JsonResponse
    {
        $validated = $request->validate([
            'option' => ['required', 'in:deposit,full'],
            'proof' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        return response()->json([
            'message' => 'Đã ghi nhận. Chủ sân sẽ đối chiếu và xác nhận đã nhận tiền.',
            'data' => new BookingResource($payments->report(
                $request->user(),
                $booking,
                $validated['option'],
                $request->file('proof')
            )),
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->cancel($request->user(), $booking, $request->input('cancel_reason'));

        return response()->json(['message' => 'Đã hủy đơn đặt sân.', 'data' => new BookingResource($booking)]);
    }
}
