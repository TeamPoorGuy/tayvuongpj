<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Mail\ComplaintHandled;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ComplaintService
{
    public function create(array $data, ?User $user = null): Complaint
    {
        $bookingId = $data['booking_id'] ?? null;
        if ($bookingId) {
            $booking = Booking::find($bookingId);
            if (! $booking) {
                throw new DomainException('Đơn đặt sân được khiếu nại không tồn tại.');
            }
            if ($user && $booking->user_id !== $user->id && ! $user->isAdmin()) {
                throw new ForbiddenException('Bạn chỉ có thể khiếu nại đơn đặt sân của chính mình.');
            }
        }

        return Complaint::create([
            'user_id' => $user?->id,
            'booking_id' => $bookingId,
            'subject_type' => $data['subject_type'] ?? 'booking',
            'subject_id' => $data['subject_id'] ?? ($bookingId ? (string) $bookingId : null),
            'category' => $data['category'],
            'content' => $data['content'],
            'status' => 'open',
        ])->load(['user', 'booking']);
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return Complaint::with(['user', 'booking.sportsField', 'handler'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($inner) => $inner
                ->where('content', 'like', '%'.addcslashes($keyword, '%_\\').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($keyword, '%_\\').'%')
                    ->orWhere('email', 'like', '%'.addcslashes($keyword, '%_\\').'%'))))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function userComplaints(User $user): LengthAwarePaginator
    {
        return Complaint::with(['booking.sportsField'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(15);
    }

    public function handle(Complaint $complaint, string $status, ?string $resolution, User $admin): Complaint
    {
        if (! in_array($status, ['processing', 'resolved', 'rejected'], true)) {
            throw new DomainException('Trạng thái xử lý không hợp lệ.');
        }

        $complaint->update([
            'status' => $status,
            'resolution' => $resolution,
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);

        AdminLog::record('handle_complaint', $complaint, [
            'status' => $status,
            'resolution' => $resolution,
        ], $admin);

        if ($complaint->user?->email) {
            SafeMail::queue($complaint->user->email, new ComplaintHandled($complaint));
        }

        return $complaint->fresh(['user', 'booking.sportsField', 'handler']);
    }
}
