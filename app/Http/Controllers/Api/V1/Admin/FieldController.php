<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\VerifyRequest;
use App\Http\Resources\Api\FieldResource;
use App\Models\SportsField;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FieldController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,inactive']]);

        return FieldResource::collection($this->service->fields($request->input('status')));
    }

    public function verify(VerifyRequest $request, SportsField $field): JsonResponse
    {
        return response()->json(['message' => 'Đã xử lý kiểm duyệt sân.', 'data' => new FieldResource($this->service->verifyField($field, $request->string('status')->toString()))]);
    }
}
