<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\SportsField;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCustomers = User::where('role', 'customer')->count();
        $totalFieldOwners = User::where('role', 'field_owner')->count();
        $pendingOwners = FieldOwnerProfile::where('verification_status', 'pending')->count();
        $totalFields = SportsField::count();
        $pendingFields = SportsField::where('status', 'pending')->count();
        $totalBookings = Booking::count();
        $totalRevenue = Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_price');

        $mostBookedFields = SportsField::withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalCustomers',
            'totalFieldOwners',
            'pendingOwners',
            'totalFields',
            'pendingFields',
            'totalBookings',
            'totalRevenue',
            'mostBookedFields'
        ));
    }
}
