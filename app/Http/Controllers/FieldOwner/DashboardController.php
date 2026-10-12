<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = $request->user()->id;
        $fieldIds = SportsField::where('field_owner_id', $ownerId)->pluck('id');
        $revenueStatuses = [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED];

        return view('field-owner.dashboard', [
            'totalFields' => $fieldIds->count(),
            'totalBookings' => Booking::whereIn('sports_field_id', $fieldIds)->count(),
            'totalRevenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)
                ->whereIn('status', $revenueStatuses)->sum('total_price'),
            'currentMonthBookings' => Booking::whereIn('sports_field_id', $fieldIds)
                ->whereYear('booking_date', now()->year)
                ->whereMonth('booking_date', now()->month)
                ->count(),
            'currentMonthRevenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)
                ->whereIn('status', $revenueStatuses)
                ->whereYear('booking_date', now()->year)
                ->whereMonth('booking_date', now()->month)
                ->sum('total_price'),
            'mostBookedField' => SportsField::where('field_owner_id', $ownerId)
                ->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
                ->withCount('bookings')
                ->orderByDesc('bookings_count')
                ->first(),
            'recentBookings' => Booking::whereIn('sports_field_id', $fieldIds)
                ->with(['sportsField.primaryImage', 'sportsField.reviews', 'customer', 'timeSlot'])
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
