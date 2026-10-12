<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with(Booking::displayRelations())
            ->latest()
            ->paginate(10);

        return view('customer.bookings.index', compact('bookings'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($request, $data) {
            $field = SportsField::approved()
                ->lockForUpdate()
                ->find($data['sports_field_id']);

            if (! $field) {
                return ['error' => 'Sân chưa được duyệt hoặc đang dừng hoạt động.'];
            }

            // Ràng buộc slot phải thuộc đúng sân đang đặt.
            $slot = TimeSlot::query()
                ->where('sports_field_id', $field->id)
                ->where('is_active', true)
                ->findOrFail($data['time_slot_id']);

            $isBooked = Booking::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data['booking_date'])
                ->whereIn('status', Booking::BLOCKING_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($isBooked) {
                return ['error' => 'Khung giờ này đã có người đặt. Vui lòng chọn giờ khác.'];
            }

            Booking::create([
                'user_id' => $request->user()->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $data['booking_date'],
                'status' => Booking::STATUS_PENDING,
                'total_price' => $field->price_per_hour * $slot->durationInHours(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['error' => null];
        });

        if ($result['error']) {
            return back()->withInput()->with('error', $result['error']);
        }

        return redirect()->route('customer.bookings.index')
            ->with('success', 'Yêu cầu đặt sân đã được gửi thành công!');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($booking->user_id === $request->user()->id, 403);

        if (! $booking->canBeCancelled()) {
            return back()->with('error', 'Đơn đặt sân này không còn đủ điều kiện để hủy.');
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? 'Khách hàng tự hủy đơn.',
        ]);

        return back()->with('success', 'Đã hủy đơn đặt sân thành công.');
    }
}
