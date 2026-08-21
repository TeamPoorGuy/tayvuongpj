<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index()
    {
        $bookings = Booking::where('user_id', Auth::id())
            ->with(['sportsField.primaryImage', 'timeSlot', 'review'])
            ->latest()
            ->paginate(10);

        return view('customer.bookings.index', compact('bookings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'sports_field_id' => ['required', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'booking_date.required' => 'Không được bỏ trống ngày đặt.',
            'booking_date.after_or_equal' => 'Không được chọn ngày trong quá khứ.',
            'time_slot_id.required' => 'Bắt buộc chọn khung giờ.',
            'sports_field_id.required' => 'Bắt buộc chọn sân.',
        ]);

        $field = SportsField::findOrFail($request->sports_field_id);

        // Kiểm tra sân có đang hoạt động và được duyệt không
        if ($field->status !== 'approved' || !$field->is_active) {
            return back()->with('error', 'Sân thể thao này hiện chưa được duyệt hoặc đang dừng hoạt động.')->withInput();
        }

        // Kiểm tra trùng lặp lịch đặt sân
        $existingBooking = Booking::where('sports_field_id', $field->id)
            ->where('time_slot_id', $request->time_slot_id)
            ->where('booking_date', $request->booking_date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($existingBooking) {
            return back()->with('error', 'Khung giờ này đã có người đặt trên hệ thống. Vui lòng chọn khung giờ khác.')->withInput();
        }

        $timeSlot = TimeSlot::findOrFail($request->time_slot_id);
        
        // Tính tổng tiền dựa trên giá theo giờ (ví dụ slot 1.5 tiếng)
        $totalPrice = $field->price_per_hour * 1.5;

        $booking = Booking::create([
            'user_id' => Auth::id(),
            'sports_field_id' => $field->id,
            'time_slot_id' => $timeSlot->id,
            'booking_date' => $request->booking_date,
            'status' => 'pending',
            'total_price' => $totalPrice,
            'notes' => $request->notes,
        ]);

        return redirect()->route('customer.bookings.index')->with('success', 'Yêu cầu đặt sân đã được gửi thành công! Vui lòng chờ chủ sân xác nhận.');
    }

    public function cancel(Request $request, $id)
    {
        $booking = Booking::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        // Kiểm tra khách hàng chỉ được hủy đơn của mình và đáp ứng điều kiện
        if (!$booking->canBeCancelled()) {
            return back()->with('error', 'Đơn đặt sân này không thỏa mãn điều kiện để hủy (chỉ hủy được đơn chờ/đã xác nhận trước ngày đá).');
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $request->cancel_reason ?? 'Khách hàng tự hủy đơn.',
        ]);

        return back()->with('success', 'Đã hủy đơn đặt sân thành công.');
    }
}
