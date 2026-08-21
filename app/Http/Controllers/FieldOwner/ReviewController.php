<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\SportsField;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index()
    {
        $fieldIds = SportsField::where('field_owner_id', Auth::id())->pluck('id');
        $reviews = Review::whereIn('sports_field_id', $fieldIds)
            ->with(['user', 'sportsField', 'booking'])
            ->latest()
            ->paginate(10);

        return view('field-owner.reviews.index', compact('reviews'));
    }
}
