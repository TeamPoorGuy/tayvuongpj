<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReviewResource;
use App\Services\FieldOwnerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private readonly FieldOwnerService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReviewResource::collection($this->service->reviews($request->user()));
    }

    public function reply(Request $request, \App\Models\Review $review): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:1000'],
        ], [
            'reply.required' => 'Vui lòng nhập nội dung phản hồi.',
            'reply.max' => 'Nội dung phản hồi không được quá 1000 ký tự.',
        ]);

        $updated = $this->service->replyReview($request->user(), $review, $validated['reply']);

        return response()->json([
            'message' => 'Đã gửi phản hồi đánh giá.',
            'data' => new ReviewResource($updated),
        ]);
    }
}
