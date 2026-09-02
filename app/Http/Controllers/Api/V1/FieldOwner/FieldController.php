<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\DTOs\Field\UpsertFieldData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Field\UpsertFieldRequest;
use App\Http\Resources\Api\FieldResource;
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

    public function toggle(Request $request, SportsField $field): JsonResponse
    {
        $field = $this->service->toggleField($request->user(), $field);

        return response()->json(['message' => 'Đã đổi trạng thái hoạt động.', 'data' => new FieldResource($field)]);
    }
}
