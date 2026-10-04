<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\Admin\StoreFieldTypeRequest;
use App\Http\Requests\Api\Admin\UpdateCategoryRequest;
use App\Http\Requests\Api\Admin\UpdateFieldTypeRequest;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\FieldTypeResource;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->service->categories());
    }

    public function storeCategory(StoreCategoryRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Đã tạo danh mục.', 'data' => new CategoryResource($this->service->createCategory($request->validated()))], 201);
    }

    public function updateCategory(UpdateCategoryRequest $request, SportCategory $category): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật danh mục.', 'data' => new CategoryResource($this->service->updateCategory($category, $request->validated()))]);
    }

    public function updateFieldType(UpdateFieldTypeRequest $request, FieldType $fieldType): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật loại sân.', 'data' => new FieldTypeResource($this->service->updateFieldType($fieldType, $request->validated()))]);
    }

    public function storeFieldType(StoreFieldTypeRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Đã tạo loại sân.', 'data' => new FieldTypeResource($this->service->createFieldType($request->validated()))], 201);
    }
}
