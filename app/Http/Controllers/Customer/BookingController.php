<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Mail\BookingStatusChanged;
use App\Mail\NewBookingRequest;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Services\TrustScoreService;
use App\Services\WaitlistService;
use App\Support\SafeMail;
use Carbon\Carbon;
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

        $user = $request->user();

        $result = DB::transaction(function () use ($user, $data) {
            $field = SportsField::approved()
                ->lockForUpdate()
                ->find($data['sports_field_id']);

            if (! $field) {
                return ['error' => 'Sân chưa được duyệt hoặc đang dừng hoạt động.'];
            }

            if ($field->field_owner_id === $user->id) {
                return ['error' => 'Bạn không thể đặt sân của chính mình.'];
            }

            $slot = TimeSlot::query()
                ->where('sports_field_id', $field->id)
                ->where('is_active', true)
                ->findOrFail($data['time_slot_id']);

            if (Carbon::parse($data['booking_date'].' '.$slot->start_time)->isPast()) {
                return ['error' => 'Khung giờ này đã qua. Vui lòng chọn giờ khác.'];
            }

            $isBooked = Booking::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data['booking_date'])
                ->whereIn('status', Booking::BLOCKING_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($isBooked) {
                return ['error' => 'Khung giờ này đã có người đặt. Vui lòng chọn giờ khác hoặc đăng ký hàng đợi.'];
            }

            // Kiểm tra xem slot có đang được ưu tiên giữ cho người trong waitlist không
            $activeOffer = BookingWaitlist::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data['booking_date'])
                ->where('status', BookingWaitlist::STATUS_OFFERED)
                ->where('offer_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($activeOffer && $activeOffer->user_id !== $user->id) {
                return ['error' => 'Khung giờ đang được ưu tiên giữ cho thành viên trong hàng đợi đến '.$activeOffer->offer_expires_at->format('H:i').'. Vui lòng thử lại sau.'];
            }

            if ($activeOffer && $activeOffer->user_id === $user->id) {
                $activeOffer->update(['status' => BookingWaitlist::STATUS_ACCEPTED]);
            }

            $pricePerHour = $slot->price_per_hour ?? $field->price_per_hour;

            $booking = Booking::create([
                'user_id' => $user->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $data['booking_date'],
                'status' => Booking::STATUS_PENDING,
                'total_price' => $pricePerHour * $slot->durationInHours(),
                'notes' => $data['notes'] ?? null,
            ]);

            return ['error' => null, 'booking' => $booking, 'owner_email' => $field->owner?->email];
        });

        if ($result['error']) {
            return back()->withInput()->with('error', $result['error']);
        }

        if (! empty($result['owner_email'])) {
            SafeMail::queue($result['owner_email'], new NewBookingRequest($result['booking']));
        }

        return redirect()->route('customer.bookings.index')
            ->with('success', 'Yêu cầu đặt sân đã được gửi thành công!');
    }

    public function cancel(Request $request, Booking $booking, WaitlistService $waitlistService)
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($booking->user_id === $request->user()->id, 403);

        if (! $booking->canBeCancelled()) {
            return back()->with('error', 'Đơn đặt sân này không còn đủ điều kiện để hủy (quá thời hạn hủy sau khi duyệt hoặc trận đấu đã/sắp diễn ra).');
        }

        $user = $request->user();

        // Trừ điểm uy tín nếu hủy sát giờ (< 4h)
        if ($booking->isLateToCancel()) {
            TrustScoreService::penalizeLateCancellation($user, $booking);
        }

        $booking->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? 'Khách hàng tự hủy đơn.',
        ]);

        // Kích hoạt giải phóng slot cho người trong hàng đợi
        $waitlistService->handleSlotFreed(
            $booking->sports_field_id,
            $booking->time_slot_id,
            $booking->booking_date->toDateString()
        );

        $booking->load(['sportsField.owner', 'timeSlot', 'customer']);
        if ($booking->customer?->email) {
            SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
        }

        $message = 'Đã hủy đơn đặt sân thành công.';
        if ($booking->isLateToCancel()) {
            $message .= ' Lưu ý: Bạn bị trừ 15 điểm uy tín do hủy sát giờ thi đấu (< 4 giờ).';
        }

        return back()->with('success', $message);
    }
}
