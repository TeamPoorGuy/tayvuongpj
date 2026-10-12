<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'type_id' => ['nullable', 'integer', 'exists:field_types,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'gte:min_price'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,30'],
        ]);

        $fields = SportsField::approved()
            ->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%"));
            })
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'fieldType.sportCategory',
                fn ($category) => $category->where('slug', $request->string('category')->toString())
            ))
            ->when($request->filled('type_id'), fn ($query) => $query->where('field_type_id', $request->integer('type_id')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price_per_hour', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price_per_hour', '<=', $request->input('max_price')))
            ->latest()
            ->paginate($request->integer('per_page', 9))
            ->withQueryString();

        $categories = SportCategory::query()
            ->where('is_active', true)
            ->withCount('fieldTypes')
            ->get();

        $fieldTypes = FieldType::query()
            ->where('is_active', true)
            ->with('sportCategory')
            ->get();

        return view('customer.fields.index', compact('fields', 'categories', 'fieldTypes'));
    }

    public function show(string $slug)
    {
        $field = SportsField::approved()
            ->where('slug', $slug)
            ->with(['images', 'primaryImage', 'fieldType.sportCategory', 'owner.fieldOwnerProfile', 'timeSlots', 'reviews.user'])
            ->firstOrFail();

        return view('customer.fields.show', compact('field'));
    }

    public function checkAvailableSlots(Request $request, SportsField $field): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        abort_unless(
            SportsField::approved()->whereKey($field->id)->exists(),
            404,
            'Không tìm thấy sân đang hoạt động.'
        );

        $bookedSlotIds = Booking::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $data['date'])
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->pluck('time_slot_id');

        $user = $request->user();
        $userWaitlistedSlotIds = $user ? \App\Models\BookingWaitlist::query()
            ->where('user_id', $user->id)
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $data['date'])
            ->whereIn('status', [\App\Models\BookingWaitlist::STATUS_WAITING, \App\Models\BookingWaitlist::STATUS_OFFERED])
            ->pluck('time_slot_id') : collect();

        $slots = $field->timeSlots()
            ->where('is_active', true)
            ->get()
            ->map(function ($slot) use ($data, $bookedSlotIds, $field, $userWaitlistedSlotIds) {
                $isPast = \Carbon\Carbon::parse($data['date'].' '.$slot->start_time)->isPast();
                $isBooked = $bookedSlotIds->contains($slot->id) || $isPast;
                $price = $slot->price_per_hour ?? $field->price_per_hour;

                return [
                    'id' => $slot->id,
                    'start_time' => substr($slot->start_time, 0, 5),
                    'end_time' => substr($slot->end_time, 0, 5),
                    'price' => (float) $price,
                    'is_booked' => $isBooked,
                    'is_past' => $isPast,
                    'is_waitlisted' => $userWaitlistedSlotIds->contains($slot->id),
                ];
            })
            ->all();

        return response()->json([
            'success' => true,
            'date' => $data['date'],
            'slots' => $slots,
        ]);
    }

    public function getSuggestions(Request $request, SportsField $field, \App\Services\SmartSuggestionService $service): JsonResponse
    {
        $data = $request->validate([
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $slot = TimeSlot::findOrFail($data['time_slot_id']);
        $suggestions = $service->getSuggestions($field, $slot, $data['date']);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }
}
