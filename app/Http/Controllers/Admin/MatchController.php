<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchRequest;
use App\Services\MatchmakingService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $matches = MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('keyword'), fn ($q) => $q->where('title', 'like', '%'.$request->input('keyword').'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.matches.index', compact('matches'));
    }

    public function cancel(Request $request, MatchRequest $match, MatchmakingService $service)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($request->user(), $match->id, $data['reason']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã hủy phòng thi đấu và gửi email thông báo cho các bên liên quan.');
    }
}
