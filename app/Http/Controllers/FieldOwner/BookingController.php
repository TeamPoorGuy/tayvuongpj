<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Mail\BookingStatusChanged;
use App\Models\Booking;
use App\Models\SportsField;
use App\Services\CheckInService;
use App\Services\TrustScoreService;
use App\Services\WaitlistService;
use App\Support\SafeMail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'field_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
            ])],
            'date' => ['nullable', 'date'],
        ]);

        $fieldIds = SportsField::where('field_owner_id', $request->user()->id)->pluck('id');
        $bookings = Booking::whereIn('sports_field_id', $fieldIds)
            ->with(Booking::displayRelations(withCustomer: true))
            ->when($request->input('field_id'), fn ($query, $id) => $query->where('sports_field_id', $id))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('date'), fn ($query, $date) => $query->whereDate('booking_date', $date))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $fields = $request->user()->ownedFields()->get();

        return view('field-owner.bookings.index', compact('bookings', 'fields'));
    }

    public function updateStatus(Request $request, Booking $booking, WaitlistService $waitlistService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
            ])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($booking->sportsField->field_owner_id !== $request->user()->id) {
            return back()->with('error', 'Bạn không có quyền cập nhật đơn đặt sân này.');
        }

        if (! $booking->canTransitionTo($data['status'])) {
            return back()->with('error', 'Chuyển trạng thái đơn không hợp lệ.');
        }

        $updateData = ['status' => $data['status']];

        if ($data['status'] === Booking::STATUS_CONFIRMED) {
            $updateData['confirmed_at'] = now();
        }

        if (in_array($data['status'], [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true)) {
            $updateData['cancelled_at'] = now();
            if (! empty($data['reason'])) {
                $updateData['cancel_reason'] = $data['reason'];
            }
        }

        $booking->update($updateData);

        // Kích hoạt giải phóng slot cho người trong hàng đợi
        if (in_array($data['status'], [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true)) {
            $waitlistService->handleSlotFreed(
                $booking->sports_field_id,
                $booking->time_slot_id,
                $booking->booking_date->toDateString()
            );
        }

        $booking->load(['customer', 'sportsField', 'timeSlot']);
        if ($booking->customer?->email) {
            SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
        }

        return back()->with('success', 'Đã cập nhật trạng thái đơn đặt sân.');
    }

    public function checkIn(Request $request, CheckInService $checkInService)
    {
        $data = $request->validate([
            'check_in_code' => ['nullable', 'string', 'max:50'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        try {
            if (! empty($data['check_in_code'])) {
                $booking = $checkInService->checkIn($request->user(), $data['check_in_code']);
            } elseif (! empty($data['booking_id'])) {
                $targetBooking = Booking::findOrFail($data['booking_id']);
                $booking = $checkInService->checkInBooking($request->user(), $targetBooking);
            } else {
                return back()->with('error', 'Vui lòng cung cấp mã check-in hoặc chọn đơn đặt.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Check-in thành công cho khách {$booking->customer?->name}! (+10 điểm uy tín đã được cộng)");
    }
}
