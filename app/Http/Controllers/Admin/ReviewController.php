<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::with(['user', 'sportsField'])->latest()->paginate(15);

        return view('admin.reviews.index', compact('reviews'));
    }

    public function toggle(Review $review)
    {
        $review->update(['is_visible' => ! $review->is_visible]);

        return back()->with('success', 'Đã thay đổi trạng thái hiển thị đánh giá.');
    }
}
