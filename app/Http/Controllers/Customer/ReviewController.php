<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'max:1000'],
        ], [
            'comment.required' => 'Vui lòng nhập nội dung đánh giá.',
            'rating.between' => 'Đánh giá phải từ 1 đến 5 sao.',
        ]);

        $booking = Booking::where('id', $request->booking_id)
            ->where('user_id', Auth::id())
            ->where('status', 'completed')
            ->firstOrFail();

        if ($booking->review) {
            return back()->with('error', 'Bạn đã đánh giá cho lượt đặt sân này rồi.');
        }

        Review::create([
            'user_id' => Auth::id(),
            'sports_field_id' => $booking->sports_field_id,
            'booking_id' => $booking->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_visible' => true,
        ]);

        return back()->with('success', 'Cảm ơn bạn đã gửi đánh giá về sân!');
    }
}
