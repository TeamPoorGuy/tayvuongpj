<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $fieldIds = SportsField::where('field_owner_id', Auth::id())->pluck('id');

        $query = Booking::whereIn('sports_field_id', $fieldIds)
            ->with(['sportsField', 'customer', 'timeSlot']);

        // Lọc theo sân
        if ($request->filled('field_id')) {
            $query->where('sports_field_id', $request->field_id);
        }

        // Lọc theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Lọc theo ngày
        if ($request->filled('date')) {
            $query->where('booking_date', $request->date);
        }

        $bookings = $query->latest()->paginate(10)->withQueryString();
        $fields = SportsField::where('field_owner_id', Auth::id())->get();

        return view('field-owner.bookings.index', compact('bookings', 'fields'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:confirmed,completed,rejected,cancelled'],
        ]);

        $fieldIds = SportsField::where('field_owner_id', Auth::id())->pluck('id');
        $booking = Booking::whereIn('sports_field_id', $fieldIds)->where('id', $id)->firstOrFail();

        $booking->update([
            'status' => $request->status,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn đặt sân thành công.');
    }
}
