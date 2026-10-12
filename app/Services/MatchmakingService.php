<?php

namespace App\Services;

use App\Mail\MatchCancelled;
use App\Models\MatchParticipant;
use App\Models\MatchRequest;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use App\Support\SafeMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchmakingService
{
    /**
     * Tạo yêu cầu tìm đối / ghép đội mới.
     */
    public function create(User $user, array $data): MatchRequest
    {
        $open = MatchRequest::where('user_id', $user->id)->where('status', MatchRequest::STATUS_OPEN)->count();
        if ($open >= 5) {
            throw ValidationException::withMessages(['title' => 'Bạn chỉ có thể mở tối đa 5 phòng tìm đối cùng lúc.']);
        }

        if (Carbon::parse($data['play_date'].' '.$data['start_time'])->isPast()) {
            throw ValidationException::withMessages(['start_time' => 'Giờ bắt đầu đã qua. Vui lòng chọn giờ khác.']);
        }

        if (! empty($data['sports_field_id'])) {
            $field = SportsField::with('fieldType')->findOrFail($data['sports_field_id']);

            if ($field->fieldType?->sport_category_id !== (int) $data['sport_category_id']) {
                throw ValidationException::withMessages(['sports_field_id' => 'Sân này không thuộc môn thể thao đã chọn.']);
            }

            $matchesSlot = TimeSlot::where('sports_field_id', $field->id)->where('is_active', true)->get()
                ->contains(fn (TimeSlot $slot) => substr($slot->start_time, 0, 5) === $data['start_time']
                    && substr($slot->end_time, 0, 5) === $data['end_time']);

            if (! $matchesSlot) {
                throw ValidationException::withMessages(['start_time' => 'Giờ chơi phải trùng một khung giờ của sân đã chọn.']);
            }
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
            'joined_count' => 1,
            'description' => $data['description'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? $user->phone ?? '',
            'status' => MatchRequest::STATUS_OPEN,
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
                throw ValidationException::withMessages(['user_id' => 'Bạn là chủ phòng của trận đấu này.']);
            }

            if ($match->status !== MatchRequest::STATUS_OPEN) {
                throw ValidationException::withMessages(['match' => 'Trận đấu này đã đủ người hoặc đã đóng.']);
            }

            $existing = MatchParticipant::where('match_request_id', $matchId)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages(['match' => 'Bạn đã gửi yêu cầu tham gia trận đấu này rồi.']);
            }

            return MatchParticipant::create([
                'match_request_id' => $matchId,
                'user_id' => $user->id,
                'status' => MatchParticipant::STATUS_PENDING,
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
                throw ValidationException::withMessages(['host' => 'Bạn không có quyền quản lý người tham gia trận đấu này.']);
            }

            $participant = MatchParticipant::where('id', $participantId)
                ->where('match_request_id', $matchId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($status, [MatchParticipant::STATUS_ACCEPTED, MatchParticipant::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages(['status' => 'Trạng thái duyệt không hợp lệ.']);
            }

            $oldStatus = $participant->status;

            if ($status === MatchParticipant::STATUS_ACCEPTED && $oldStatus !== MatchParticipant::STATUS_ACCEPTED && $match->status !== MatchRequest::STATUS_OPEN) {
                throw ValidationException::withMessages(['status' => 'Phòng đã đủ người hoặc đã đóng, không thể duyệt thêm.']);
            }

            $participant->update(['status' => $status]);

            if ($status === MatchParticipant::STATUS_ACCEPTED && $oldStatus !== MatchParticipant::STATUS_ACCEPTED) {
                $match->increment('joined_count');
                if ($match->joined_count >= ($match->players_needed + 1)) {
                    $match->update(['status' => MatchRequest::STATUS_FULL]);
                }
            } elseif ($status === MatchParticipant::STATUS_REJECTED && $oldStatus === MatchParticipant::STATUS_ACCEPTED) {
                $match->decrement('joined_count');
                if ($match->status === MatchRequest::STATUS_FULL) {
                    $match->update(['status' => MatchRequest::STATUS_OPEN]);
                }
            }

            return $participant->load(['user']);
        });
    }

    /**
     * Hủy bài đăng tìm đối.
     */
    public function cancel(User $host, int $matchId, ?string $reason = null): MatchRequest
    {
        $match = DB::transaction(function () use ($host, $matchId) {
            $match = MatchRequest::with(['participants.user', 'host'])->where('id', $matchId)->lockForUpdate()->firstOrFail();

            if ($match->user_id !== $host->id && ! $host->isAdmin()) {
                throw ValidationException::withMessages(['host' => 'Bạn không có quyền hủy trận đấu này.']);
            }

            if (in_array($match->status, [MatchRequest::STATUS_CANCELLED, MatchRequest::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages(['match' => 'Trận đấu này đã đóng, không thể huỷ lại.']);
            }

            $match->update(['status' => MatchRequest::STATUS_CANCELLED]);

            return $match;
        });

        $cancelReason = $reason ?: ($host->isAdmin() ? 'Quản trị viên đã huỷ phòng thi đấu.' : 'Chủ phòng đã huỷ trận đấu.');

        foreach ($match->participants->where('status', MatchParticipant::STATUS_ACCEPTED) as $participant) {
            if ($participant->user?->email) {
                SafeMail::queue($participant->user->email, new MatchCancelled($match, $cancelReason));
            }
        }

        if ($host->isAdmin() && $match->user_id !== $host->id && $match->host?->email) {
            SafeMail::queue($match->host->email, new MatchCancelled($match, $cancelReason));
        }

        return $match;
    }

    /**
     * Người tham gia tự rút lui.
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
                throw ValidationException::withMessages(['user' => 'Bạn chưa tham gia trận đấu này.']);
            }

            $wasAccepted = $participant->status === MatchParticipant::STATUS_ACCEPTED;
            $participant->delete();

            if ($wasAccepted) {
                $match->decrement('joined_count');
                if ($match->status === MatchRequest::STATUS_FULL) {
                    $match->update(['status' => MatchRequest::STATUS_OPEN]);
                }
            }
        });
    }
}
