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

    public function field(string $slug): FieldResource
    {
        return new FieldResource($this->catalogService->fieldBySlug($slug));
    }

    public function availableSlots(AvailableSlotsRequest $request, SportsField $field): JsonResponse
    {
        return response()->json(['data' => [
            'date' => $request->string('date')->toString(),
            'slots' => $this->catalogService->availableSlots($field, $request->string('date')->toString()),
        ]]);
    }
}
