<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Field\AvailableSlotsRequest;
use App\Http\Requests\Api\Field\FieldIndexRequest;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\FieldResource;
use App\Http\Resources\Api\FieldTypeResource;
use App\Models\SportsField;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService) {}

    public function home(): JsonResponse
    {
        return response()->json(['data' => [
            'categories' => CategoryResource::collection($this->catalogService->categories()),
            'featured_fields' => FieldResource::collection($this->catalogService->featuredFields()),
        ]]);
    }

    public function categories(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->catalogService->categories(true));
    }

    public function fieldTypes(): AnonymousResourceCollection
    {
        return FieldTypeResource::collection($this->catalogService->fieldTypes());
    }

    public function fields(FieldIndexRequest $request): AnonymousResourceCollection
    {
        return FieldResource::collection($this->catalogService->fields($request));
    }

    public function locations(): JsonResponse
    {
        return response()->json(['data' => $this->catalogService->locations()]);
    }

    public function field(string $slug): FieldResource
    {
        return new FieldResource($this->catalogService->fieldBySlug($slug));
    }

    /** Lưới ô 30 phút của ngày cho sân đang xem và các sân cùng cụm. */
    public function schedule(AvailableSlotsRequest $request, SportsField $field): JsonResponse
    {
        abort_unless(SportsField::approved()->whereKey($field->id)->exists(), 404, 'Không tìm thấy sân đang hoạt động.');
        $date = $request->string('date')->toString();

        return response()->json(['data' => [
            'date' => $date,
            'fields' => $field->siblings()->map(fn (SportsField $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'slug' => $court->slug,
                'price_per_hour' => (float) $court->price_per_hour,
                'is_closed' => $court->isClosedOn($date),
                'closure_reason' => $court->closureOn($date)?->reason,
                'cells' => $court->cellsFor($date),
            ])->all(),
        ]]);
    }

    public function availableSlots(AvailableSlotsRequest $request, SportsField $field): JsonResponse
    {
        $date = $request->string('date')->toString();
        $closure = $field->closureOn($date);

        return response()->json(['data' => [
            'date' => $date,
            'is_closed' => $closure !== null,
            'closure_reason' => $closure?->reason,
            'slots' => $this->catalogService->availableSlots($field, $date),
        ]]);
    }
}
