<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Mail\MatchCancelled;
use App\Models\MatchParticipant;
use App\Models\MatchRequest;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MatchmakingService
{
    /**
     * Danh sách bài đăng tìm đối / ghép đội.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])
            ->when($filters['category_id'] ?? null, fn ($q, $cat) => $q->where('sport_category_id', $cat))
            ->when($filters['skill_level'] ?? null, fn ($q, $lvl) => $q->where('skill_level', $lvl))
            ->when($filters['status'] ?? null, fn ($q, $st) => $q->where('status', $st), fn ($q) => $q->whereIn('status', ['open', 'full']))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('play_date', $date))
            ->orderBy('play_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Tạo bài đăng tìm đối / người chơi.
     */
    public function create(User $user, array $data): MatchRequest
    {
        $open = MatchRequest::where('user_id', $user->id)->whereIn('status', ['open', 'full'])->count();
        if ($open >= 5) {
            throw new ConflictException('Bạn chỉ có thể mở tối đa 5 phòng tìm đối cùng lúc.');
        }

        return MatchRequest::create([
            'user_id' => $user->id,
            'sport_category_id' => $data['sport_category_id'],
            'sports_field_id' => $data['sports_field_id'] ?? null,
            'title' => $data['title'],
            'play_date' => $data['play_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'skill_level' => $data['skill_level'] ?? 'intermediate',
            'players_needed' => $data['players_needed'] ?? 1,
            'joined_count' => 1, // host là 1 người
            'description' => $data['description'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? $user->phone ?? '',
            'status' => 'open',
        ])->load(['host', 'sportCategory', 'sportsField']);
    }

    /**
     * Khách hàng xin tham gia trận đấu.
     */
    public function join(User $user, int $matchId, ?string $message = null): MatchParticipant
    {
        return DB::transaction(function () use ($user, $matchId, $message) {
            $match = MatchRequest::where('id', $matchId)->lockForUpdate()->firstOrFail();

            if ($match->user_id === $user->id) {
                throw new DomainException('Bạn là chủ phòng của trận đấu này.');
            }

            if ($match->status !== 'open') {
                throw new ConflictException('Trận đấu này đã đủ người hoặc đã đóng.');
            }

            $existing = MatchParticipant::where('match_request_id', $matchId)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw new ConflictException('Bạn đã gửi yêu cầu tham gia trận đấu này.');
            }

            return MatchParticipant::create([
                'match_request_id' => $matchId,
                'user_id' => $user->id,
                'status' => 'pending',
                'message' => $message,
            ])->load(['user', 'matchRequest']);
        });
    }

    /**
     * Chủ phòng duyệt hoặc từ chối người xin tham gia.
     */
    public function updateParticipantStatus(User $host, int $matchId, int $participantId, string $status): MatchParticipant
    {
        return DB::transaction(function () use ($host, $matchId, $participantId, $status) {
            $match = MatchRequest::where('id', $matchId)->lockForUpdate()->firstOrFail();

            if ($match->user_id !== $host->id && ! $host->isAdmin()) {
                throw new DomainException('Bạn không có quyền quản lý người tham gia trận đấu này.');
            }

            $participant = MatchParticipant::where('id', $participantId)
                ->where('match_request_id', $matchId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($status, ['accepted', 'rejected'], true)) {
                throw new DomainException('Trạng thái duyệt không hợp lệ.');
            }

            $oldStatus = $participant->status;
            $participant->update(['status' => $status]);

            // Cập nhật joined_count nếu được duyệt
            if ($status === 'accepted' && $oldStatus !== 'accepted') {
                $match->increment('joined_count');
                if ($match->joined_count >= ($match->players_needed + 1)) {
                    $match->update(['status' => 'full']);
                }
            } elseif ($status === 'rejected' && $oldStatus === 'accepted') {
                $match->decrement('joined_count');
                if ($match->status === 'full') {
                    $match->update(['status' => 'open']);
                }
            }

            return $participant->load(['user']);
        });
    }

    /**
     * Hủy bài đăng tìm đối.
     */
    public function cancel(User $host, int $matchId): MatchRequest
    {
        $match = MatchRequest::with('participants.user')->where('id', $matchId)->firstOrFail();

        if ($match->user_id !== $host->id && ! $host->isAdmin()) {
            throw new DomainException('Bạn không có quyền hủy trận đấu này.');
        }

        $match->update(['status' => 'cancelled']);

        // Báo cho người đã được chấp nhận để họ không đến sân vô ích.
        foreach ($match->participants->where('status', 'accepted') as $participant) {
            SafeMail::queue($participant->user?->email, new MatchCancelled($match));
        }

        return $match;
    }

    /**
     * Người tham gia tự rút lui (hoặc rút yêu cầu đang chờ).
     */
    public function leave(User $user, int $matchId): void
    {
        DB::transaction(function () use ($user, $matchId) {
            $match = MatchRequest::where('id', $matchId)->lockForUpdate()->firstOrFail();
            $participant = MatchParticipant::where('match_request_id', $matchId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $participant) {
                throw new DomainException('Bạn chưa tham gia trận đấu này.');
            }

            $wasAccepted = $participant->status === 'accepted';
            $participant->delete();

            if ($wasAccepted) {
                $match->decrement('joined_count');
                if ($match->status === 'full') {
                    $match->update(['status' => 'open']);
                }
            }
        });
    }
}
