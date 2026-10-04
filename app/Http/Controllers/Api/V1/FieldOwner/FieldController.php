<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\DTOs\Field\UpsertFieldData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Field\AddImagesRequest;
use App\Http\Requests\Api\Field\UpsertFieldRequest;
use App\Http\Resources\Api\FieldResource;
use App\Models\FieldImage;
use App\Models\SportsField;
use App\Services\FieldOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FieldController extends Controller
{
    public function __construct(private readonly FieldOwnerService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return FieldResource::collection($this->service->fields($request->user()));
    }

    public function store(UpsertFieldRequest $request): JsonResponse
    {
        $field = $this->service->createField($request->user(), UpsertFieldData::fromRequest($request));

        return response()->json(['message' => 'Đã tạo sân và gửi kiểm duyệt.', 'data' => new FieldResource($field)], 201);
    }

    public function update(UpsertFieldRequest $request, SportsField $field): JsonResponse
    {
        $field = $this->service->updateField($request->user(), $field, UpsertFieldData::fromRequest($request));

        return response()->json(['message' => 'Đã cập nhật sân.', 'data' => new FieldResource($field)]);
    }

    public function destroy(Request $request, SportsField $field): JsonResponse
    {
        $this->service->deleteField($request->user(), $field);

        return response()->json(['message' => 'Đã xoá sân.']);
    }

    public function addImages(AddImagesRequest $request, SportsField $field): JsonResponse
    {
        $field = $this->service->addImages($request->user(), $field, $request->file('images'));

        return response()->json(['message' => 'Đã thêm ảnh.', 'data' => new FieldResource($field)], 201);
    }

    public function deleteImage(Request $request, SportsField $field, FieldImage $image): JsonResponse
    {
        return response()->json(['message' => 'Đã xoá ảnh.', 'data' => new FieldResource($this->service->deleteImage($request->user(), $field, $image))]);
    }

    public function primaryImage(Request $request, SportsField $field, FieldImage $image): JsonResponse
    {
        return response()->json(['message' => 'Đã đặt ảnh đại diện.', 'data' => new FieldResource($this->service->setPrimaryImage($request->user(), $field, $image))]);
    }

    public function toggle(Request $request, SportsField $field): JsonResponse
    {
        $field = $this->service->toggleField($request->user(), $field);

        return response()->json(['message' => 'Đã đổi trạng thái hoạt động.', 'data' => new FieldResource($field)]);
    }
}
