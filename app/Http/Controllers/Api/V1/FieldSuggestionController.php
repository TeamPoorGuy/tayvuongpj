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

        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slot = TimeSlot::where('sports_field_id', $field->id)->findOrFail($validated['slot_id']);

        $suggestions = $this->service->getSuggestions($field, $slot, $validated['date']);

        return response()->json([
            'data' => $suggestions,
        ]);
    }
}
