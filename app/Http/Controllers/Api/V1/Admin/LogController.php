<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['action', 'admin_id', 'from_date', 'to_date', 'per_page']);
        $logs = $this->service->adminLogs($filters);

        return response()->json($logs);
    }
}
