<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Field\UpsertSlotRequest;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Services\FieldOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlotController extends Controller
{
    public function __construct(private readonly FieldOwnerService $service) {}

    public function store(UpsertSlotRequest $request, SportsField $field): JsonResponse
    {
        $slot = $this->service->saveSlot($request->user(), $field, $request->validated());

        return response()->json(['message' => 'Đã thêm khung giờ.', 'data' => $this->payload($slot)], 201);
    }

    public function update(UpsertSlotRequest $request, SportsField $field, TimeSlot $slot): JsonResponse
    {
        $slot = $this->service->saveSlot($request->user(), $field, $request->validated(), $slot);

        return response()->json(['message' => 'Đã cập nhật khung giờ.', 'data' => $this->payload($slot)]);
    }

    public function destroy(Request $request, SportsField $field, TimeSlot $slot): JsonResponse
    {
        $deleted = $this->service->deleteSlot($request->user(), $field, $slot);

        return response()->json(['message' => $deleted ? 'Đã xoá khung giờ.' : 'Khung giờ đã có lịch sử đặt nên được chuyển sang ngưng hoạt động.']);
    }

    private function payload(TimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'price_per_hour' => $slot->price_per_hour !== null ? (float) $slot->price_per_hour : null,
            'is_active' => (bool) $slot->is_active,
        ];
    }
}
