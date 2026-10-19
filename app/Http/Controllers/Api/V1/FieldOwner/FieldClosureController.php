<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FieldClosureResource;
use App\Models\FieldClosure;
use App\Models\SportsField;
use App\Services\FieldClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FieldClosureController extends Controller
{
    public function __construct(private readonly FieldClosureService $service) {}

    public function index(Request $request, SportsField $field): AnonymousResourceCollection
    {
        return FieldClosureResource::collection($this->service->closures($request->user(), $field));
    }

    public function store(Request $request, SportsField $field): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
            'start_date.after_or_equal' => 'Ngày bắt đầu không được ở trong quá khứ.',
            'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'reason.required' => 'Vui lòng nhập lý do đóng sân.',
            'reason.max' => 'Lý do đóng sân không được vượt quá 255 ký tự.',
        ]);

        $closure = $this->service->createClosure($request->user(), $field, $validated);

        return response()->json([
            'message' => 'Đã ghi nhận lịch đóng sân.',
            'data' => new FieldClosureResource($closure),
        ], 201);
    }

    public function destroy(Request $request, SportsField $field, FieldClosure $closure): JsonResponse
    {
        $this->service->deleteClosure($request->user(), $field, $closure);

        return response()->json(['message' => 'Đã xoá lịch đóng sân. Sân đã mở lại hoạt động.']);
    }
}
