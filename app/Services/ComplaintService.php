<?php

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\DomainException;
use App\Exceptions\ForbiddenException;
use App\Mail\ComplaintFiled;
use App\Mail\ComplaintHandled;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ComplaintService
{
    /** Loại khiếu nại chỉ chủ sân dùng (về hành vi của khách); "other" dùng chung. */
    public const OWNER_CATEGORIES = ['property_damage', 'customer_misconduct', 'overtime'];

    /**
     * Khách khiếu nại chủ sân, hoặc chủ sân khiếu nại khách của mình. Khiếu nại gắn với đơn thì có bên bị khiếu nại:
     * bên đó được báo qua mail và phản hồi một lần trước khi admin quyết định.
     */
    /** @param  list<UploadedFile>  $photos  Ảnh chứng cứ (tối đa 3 mỗi bên). */
    public function create(array $data, ?User $user = null, array $photos = []): Complaint
    {
        $bookingId = $data['booking_id'] ?? null;
        $booking = null;
        $direction = 'customer_to_owner';
        $againstId = null;

        if ($bookingId) {
            $booking = Booking::with('sportsField')->find($bookingId);
            if (! $booking) {
                throw new DomainException('Đơn đặt sân được khiếu nại không tồn tại.');
            }

            $ownerId = $booking->sportsField?->field_owner_id;
            $isOwnerSide = $user && $ownerId === $user->id && $booking->user_id !== $user->id;
            if ($user && ! $isOwnerSide && $booking->user_id !== $user->id && ! $user->isAdmin()) {
                throw new ForbiddenException('Bạn chỉ có thể khiếu nại đơn đặt sân của chính mình hoặc đơn tại sân của bạn.');
            }

            if ($isOwnerSide) {
                if (! $booking->startsAt()->isPast()) {
                    throw new DomainException('Chưa đến giờ chơi nên chưa thể khiếu nại khách về đơn này.');
                }
                $direction = 'owner_to_customer';
                $againstId = $booking->user_id;
            } else {
                $againstId = $ownerId;
            }
        } elseif ($user && in_array($data['category'], self::OWNER_CATEGORIES, true)) {
            throw new DomainException('Loại khiếu nại này cần gắn với một đơn đặt sân.');
        }

        $ownerOnly = in_array($data['category'], self::OWNER_CATEGORIES, true);
        if ($direction === 'customer_to_owner' && $ownerOnly) {
            throw new DomainException('Loại khiếu nại này chỉ dành cho chủ sân.');
        }

        if ($data['category'] === 'wrong_no_show') {
            $this->assertNoShowAppealable($booking);
        }
        if ($photos && ! $user) {
            throw new DomainException('Vui lòng đăng nhập để đính kèm ảnh chứng cứ.');
        }

        // Mỗi người chỉ có một khiếu nại đang xử lý trên một đơn; gửi trùng chỉ làm admin xử lý lặp.
        if ($bookingId && Complaint::active()->where('booking_id', $bookingId)->where('user_id', $user?->id)->exists()) {
            throw new ConflictException('Đơn này đã có khiếu nại của bạn đang được xử lý. Vui lòng chờ kết quả.');
        }

        $complaint = Complaint::create([
            'user_id' => $user?->id,
            'direction' => $direction,
            'against_user_id' => $againstId,
            'booking_id' => $bookingId,
            'subject_type' => $data['subject_type'] ?? 'booking',
            'subject_id' => $data['subject_id'] ?? ($bookingId ? (string) $bookingId : null),
            'category' => $data['category'],
            'content' => $data['content'],
            'status' => 'open',
            'respond_by' => $againstId ? now()->addHours(config('booking.complaint_response_hours')) : null,
        ]);

        // Khiếu nại của khách làm đơn bị giữ khỏi kỳ chi trả cho chủ sân; khiếu nại của chủ sân thì không.
        if ($booking && $direction === 'customer_to_owner') {
            app(StatementService::class)->refreshFor($booking);
        }

        $this->storeAttachments($complaint, $user, $photos);

        $complaint->load(['user', 'booking', 'against', 'attachments']);
        if ($complaint->against?->email) {
            SafeMail::queue($complaint->against->email, new ComplaintFiled($complaint));
        }

        return $complaint;
    }

    /** Bên bị khiếu nại phản hồi đúng một lần khi khiếu nại còn đang xử lý. */
    /** @param  list<UploadedFile>  $photos */
    public function respond(Complaint $complaint, User $user, string $response, array $photos = []): Complaint
    {
        if ($complaint->against_user_id !== $user->id) {
            throw new ForbiddenException('Chỉ bên bị khiếu nại mới được phản hồi.');
        }
        if (! in_array($complaint->status, ['open', 'processing'], true)) {
            throw new ConflictException('Khiếu nại này đã đóng, không thể phản hồi.');
        }
        if ($complaint->response !== null) {
            throw new ConflictException('Bạn đã phản hồi khiếu nại này rồi.');
        }

        $this->storeAttachments($complaint, $user, $photos);
        $complaint->update(['response' => $response, 'responded_at' => now()]);

        return $complaint->load(['user', 'against', 'booking.sportsField', 'attachments']);
    }

    /** Chỉ khiếu nại "đã đến sân" cho đơn thật sự bị ghi vắng mặt và còn trong hạn kháng cáo. */
    private function assertNoShowAppealable(?Booking $booking): void
    {
        if (! $booking || $booking->no_show_at === null) {
            throw new DomainException('Đơn này không bị ghi nhận vắng mặt nên không thể khiếu nại loại này.');
        }

        $hours = config('booking.no_show_appeal_hours');
        if ($booking->no_show_at->addHours($hours)->isPast()) {
            throw new DomainException("Đã quá {$hours} giờ kể từ khi đơn bị ghi nhận vắng mặt nên không thể kháng cáo. Vui lòng liên hệ hỗ trợ.");
        }
    }

    /** Lưu ảnh chứng cứ của một bên; tối đa 3 ảnh mỗi bên trên một khiếu nại. */
    private function storeAttachments(Complaint $complaint, ?User $user, array $photos): void
    {
        if (! $photos || ! $user) {
            return;
        }

        $already = ComplaintAttachment::where('complaint_id', $complaint->id)->where('user_id', $user->id)->count();
        if ($already + count($photos) > 3) {
            throw new DomainException('Mỗi bên chỉ đính kèm tối đa 3 ảnh chứng cứ cho một khiếu nại.');
        }

        foreach ($photos as $photo) {
            ComplaintAttachment::create([
                'complaint_id' => $complaint->id,
                'user_id' => $user->id,
                'path' => $photo->store("complaints/{$complaint->id}", 'local'),
                'original_name' => $photo->getClientOriginalName(),
            ]);
        }
    }

    /** Khiếu nại liên quan tới người dùng: do họ gửi hoặc họ là bên bị khiếu nại. */
    public function involving(User $user): LengthAwarePaginator
    {
        return Complaint::with(['user', 'against', 'attachments', 'booking.sportsField'])
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('against_user_id', $user->id))
            ->latest()
            ->paginate(15);
    }

    /**
     * Số liệu giúp admin cân nhắc: chủ sân huỷ đơn đã thanh toán, vắng mặt, và các khiếu nại đã nhận.
     *
     * @return array{owner_cancellations_90d: int, no_shows: int, complaints_received: int, complaints_upheld: int}
     */
    public function partyStats(User $user): array
    {
        return [
            'owner_cancellations_90d' => Booking::where('cancelled_by', 'field_owner')
                ->where('payment_status', 'paid')
                ->where('cancelled_at', '>=', now()->subDays(90))
                ->whereHas('sportsField', fn ($f) => $f->where('field_owner_id', $user->id)->withTrashed())
                ->count(),
            'no_shows' => Booking::where('user_id', $user->id)->whereNotNull('no_show_at')->count(),
            'complaints_received' => Complaint::where('against_user_id', $user->id)->count(),
            'complaints_upheld' => Complaint::where('against_user_id', $user->id)->where('status', 'resolved')->count(),
        ];
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return Complaint::with(['user', 'against', 'attachments', 'booking.sportsField', 'handler'])
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
        return Complaint::with(['booking.sportsField', 'user', 'against', 'attachments'])
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('against_user_id', $user->id))
            ->latest()
            ->paginate(15);
    }

    /**
     * Admin xử lý khiếu nại. Khi chấp nhận (`resolved`) có thể kèm kết quả thực tế:
     * - khiếu nại của khách: hoàn thêm tiền và/hoặc xác nhận khách đã đến sân (gỡ vắng mặt, trả điểm uy tín);
     * - khiếu nại của chủ sân: trừ điểm uy tín của khách.
     * Khiếu nại đã đóng (`resolved`/`rejected`) không sửa lại được.
     */
    public function handle(
        Complaint $complaint,
        string $status,
        ?string $resolution,
        User $admin,
        ?float $refundAmount = null,
        bool $confirmAttendance = false,
        ?int $trustPenalty = null,
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

        $customerSide = $complaint->direction === 'customer_to_owner';
        $moneyOrAttendance = ($refundAmount ?? 0) > 0 || $confirmAttendance;
        $penalty = ($trustPenalty ?? 0) > 0;
        if (($moneyOrAttendance || $penalty) && $status !== 'resolved') {
            throw new DomainException('Chỉ có thể hoàn tiền, gỡ vắng mặt hoặc trừ điểm khi chấp nhận khiếu nại.');
        }
        if (($moneyOrAttendance || $penalty) && ! $complaint->booking_id) {
            throw new DomainException('Khiếu nại này không gắn với đơn đặt sân nên không có kết quả kèm theo nào áp dụng được.');
        }
        if ($moneyOrAttendance && ! $customerSide) {
            throw new DomainException('Hoàn tiền và gỡ vắng mặt chỉ áp dụng cho khiếu nại của khách.');
        }
        if ($penalty && $customerSide) {
            throw new DomainException('Trừ điểm uy tín chỉ áp dụng cho khiếu nại của chủ sân về khách.');
        }

        DB::transaction(function () use ($complaint, $status, $resolution, $admin, $refundAmount, $confirmAttendance, $trustPenalty) {
            $booking = $complaint->booking_id ? Booking::lockForUpdate()->find($complaint->booking_id) : null;
            $resolved = $status === 'resolved';

            if ($resolved && ($refundAmount ?? 0) > 0) {
                $this->refundBooking($booking, $refundAmount);
            }
            if ($resolved && $confirmAttendance) {
                $this->confirmAttendance($booking);
            }
            if ($resolved && ($trustPenalty ?? 0) > 0 && $booking?->customer) {
                TrustScoreService::penalizeMisconduct($booking->customer, $trustPenalty, $booking);
            }

            $complaint->update([
                'status' => $status,
                'resolution' => $resolution,
                'refund_amount' => $resolved ? $refundAmount : null,
                'attendance_confirmed' => $resolved && $confirmAttendance,
                'trust_penalty' => $resolved && ($trustPenalty ?? 0) > 0 ? $trustPenalty : null,
                'handled_by' => $admin->id,
                'handled_at' => now(),
            ]);

            AdminLog::record('handle_complaint', $complaint, [
                'status' => $status,
                'resolution' => $resolution,
                'refund_amount' => $complaint->refund_amount,
                'attendance_confirmed' => $complaint->attendance_confirmed,
                'trust_penalty' => $complaint->trust_penalty,
            ], $admin);
        });

        // Đóng khiếu nại (nhả đơn về kỳ chi trả) hoặc có hoàn tiền: lập lại kỳ chưa chi trả của đơn.
        if ($customerSide && $complaint->booking) {
            app(StatementService::class)->refreshFor($complaint->booking->refresh());
        }

        // Báo kết quả cho cả bên khiếu nại lẫn bên bị khiếu nại.
        $complaint->loadMissing(['user', 'against']);
        if ($complaint->user?->email) {
            SafeMail::queue($complaint->user->email, new ComplaintHandled($complaint));
        }
        if ($status !== 'processing' && $complaint->against?->email) {
            SafeMail::queue($complaint->against->email, new ComplaintHandled($complaint, asRespondent: true));
        }

        return $complaint->fresh(['user', 'against', 'attachments', 'booking.sportsField', 'handler']);
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

        $booking->update(['refund_amount' => $already + $amount]);
        app(WalletService::class)->refundBooking($booking, $amount, "Hoàn tiền theo khiếu nại đơn #BK-{$booking->id}");
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
