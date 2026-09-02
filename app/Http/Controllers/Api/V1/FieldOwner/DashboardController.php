<?php

namespace App\Http\Controllers\Api\V1\FieldOwner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Http\Resources\Api\FieldResource;
use App\Services\FieldOwnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly FieldOwnerService $service) {}

    public function show(Request $request): JsonResponse
    {
        $data = $this->service->dashboard($request->user());
        $data['most_booked_field'] = $data['most_booked_field'] ? new FieldResource($data['most_booked_field']) : null;
        $data['recent_bookings'] = BookingResource::collection($data['recent_bookings']);

        return response()->json(['data' => $data]);
    }
}
