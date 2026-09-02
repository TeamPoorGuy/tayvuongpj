<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ReviewResource;
use App\Models\Review;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return ReviewResource::collection($this->service->reviews());
    }

    public function toggle(Review $review): JsonResponse
    {
        return response()->json(['message' => 'Đã cập nhật hiển thị đánh giá.', 'data' => new ReviewResource($this->service->toggleReview($review))]);
    }
}
