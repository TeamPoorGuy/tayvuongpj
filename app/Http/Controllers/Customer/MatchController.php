<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MatchParticipant;
use App\Models\MatchRequest;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Services\MatchmakingService;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:sport_categories,id'],
            'date' => ['nullable', 'date'],
            'skill_level' => ['nullable', 'string', 'in:beginner,intermediate,advanced,any'],
        ]);

        $matches = MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])
            ->whereIn('status', [MatchRequest::STATUS_OPEN, MatchRequest::STATUS_FULL])
            ->whereDate('play_date', '>=', today())
            ->when($request->filled('category_id'), fn ($q) => $q->where('sport_category_id', $request->input('category_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('play_date', $request->input('date')))
            ->when($request->filled('skill_level') && $request->input('skill_level') !== 'any', fn ($q) => $q->where('skill_level', $request->input('skill_level')))
            ->orderBy('play_date')
            ->orderBy('start_time')
            ->paginate(12)
            ->withQueryString();

        $categories = SportCategory::where('is_active', true)->get();

        return view('customer.matches.index', compact('matches', 'categories'));
    }

    public function create()
    {
        $categories = SportCategory::where('is_active', true)->with('fieldTypes')->get();
        $fields = SportsField::approved()->with(['fieldType.sportCategory', 'timeSlots'])->get();

        return view('customer.matches.create', compact('categories', 'fields'));
    }

    public function store(Request $request, MatchmakingService $service)
    {
        $data = $request->validate([
            'sport_category_id' => ['required', 'integer', 'exists:sport_categories,id'],
            'sports_field_id' => ['nullable', 'integer', 'exists:sports_fields,id'],
            'title' => ['required', 'string', 'max:255'],
            'play_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'string', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'end_time' => ['required', 'string', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'skill_level' => ['required', 'string', 'in:beginner,intermediate,advanced,any'],
            'players_needed' => ['required', 'integer', 'min:1', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $match = $service->create($request->user(), $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('customer.matches.show', $match->id)
            ->with('success', 'Đã tạo phòng tìm đối thủ thành công!');
    }

    public function show(MatchRequest $match)
    {
        $match->load(['host', 'sportCategory', 'sportsField.primaryImage', 'participants.user']);

        return view('customer.matches.show', compact('match'));
    }

    public function join(Request $request, MatchRequest $match, MatchmakingService $service)
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $service->join($request->user(), $match->id, $data['message'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Đã gửi yêu cầu tham gia trận đấu! Chờ chủ phòng duyệt.');
    }

    public function leave(Request $request, MatchRequest $match, MatchmakingService $service)
    {
        try {
            $service->leave($request->user(), $match->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Bạn đã rời khỏi phòng thi đấu.');
    }

    public function updateParticipant(Request $request, MatchRequest $match, MatchParticipant $participant, MatchmakingService $service)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:accepted,rejected'],
        ]);

        try {
            $service->updateParticipantStatus($request->user(), $match->id, $participant->id, $data['status']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $msg = $data['status'] === 'accepted' ? 'Đã duyệt thành viên tham gia!' : 'Đã từ chối thành viên.';

        return back()->with('success', $msg);
    }

    public function cancel(Request $request, MatchRequest $match, MatchmakingService $service)
    {
        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($request->user(), $match->id, $data['cancel_reason'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('customer.matches.index')
            ->with('success', 'Đã hủy phòng thi đấu thành công.');
    }
}
