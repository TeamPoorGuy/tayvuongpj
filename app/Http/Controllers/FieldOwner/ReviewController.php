<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\SportsField;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $fieldIds = SportsField::where('field_owner_id', $request->user()->id)->pluck('id');
        $reviews = Review::whereIn('sports_field_id', $fieldIds)
            ->with(['user', 'sportsField'])
            ->latest()
            ->paginate(10);

        return view('field-owner.reviews.index', compact('reviews'));
    }
}
