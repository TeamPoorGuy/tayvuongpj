<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Mail\ComplaintHandled;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    public function create(array $data, ?User $user = null): Complaint
    {
        $bookingId = $data['booking_id'] ?? null;
        $booking = null;
        if ($bookingId) {
            $booking = Booking::find($bookingId);
            if (! $booking) {
                throw new DomainException('Đơn đặt sân được khiếu nại không tồn tại.');
            }
            if ($user && $booking->user_id !== $user->id && ! $user->isAdmin()) {
                throw new ForbiddenException('Bạn chỉ có thể khiếu nại đơn đặt sân của chính mình.');
            }
        }

        // Mỗi đơn chỉ có một khiếu nại đang xử lý; gửi trùng chỉ làm admin xử lý lặp.
        if ($bookingId && Complaint::active()->where('booking_id', $bookingId)->exists()) {
            throw new ConflictException('Đơn này đã có khiếu nại đang được xử lý. Vui lòng chờ kết quả.');
        }

        $complaint = Complaint::create([
            'user_id' => $user?->id,
            'booking_id' => $bookingId,
            'subject_type' => $data['subject_type'] ?? 'booking',
            'subject_id' => $data['subject_id'] ?? ($bookingId ? (string) $bookingId : null),
            'category' => $data['category'],
            'content' => $data['content'],
            'status' => 'open',
        ]);

        // Đơn đang tranh chấp bị giữ lại khỏi kỳ chi trả cho chủ sân.
        if ($bookingId) {
            app(StatementService::class)->refreshFor($booking);
        }

        return $complaint->load(['user', 'booking']);
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

    /**
     * Admin xử lý khiếu nại. Khi chấp nhận (`resolved`) có thể kèm kết quả thực tế:
     * hoàn thêm tiền cho khách và/hoặc xác nhận khách đã đến sân (gỡ ghi nhận vắng mặt, trả điểm uy tín).
     * Khiếu nại đã đóng (`resolved`/`rejected`) không sửa lại được.
     */
    public function handle(
        Complaint $complaint,
        string $status,
        ?string $resolution,
        User $admin,
        ?float $refundAmount = null,
        bool $confirmAttendance = false,
    ): Complaint {
        if (! in_array($status, ['processing', 'resolved', 'rejected'], true)) {
            throw new DomainException('Trạng thái xử lý không hợp lệ.');
        }
        if (in_array($complaint->status, ['resolved', 'rejected'], true)) {
            throw new ConflictException('Khiếu nại này đã đóng, không thể cập nhật lại.');
        }
        if ($status !== 'processing' && blank($resolution)) {
            throw new DomainException('Vui lòng ghi phương án giải quyết trước khi đóng khiếu nại.');
        }
        $hasEffect = ($refundAmount ?? 0) > 0 || $confirmAttendance;
        if ($hasEffect && $status !== 'resolved') {
            throw new DomainException('Chỉ có thể hoàn tiền hoặc gỡ vắng mặt khi chấp nhận khiếu nại.');
        }
        if ($hasEffect && ! $complaint->booking_id) {
            throw new DomainException('Khiếu nại này không gắn với đơn đặt sân nên không có gì để hoàn hoặc gỡ.');
        }

        DB::transaction(function () use ($complaint, $status, $resolution, $admin, $refundAmount, $confirmAttendance) {
            $booking = $complaint->booking_id ? Booking::lockForUpdate()->find($complaint->booking_id) : null;

            if ($status === 'resolved' && ($refundAmount ?? 0) > 0) {
                $this->refundBooking($booking, $refundAmount);
            }
            if ($status === 'resolved' && $confirmAttendance) {
                $this->confirmAttendance($booking);
            }

            $complaint->update([
                'status' => $status,
                'resolution' => $resolution,
                'refund_amount' => $status === 'resolved' ? $refundAmount : null,
                'attendance_confirmed' => $status === 'resolved' && $confirmAttendance,
                'handled_by' => $admin->id,
                'handled_at' => now(),
            ]);

            AdminLog::record('handle_complaint', $complaint, [
                'status' => $status,
                'resolution' => $resolution,
                'refund_amount' => $complaint->refund_amount,
                'attendance_confirmed' => $complaint->attendance_confirmed,
            ], $admin);
        });

        // Đóng khiếu nại (nhả đơn về kỳ chi trả) hoặc có hoàn tiền: lập lại kỳ chưa chi trả của đơn.
        if ($complaint->booking) {
            app(StatementService::class)->refreshFor($complaint->booking->refresh());
        }

        if ($complaint->user?->email) {
            SafeMail::queue($complaint->user->email, new ComplaintHandled($complaint));
        }

        return $complaint->fresh(['user', 'booking.sportsField', 'handler']);
    }

    /** Hoàn thêm cho khách, cộng dồn vào khoản đã hoàn của đơn và không vượt số khách đã trả. */
    private function refundBooking(?Booking $booking, float $amount): void
    {
        if (! $booking || ! $booking->payment_id || $booking->payment_status !== 'paid') {
            throw new DomainException('Đơn này không có khoản thanh toán qua SportHub để hoàn.');
        }

        $already = in_array($booking->refund_status, ['pending', 'done'], true)
            ? (float) ($booking->refund_amount ?? $booking->payment_amount)
            : 0.0;
        $room = (float) $booking->payment_amount - $already;
        if ($room <= 0) {
            throw new DomainException('Đơn này đã được hoàn đủ số tiền khách đã thanh toán.');
        }
        if ($amount > $room) {
            throw new DomainException('Số tiền hoàn tối đa còn lại của đơn này là '.number_format($room, 0, ',', '.').'đ.');
        }

        $booking->update([
            'refund_status' => 'pending',
            'refund_amount' => $already + $amount,
            'refunded_at' => null,
        ]);
    }

    /** Khách thực tế đã đến sân: gỡ vắng mặt, ghi nhận có mặt và trả điểm uy tín đã trừ. */
    private function confirmAttendance(?Booking $booking): void
    {
        if (! $booking || $booking->no_show_at === null) {
            throw new DomainException('Đơn này không bị ghi nhận vắng mặt nên không có gì để gỡ.');
        }

        $booking->update([
            'no_show_at' => null,
            'checked_in_at' => $booking->checked_in_at ?? now(),
        ]);

        if ($booking->customer) {
            TrustScoreService::restoreNoShow($booking->customer, $booking);
        }
    }
}
