<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FieldResource;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function show(): JsonResponse
    {
        $data = $this->service->dashboard();
        $data['most_booked_fields'] = FieldResource::collection($data['most_booked_fields']);

        return response()->json(['data' => $data]);
    }
}
