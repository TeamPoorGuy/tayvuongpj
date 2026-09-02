<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BookingResource;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:pending,confirmed,completed,cancelled,rejected']]);

        return BookingResource::collection($this->service->bookings($request->input('status')));
    }
}
