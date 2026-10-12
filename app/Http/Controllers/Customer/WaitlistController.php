<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\BookingWaitlist;
use App\Services\WaitlistService;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $waitlists = BookingWaitlist::where('user_id', $request->user()->id)
            ->with(['sportsField.primaryImage', 'timeSlot'])
            ->latest()
            ->paginate(10);

        return view('customer.waitlists.index', compact('waitlists'));
    }

    public function store(Request $request, WaitlistService $service)
    {
        $data = $request->validate([
            'sports_field_id' => ['required', 'integer', 'exists:sports_fields,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $service->join($request->user(), $data['sports_field_id'], $data['time_slot_id'], $data['booking_date']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã đăng ký vào hàng đợi thành công! Hệ thống sẽ thông báo khi có chỗ trống.');
    }

    public function accept(Request $request, BookingWaitlist $waitlist, WaitlistService $service)
    {
        abort_unless($waitlist->user_id === $request->user()->id, 403);

        $result = $service->acceptOffer($request->user(), $waitlist->id);

        if (is_string($result)) {
            return back()->with('error', $result);
        }

        return redirect()->route('customer.bookings.index')
            ->with('success', 'Đã xác nhận nhận sân thành công! Vui lòng chờ chủ sân duyệt đơn.');
    }

    public function cancel(Request $request, BookingWaitlist $waitlist, WaitlistService $service)
    {
        abort_unless($waitlist->user_id === $request->user()->id, 403);

        $service->cancel($request->user(), $waitlist->id);

        return back()->with('success', 'Đã hủy đăng ký hàng đợi.');
    }
}
