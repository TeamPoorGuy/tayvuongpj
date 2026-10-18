<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FieldResource;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:this_month,last_month'],
        ]);

        $data = $this->service->dashboard(
            $request->input('period', 'this_month')
        );
        $data['top_fields'] = FieldResource::collection($data['top_fields']);

        return response()->json(['data' => $data]);
    }
}
