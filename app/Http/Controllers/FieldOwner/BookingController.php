<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
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

    public function updateStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Booking::STATUS_PENDING,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_COMPLETED,
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
            ])],
        ]);

        if ($booking->sportsField->field_owner_id !== $request->user()->id) {
            return back()->with('error', 'Bạn không có quyền cập nhật đơn đặt sân này.');
        }

        if (! $booking->canTransitionTo($data['status'])) {
            return back()->with('error', 'Chuyển trạng thái đơn không hợp lệ.');
        }

        $booking->update(['status' => $data['status']]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn đặt sân.');
    }
}
