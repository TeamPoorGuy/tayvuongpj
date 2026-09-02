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
}
