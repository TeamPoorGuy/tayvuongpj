<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\FieldStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Mail\AccountStatusChanged;
use App\Mail\BookingStatusChanged;
use App\Mail\MatchCancelled;
use App\Mail\OwnerApplicationVerified;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\Review;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminService
{
    public function dashboard(string $period = 'this_month'): array
    {
        if ($period === 'last_month') {
            $start = now()->subMonthNoOverflow()->startOfMonth();
            $end = now()->subMonthNoOverflow()->endOfMonth();
        } else {
            $period = 'this_month';
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        $allGmv = (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_price');

        // Period query & bookings
        $periodBookings = Booking::whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])->get();

        $periodConfirmedOrCompleted = $periodBookings->filter(fn ($b) => in_array($b->status?->value ?? $b->status, ['confirmed', 'completed'], true));
        $periodGmv = (float) $periodConfirmedOrCompleted->sum('total_price');
        $periodPaidAmount = (float) $periodBookings->filter(fn ($b) => in_array($b->payment_status, ['paid', 'reported'], true))->sum('payment_amount');

        // Tính hoa hồng tại chỗ bằng computeCommission() nếu chưa chốt đối soát (commission_amount == null)
        $periodCommissionAccrued = (float) $periodConfirmedOrCompleted->sum(function (Booking $b) {
            return $b->commission_amount !== null ? (float) $b->commission_amount : $b->computeCommission();
        });

        $periodTotalBookings = $periodBookings->count();
        $periodCancelled = $periodBookings->filter(fn ($b) => ($b->status?->value ?? $b->status) === 'cancelled')->count();
        $periodNoShow = $periodBookings->filter(fn ($b) => $b->no_show_at !== null)->count();

        $cancelRate = $periodTotalBookings > 0 ? round(($periodCancelled / $periodTotalBookings) * 100, 1) : 0;
        $noShowRate = $periodTotalBookings > 0 ? round(($periodNoShow / $periodTotalBookings) * 100, 1) : 0;

        $newUsers = User::where('role', UserRole::Customer->value)->whereBetween('created_at', [$start, $end])->count();
        $newOwners = FieldOwnerProfile::where('verification_status', VerificationStatus::Approved)->whereBetween('verified_at', [$start, $end])->count();

        $commissionCollected = (float) \App\Models\OwnerStatement::where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('commission_amount');
        // Hoa hồng của các kỳ chưa chi trả (SportHub đang giữ trong tiền thu hộ).
        $totalCommissionDebt = (float) \App\Models\OwnerStatement::where('status', 'unpaid')
            ->sum('commission_amount');

        // Daily series for SVG chart
        $dailySeries = [];
        $cursor = $start->copy();
        $chartEnd = $end->copy()->min($start->copy()->addDays(31));

        $chartBookings = Booking::whereBetween('booking_date', [$start->toDateString(), $chartEnd->toDateString()])
            ->get()
            ->groupBy(fn ($b) => Carbon::parse($b->booking_date)->toDateString());

        while ($cursor->lte($chartEnd)) {
            $dateKey = $cursor->toDateString();
            $dayBookings = $chartBookings->get($dateKey, collect());
            $dayConfirmedOrCompleted = $dayBookings->filter(fn ($b) => in_array($b->status?->value ?? $b->status, ['confirmed', 'completed'], true));

            $dayGmv = (float) $dayConfirmedOrCompleted->sum('total_price');
            $dayCommission = (float) $dayConfirmedOrCompleted->sum(function (Booking $b) {
                return $b->commission_amount !== null ? (float) $b->commission_amount : $b->computeCommission();
            });

            $dailySeries[] = [
                'date' => $dateKey,
                'label' => $cursor->format('d/m'),
                'gmv' => $dayGmv,
                'commission' => $dayCommission,
                'booking_count' => $dayBookings->count(),
            ];
            $cursor->addDay();
        }

        // Top fields in period
        $topFields = SportsField::with(['primaryImage', 'fieldType.sportCategory'])
            ->withCount(['bookings' => fn ($q) => $q->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])->whereIn('status', ['confirmed', 'completed'])])
            ->withSum(['bookings' => fn ($q) => $q->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])->whereIn('status', ['confirmed', 'completed'])], 'total_price')
            ->orderByDesc('bookings_count')
            ->take(5)
            ->get();

        // Action items (pending queues)
        $actionQueue = [
            'pending_owners' => FieldOwnerProfile::where('verification_status', VerificationStatus::Pending)->count(),
            'pending_fields' => SportsField::where('status', 'pending')->count(),
            'open_complaints' => \App\Models\Complaint::where('status', 'open')->count(),
            'pending_withdrawals' => \App\Models\Withdrawal::where('status', 'pending')->count(),
        ];

        return [
            'period' => $period,
            'from_date' => $start->toDateString(),
            'to_date' => $end->toDateString(),
            'kpis' => [
                'gmv' => $periodGmv,
                'paid_amount' => $periodPaidAmount,
                'commission_accrued' => $periodCommissionAccrued,
                'commission_collected' => $commissionCollected,
                'commission_debt' => $totalCommissionDebt,
                'total_bookings' => $periodTotalBookings,
                'cancelled_bookings' => $periodCancelled,
                'cancel_rate' => $cancelRate,
                'no_show_bookings' => $periodNoShow,
                'no_show_rate' => $noShowRate,
                'new_users' => $newUsers,
                'new_owners' => $newOwners,
            ],
            'daily_series' => $dailySeries,
            'top_fields' => $topFields,
            'action_queue' => $actionQueue,

            // Keys used by statements page
            'total_gmv' => $allGmv,
            'total_commission_collected' => (float) \App\Models\OwnerStatement::where('status', 'paid')->sum('commission_amount'),
            'total_commission_pending' => $totalCommissionDebt,
        ];
    }

    public function users(array $filters): LengthAwarePaginator
    {
        // Chủ sân có role=customer; vai trò hiển thị/lọc suy ra từ hồ sơ đã duyệt.
        $approvedOwner = fn ($query) => $query->where('verification_status', VerificationStatus::Approved);

        return User::where('role', '!=', UserRole::Admin->value)
            ->withExists(['fieldOwnerProfile as is_field_owner' => $approvedOwner])
            ->when($filters['role'] ?? null, fn ($query, $role) => $role === 'field_owner'
                ? $query->whereHas('fieldOwnerProfile', $approvedOwner)
                : $query->whereDoesntHave('fieldOwnerProfile', $approvedOwner))
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.addcslashes($keyword, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($keyword, '%_\\').'%')
                ->orWhere('phone', 'like', '%'.addcslashes($keyword, '%_\\').'%')))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function cancelFutureBookings(iterable $bookings, string $reason): int
    {
        $count = 0;
        foreach ($bookings as $booking) {
            if ($booking->booking_date->isToday() && $booking->endsAt()->isPast()) {
                continue;
            }

            $booking->markCancelled('system', $reason);

            if ($booking->customer?->email) {
                SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
            }

            app(WaitlistService::class)->handleBookingFreed($booking);

            $count++;
        }

        return $count;
    }

    public function toggleUser(User $user, ?string $reason = null, ?User $admin = null): User
    {
        if ($user->role === UserRole::Admin->value) {
            throw new ForbiddenException('Không thể khóa tài khoản quản trị viên.');
        }

        if ($user->is_active && empty(trim((string) $reason))) {
            throw new ConflictException('Vui lòng cung cấp lý do khi khóa tài khoản người dùng.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        AdminLog::record('toggle_user', $user, [
            'is_active' => $user->is_active,
            'reason' => $reason,
        ], $admin);

        if ($user->email) {
            SafeMail::queue($user->email, new AccountStatusChanged($user, (bool) $user->is_active, $reason));
        }

        if (! $user->is_active) {
            $cancelReason = $reason ? "Tài khoản bị tạm khoá: {$reason}" : 'Tài khoản của bạn đã bị tạm khoá bởi quản trị viên.';
            $customerBookings = Booking::with(['customer', 'sportsField', 'timeSlot'])
                ->where('user_id', $user->id)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->whereDate('booking_date', '>=', today())
                ->get();
            $this->cancelFutureBookings($customerBookings, $cancelReason);

            $fieldIds = SportsField::withTrashed()->where('field_owner_id', $user->id)->pluck('id');
            if ($fieldIds->isNotEmpty()) {
                $ownerBookings = Booking::with(['customer', 'sportsField', 'timeSlot'])
                    ->whereIn('sports_field_id', $fieldIds)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->whereDate('booking_date', '>=', today())
                    ->get();
                $this->cancelFutureBookings($ownerBookings, $reason ? "Chủ sân đã bị tạm khoá tài khoản: {$reason}" : 'Chủ sân đã bị tạm khoá tài khoản bởi quản trị viên.');
            }
        }

        return $user->refresh();
    }


    public function userDetail(User $user): array
    {
        return [
            'user' => $user->load('fieldOwnerProfile'),
            'stats' => [
                'total_bookings' => Booking::where('user_id', $user->id)->count(),
                'cancelled_bookings' => Booking::where('user_id', $user->id)->where('status', BookingStatus::Cancelled->value)->count(),
                'no_show_bookings' => Booking::where('user_id', $user->id)->whereNotNull('no_show_at')->count(),
                'complaints_count' => \App\Models\Complaint::where('user_id', $user->id)->count(),
            ],
            'recent_bookings' => Booking::with(['sportsField', 'timeSlot'])
                ->where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get(),
            'recent_trust_scores' => $user->trustScoreLogs()
                ->latest()
                ->take(5)
                ->get(),
        ];
    }

    public function adminLogs(array $filters = []): LengthAwarePaginator
    {
        return AdminLog::with('admin')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['admin_id'] ?? null, fn ($query, $adminId) => $query->where('admin_id', $adminId))
            ->when($filters['from_date'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to_date'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function ownerProfiles(?string $status = null): LengthAwarePaginator
    {
        return FieldOwnerProfile::with(['user', 'documents'])
            ->when($status, fn ($query) => $query->where('verification_status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function verifyOwner(FieldOwnerProfile $profile, string $status, ?string $reason, User $admin): FieldOwnerProfile
    {
        $next = VerificationStatus::from($status);
        if (! $profile->verification_status->canTransitionTo($next)) {
            throw new ConflictException('Hồ sơ này đã được xử lý hoặc không thể chuyển sang trạng thái này.');
        }

        $profile->update([
            'verification_status' => $next,
            'rejection_reason' => $next === VerificationStatus::Approved ? null : $reason,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        if (in_array($next, [VerificationStatus::Rejected, VerificationStatus::Suspended], true)) {
            $fieldIds = SportsField::withTrashed()->where('field_owner_id', $profile->user_id)->pluck('id');
            if ($fieldIds->isNotEmpty()) {
                $ownerBookings = Booking::with(['customer', 'sportsField', 'timeSlot'])
                    ->whereIn('sports_field_id', $fieldIds)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->whereDate('booking_date', '>=', today())
                    ->get();
                $this->cancelFutureBookings(
                    $ownerBookings,
                    $reason
                        ? ($next === VerificationStatus::Suspended ? "Chủ sân đã bị đình chỉ: {$reason}" : "Hồ sơ chủ sân đã bị từ chối: {$reason}")
                        : ($next === VerificationStatus::Suspended ? 'Chủ sân đã bị đình chỉ hoạt động bởi quản trị viên.' : 'Hồ sơ chủ sân đã bị từ chối.')
                );
            }
        }

        $profile->refresh()->load(['user', 'documents']);

        AdminLog::record('verify_owner', $profile, [
            'status' => $next->value,
            'reason' => $reason,
        ], $admin);

        // Gửi mail qua queue; lỗi mail không được chặn việc duyệt hồ sơ.
        SafeMail::queue($profile->user->email, new OwnerApplicationVerified($profile));

        return $profile;
    }

    private function like(string $keyword): string
    {
        return '%'.addcslashes($keyword, '%_\\').'%';
    }

    public function fields(?string $status, ?string $keyword = null, ?bool $isActive = null): LengthAwarePaginator
    {
        return SportsField::with(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage'])
            ->withRating()
            ->when($status, function ($query, $status) {
                if ($status === 'inactive') {
                    $query->where(fn ($q) => $q->where('status', 'inactive')->orWhere('is_active', false));
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', $this->like($keyword))
                ->orWhere('address', 'like', $this->like($keyword))
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function verifyField(SportsField $field, string $status, ?string $reason = null, bool $cancelFutureBookings = false): SportsField
    {
        $next = FieldStatus::from($status);
        $currentStatus = $field->status instanceof FieldStatus ? $field->status : FieldStatus::from($field->status);

        if (! $currentStatus->canTransitionTo($next)) {
            throw new ConflictException('Sân này không thể chuyển sang trạng thái này.');
        }

        DB::transaction(function () use ($field, $next, $reason, $cancelFutureBookings) {
            $field->update([
                'status' => $next->value,
                'rejection_reason' => $next === FieldStatus::Rejected ? $reason : null,
            ]);

            if ($next === FieldStatus::Rejected) {
                // Sân đã bị gỡ: hàng đợi của sân này không còn ý nghĩa, đóng để khách không nhận lời mời giữ chỗ vô ích.
                BookingWaitlist::where('sports_field_id', $field->id)
                    ->whereIn('status', ['waiting', 'offered'])
                    ->update(['status' => 'cancelled']);

                // Sân đã bị gỡ: đóng các phòng tìm đối sắp tới gắn với sân này và gửi mail cho chủ phòng + người đã duyệt.
                $affectedMatches = MatchRequest::with(['host', 'participants.user'])
                    ->where('sports_field_id', $field->id)
                    ->whereIn('status', ['open', 'full'])
                    ->whereDate('play_date', '>=', today())
                    ->get();

                foreach ($affectedMatches as $match) {
                    if ($match->play_date->isToday() && Carbon::parse($match->end_time)->isPast()) {
                        continue;
                    }

                    $match->update(['status' => 'cancelled']);

                    $matchReason = $reason ? "Sân thi đấu đã bị gỡ khỏi hệ thống: {$reason}" : 'Sân thi đấu đã bị gỡ khỏi hệ thống.';

                    if ($match->host?->email) {
                        SafeMail::queue($match->host->email, new MatchCancelled($match, $matchReason));
                    }
                    foreach ($match->participants->where('status', 'accepted') as $participant) {
                        if ($participant->user?->email) {
                            SafeMail::queue($participant->user->email, new MatchCancelled($match, $matchReason));
                        }
                    }
                }
            }

            if ($next === FieldStatus::Rejected && $cancelFutureBookings) {
                $cancelReason = $reason ? "Sân đã bị gỡ khỏi hệ thống: {$reason}" : 'Sân đã bị gỡ khỏi hệ thống.';

                $futureBookings = Booking::with(['customer', 'sportsField', 'timeSlot'])
                    ->where('sports_field_id', $field->id)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->whereDate('booking_date', '>=', today())
                    ->get();

                $this->cancelFutureBookings($futureBookings, $cancelReason);
            }
        });

        AdminLog::record('verify_field', $field, [
            'status' => $next->value,
            'reason' => $reason,
            'cancel_future_bookings' => $cancelFutureBookings,
        ]);

        return $field->refresh()->load(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage', 'reviews']);
    }

    public function bookings(?string $status, ?string $keyword = null, ?string $date = null): LengthAwarePaginator
    {
        return Booking::with(Booking::resourceRelations(withCustomer: true))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($date, fn ($query) => $query->whereDate('booking_date', $date))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('customer', fn ($c) => $c->where('name', 'like', $this->like($keyword))
                    ->orWhere('email', 'like', $this->like($keyword))
                    ->orWhere('phone', 'like', $this->like($keyword)))
                ->orWhereHas('sportsField', fn ($f) => $f->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function cancelBookingAsAdmin(Booking $booking, string $reason, User $admin): Booking
    {
        if (in_array($booking->status, [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::Rejected], true)) {
            throw new ConflictException('Đơn đặt sân này đã kết thúc hoặc đã bị huỷ trước đó.');
        }

        $booking->markCancelled('admin', $reason);

        app(WaitlistService::class)->handleBookingFreed($booking);

        AdminLog::record('admin_cancel_booking', $booking, [
            'reason' => $reason,
            'refund_status' => $booking->refund_status,
            'refund_amount' => $booking->refund_amount,
        ], $admin);

        if ($booking->customer?->email) {
            SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
        }

        return $booking->refresh()->load(Booking::resourceRelations(withCustomer: true));
    }

    public function exportBookingsCsv(?string $status = null, ?string $keyword = null, ?string $date = null): string
    {
        $bookings = Booking::with(Booking::resourceRelations(withCustomer: true))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($date, fn ($query) => $query->whereDate('booking_date', $date))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('customer', fn ($c) => $c->where('name', 'like', $this->like($keyword))
                    ->orWhere('email', 'like', $this->like($keyword))
                    ->orWhere('phone', 'like', $this->like($keyword)))
                ->orWhereHas('sportsField', fn ($f) => $f->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->take(2000)
            ->get();

        $csv = "\xEF\xBB\xBF";
        $csv .= "Mã đơn,Khách hàng,Số điện thoại,Sân thể thao,Ngày chơi,Khung giờ,Tổng tiền,Trạng thái đơn,Thanh toán,Hoàn tiền,Số tiền hoàn,Ngày tạo\n";

        foreach ($bookings as $b) {
            $statusLabel = match ($b->status instanceof \BackedEnum ? $b->status->value : $b->status) {
                'pending' => 'Chờ duyệt',
                'confirmed' => 'Đã duyệt',
                'completed' => 'Hoàn thành',
                'cancelled' => 'Đã huỷ',
                'rejected' => 'Từ chối',
                default => (string) $b->status,
            };

            $payLabel = match ($b->payment_status) {
                'unpaid' => 'Chưa thanh toán',
                'awaiting' => 'Chờ chuyển khoản',
                'reported' => 'Đã báo chuyển',
                'paid' => 'Đã thanh toán',
                default => (string) $b->payment_status,
            };

            $refundLabel = match ($b->refund_status) {
                'pending' => 'Chờ hoàn tiền',
                'done' => 'Đã hoàn tiền',
                'waived' => 'Miễn/Không hoàn',
                default => 'Không',
            };

            $line = [
                '#BK-'.str_pad((string) $b->id, 5, '0', STR_PAD_LEFT),
                '"'.str_replace('"', '""', $b->customer?->name ?? 'N/A').'"',
                '"'.str_replace('"', '""', $b->customer?->phone ?? 'N/A').'"',
                '"'.str_replace('"', '""', $b->sportsField?->name ?? 'N/A').'"',
                $b->booking_date->toDateString(),
                ($b->start_time ? substr($b->start_time, 0, 5).'-'.substr($b->end_time, 0, 5) : 'N/A'),
                (int) $b->total_price,
                '"'.$statusLabel.'"',
                '"'.$payLabel.'"',
                '"'.$refundLabel.'"',
                (int) ($b->refund_amount ?? 0),
                $b->created_at->format('Y-m-d H:i:s'),
            ];

            $csv .= implode(',', $line)."\n";
        }

        return $csv;
    }

    public function matches(?string $status, ?string $keyword = null): LengthAwarePaginator
    {
        return MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', $this->like($keyword))
                ->orWhereHas('host', fn ($host) => $host->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function cancelMatch(int $id, ?string $reason, User $admin): MatchRequest
    {
        $match = app(MatchmakingService::class)->cancel($admin, $id, $reason);
        AdminLog::record('cancel_match', $match, ['reason' => $reason], $admin);

        return $match;
    }

    public function reviews(?int $rating = null): LengthAwarePaginator
    {
        return Review::with(['user', 'sportsField'])
            ->when($rating, fn ($query) => $query->where('rating', $rating))
            ->latest()
            ->paginate(15);
    }

    public function toggleReview(Review $review): Review
    {
        $review->update(['is_visible' => ! $review->is_visible]);
        AdminLog::record('toggle_review', $review, ['is_visible' => $review->is_visible]);

        return $review->refresh()->load(['user', 'sportsField']);
    }

    public function categories(): Collection
    {
        return SportCategory::with('fieldTypes')->withCount('fieldTypes')->latest()->get();
    }

    public function createCategory(array $data): SportCategory
    {
        $category = SportCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'] ?? '⚽',
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ])->load('fieldTypes')->loadCount('fieldTypes');

        AdminLog::record('create_category', $category, ['name' => $category->name]);

        return $category;
    }

    public function updateCategory(SportCategory $category, array $data): SportCategory
    {
        // Giữ nguyên slug để link /fields?category=... không bị gãy.
        $category->update($data);
        AdminLog::record('update_category', $category, $data);

        return $category->load('fieldTypes')->loadCount('fieldTypes');
    }

    public function updateFieldType(FieldType $type, array $data): FieldType
    {
        $type->update($data);
        AdminLog::record('update_field_type', $type, $data);

        return $type->load('sportCategory');
    }

    public function createFieldType(array $data): FieldType
    {
        $type = FieldType::create([
            'sport_category_id' => $data['sport_category_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ])->load('sportCategory');

        AdminLog::record('create_field_type', $type, ['name' => $type->name]);

        return $type;
    }

    public function userTrustScoreLogs(User $user): LengthAwarePaginator
    {
        return $user->trustScoreLogs()
            ->with('booking.sportsField')
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }
}
