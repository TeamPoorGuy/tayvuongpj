<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $query = SportsField::approved()->with(['primaryImage', 'fieldType.sportCategory', 'reviews']);

        // Tìm kiếm theo tên hoặc địa chỉ
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('address', 'like', "%{$keyword}%");
            });
        }

        // Lọc theo thể thao
        if ($request->filled('category')) {
            $query->whereHas('fieldType.sportCategory', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Lọc theo loại sân
        if ($request->filled('type_id')) {
            $query->where('field_type_id', $request->type_id);
        }

        // Lọc theo khoảng giá
        if ($request->filled('min_price')) {
            $query->where('price_per_hour', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_per_hour', '<=', $request->max_price);
        }

        $fields = $query->paginate(9)->withQueryString();
        $categories = SportCategory::where('is_active', true)->get();
        $fieldTypes = FieldType::where('is_active', true)->get();

        return view('customer.fields.index', compact('fields', 'categories', 'fieldTypes'));
    }

    public function show($slug)
    {
        $field = SportsField::approved()
            ->where('slug', $slug)
            ->with(['images', 'fieldType.sportCategory', 'owner.fieldOwnerProfile', 'timeSlots', 'reviews.user'])
            ->firstOrFail();

        return view('customer.fields.show', compact('field'));
    }

    /**
     * AJAX endpoint: Kiểm tra các khung giờ còn trống trong ngày đã chọn
     */
    public function checkAvailableSlots(Request $request, $fieldId)
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'date.required' => 'Vui lòng chọn ngày đặt sân.',
            'date.after_or_equal' => 'Không được chọn ngày trong quá khứ.',
        ]);

        $field = SportsField::findOrFail($fieldId);
        $bookingDate = $request->date;

        // Lấy danh sách các slot đã được đặt trong ngày này (trạng thái pending hoặc confirmed)
        $bookedSlotIds = Booking::where('sports_field_id', $fieldId)
            ->where('booking_date', $bookingDate)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('time_slot_id')
            ->toArray();

        $allSlots = $field->timeSlots()->where('is_active', true)->get();

        $formattedSlots = $allSlots->map(function ($slot) use ($bookedSlotIds) {
            $isBooked = in_array($slot->id, $bookedSlotIds);
            return [
                'id' => $slot->id,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'is_booked' => $isBooked,
            ];
        });

        return response()->json([
            'success' => true,
            'date' => $bookingDate,
            'slots' => $formattedSlots,
        ]);
    }
}
