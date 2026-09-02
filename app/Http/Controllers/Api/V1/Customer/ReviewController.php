<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Review\CreateReviewRequest;
use App\Http\Resources\Api\ReviewResource;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function store(CreateReviewRequest $request): JsonResponse
    {
        $review = $this->reviewService->create(
            $request->user(),
            $request->integer('booking_id'),
            $request->integer('rating'),
            $request->string('comment')->toString(),
        );

        return response()->json(['message' => 'Cảm ơn bạn đã gửi đánh giá.', 'data' => new ReviewResource($review)], 201);
    }
}
