<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Services\SmartSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldSuggestionController extends Controller
{
    public function __construct(protected SmartSuggestionService $service) {}

    public function suggestions(Request $request, string $slug): JsonResponse
    {
        $field = SportsField::where('slug', $slug)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->firstOrFail();

        // Khoảng giờ đang kín: theo khung giờ có sẵn (slot_id) hoặc giờ chọn trên lưới.
        $validated = $request->validate([
            'slot_id' => ['required_without:start_time', 'nullable', 'integer', 'exists:time_slots,id'],
            'start_time' => ['required_without:slot_id', 'nullable', 'date_format:H:i'],
            'end_time' => ['required_with:start_time', 'nullable', 'date_format:H:i', 'after:start_time'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        if (! empty($validated['slot_id'])) {
            $slot = TimeSlot::where('sports_field_id', $field->id)->findOrFail($validated['slot_id']);
            [$start, $end] = [$slot->start_time, $slot->end_time];
        } else {
            [$start, $end] = [$validated['start_time'], $validated['end_time']];
        }

        $suggestions = $this->service->getSuggestions($field, $start, $end, $validated['date']);

        return response()->json([
            'data' => $suggestions,
        ]);
    }
}
