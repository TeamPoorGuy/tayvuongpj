<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\DTOs\Booking\CreateBookingData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CancelBookingRequest;
use App\Http\Requests\Api\Booking\CreateBookingRequest;
use App\Http\Resources\Api\BookingResource;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Booking;
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
        $payment = $this->bookingService->create($request->user(), CreateBookingData::fromRequest($request));
        $payment->load(['bookings' => fn ($q) => $q->with(Booking::resourceRelations())]);

        return response()->json([
            'message' => 'Đã giữ chỗ. Vui lòng chuyển khoản trước '.$payment->due_at->format('H:i').' để hoàn tất đặt sân.',
            'data' => new BookingResource($payment->bookings->first()),
            'payment' => new PaymentResource($payment),
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        return response()->json(['data' => new BookingResource(
            $booking->load([...Booking::resourceRelations(), 'sportsField.owner.fieldOwnerProfile'])
        )]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookingService->cancel($request->user(), $booking, $request->input('cancel_reason'));

        return response()->json(['message' => 'Đã hủy đơn đặt sân.', 'data' => new BookingResource($booking)]);
    }

    public function storeRecurring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'weeks' => ['required', 'integer', 'min:2', 'max:8'],
            'skip_conflicts' => ['nullable', 'boolean'],
            'payment_option' => ['sometimes', 'in:deposit,full'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'sports_field_id.required' => 'Vui lòng chọn sân.',
            'time_slot_id.required' => 'Vui lòng chọn khung giờ.',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
            'weeks.min' => 'Đặt cố định tối thiểu từ 2 tuần.',
            'weeks.max' => 'Đặt cố định tối đa 8 tuần.',
        ]);

        $result = $this->bookingService->createRecurring($request->user(), $validated);

        if (! empty($result['has_conflicts'])) {
            return response()->json($result, 409);
        }

        return response()->json([
            'message' => 'Đã giữ chỗ '.$result['total_bookings'].' buổi cố định. Vui lòng thanh toán một lần cho cả chuỗi.',
            'data' => [
                'recurring_group_id' => $result['recurring_group_id'],
                'payment' => new PaymentResource($result['payment']),
                'total_bookings' => $result['total_bookings'],
                'bookings' => BookingResource::collection($result['bookings']),
                'skipped_dates' => $result['skipped_dates'],
            ],
        ], 201);
    }

    public function cancelRecurring(Request $request, string $groupId): JsonResponse
    {
        $reason = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:500']])['cancel_reason'] ?? null;
        $cancelled = $this->bookingService->cancelRecurring($request->user(), $groupId, $reason);

        return response()->json([
            'message' => 'Đã huỷ các buổi sắp tới trong chuỗi đặt cố định.',
            'data' => BookingResource::collection($cancelled),
        ]);
    }
}
