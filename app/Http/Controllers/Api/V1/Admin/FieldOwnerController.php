<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\VerifyOwnerRequest;
use App\Http\Resources\Api\OwnerProfileResource;
use App\Models\FieldOwnerProfile;
use App\Models\OwnerDocument;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldOwnerController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return OwnerProfileResource::collection($this->service->ownerProfiles($request->query('status')));
    }

    public function document(FieldOwnerProfile $profile, OwnerDocument $document): StreamedResponse
    {
        abort_unless($document->field_owner_profile_id === $profile->id && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    public function verify(VerifyOwnerRequest $request, FieldOwnerProfile $profile): JsonResponse
    {
        $profile = $this->service->verifyOwner(
            $profile,
            $request->string('status')->toString(),
            $request->input('rejection_reason'),
            $request->user(),
        );

        return response()->json(['message' => 'Đã xử lý hồ sơ chủ sân.', 'data' => new OwnerProfileResource($profile)]);
    }
}
