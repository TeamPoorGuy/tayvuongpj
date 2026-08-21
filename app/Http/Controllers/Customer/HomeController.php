<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SportCategory;
use App\Models\SportsField;

class HomeController extends Controller
{
    public function index()
    {
        $categories = SportCategory::where('is_active', true)->withCount('fieldTypes')->get();
        
        $featuredFields = SportsField::approved()
            ->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
            ->latest()
            ->take(6)
            ->get();

        return view('customer.home', compact('categories', 'featuredFields'));
    }
}
