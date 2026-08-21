<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SportsField;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $ownerId = Auth::id();

        // 1. Tổng số sân thuộc quyền sở hữu
        $totalFields = SportsField::where('field_owner_id', $ownerId)->count();

        // Lấy danh sách ID các sân thuộc về chủ sân này
        $fieldIds = SportsField::where('field_owner_id', $ownerId)->pluck('id');

        // 2. Tổng số lượt đặt sân
        $totalBookings = Booking::whereIn('sports_field_id', $fieldIds)->count();

        // 3. Tổng doanh thu (các đơn đã xác nhận hoặc hoàn thành)
        $totalRevenue = Booking::whereIn('sports_field_id', $fieldIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_price');

        // 4. Số lượt đặt theo tháng hiện tại
        $currentMonthBookings = Booking::whereIn('sports_field_id', $fieldIds)
            ->whereYear('booking_date', Carbon::now()->year)
            ->whereMonth('booking_date', Carbon::now()->month)
            ->count();

        // 5. Doanh thu theo tháng hiện tại
        $currentMonthRevenue = Booking::whereIn('sports_field_id', $fieldIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereYear('booking_date', Carbon::now()->year)
            ->whereMonth('booking_date', Carbon::now()->month)
            ->sum('total_price');

        // 6. Sân được đặt nhiều nhất
        $mostBookedField = SportsField::where('field_owner_id', $ownerId)
            ->withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->first();

        // 7. Đơn đặt sân mới nhất
        $recentBookings = Booking::whereIn('sports_field_id', $fieldIds)
            ->with(['sportsField', 'customer', 'timeSlot'])
            ->latest()
            ->take(5)
            ->get();

        return view('field-owner.dashboard', compact(
            'totalFields',
            'totalBookings',
            'totalRevenue',
            'currentMonthBookings',
            'currentMonthRevenue',
            'mostBookedField',
            'recentBookings'
        ));
    }
}
