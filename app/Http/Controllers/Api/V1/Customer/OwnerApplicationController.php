<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\DTOs\Owner\SubmitApplicationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Owner\SubmitApplicationRequest;
use App\Http\Resources\Api\OwnerProfileResource;
use App\Services\OwnerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerApplicationController extends Controller
{
    public function __construct(private readonly OwnerApplicationService $service) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $this->service->show($request->user());

        return response()->json(['data' => $profile ? new OwnerProfileResource($profile) : null]);
    }

    public function document(Request $request): StreamedResponse
    {
        $path = $request->user()->fieldOwnerProfile?->verification_documents;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function store(SubmitApplicationRequest $request): JsonResponse
    {
        $profile = $this->service->submit($request->user(), SubmitApplicationData::fromRequest($request));

        return response()->json(['message' => 'Đã gửi hồ sơ đăng ký làm chủ sân.', 'data' => new OwnerProfileResource($profile)], 201);
    }
}
