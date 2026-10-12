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
        return view('admin.dashboard', [
            'totalCustomers' => User::where('role', User::ROLE_CUSTOMER)->count(),
            'totalFieldOwners' => FieldOwnerProfile::where('verification_status', FieldOwnerProfile::STATUS_APPROVED)->count(),
            'pendingOwners' => FieldOwnerProfile::where('verification_status', FieldOwnerProfile::STATUS_PENDING)->count(),
            'totalFields' => SportsField::count(),
            'pendingFields' => SportsField::where('status', SportsField::STATUS_PENDING)->count(),
            'totalBookings' => Booking::count(),
            'totalRevenue' => (float) Booking::whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])->sum('total_price'),
            'mostBookedFields' => SportsField::with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
                ->withCount('bookings')
                ->orderByDesc('bookings_count')
                ->take(5)
                ->get(),
        ]);
    }
}
