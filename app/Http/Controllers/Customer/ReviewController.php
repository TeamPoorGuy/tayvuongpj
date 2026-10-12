<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->with('review')
            ->findOrFail($data['booking_id']);

        if ($booking->review) {
            return back()->withInput()->with('error', 'Bạn đã đánh giá lượt đặt sân này rồi.');
        }

        Review::create([
            'user_id' => $request->user()->id,
            'sports_field_id' => $booking->sports_field_id,
            'booking_id' => $booking->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'is_visible' => true,
        ]);

        return back()->with('success', 'Cảm ơn bạn đã gửi đánh giá về sân!');
    }
}
