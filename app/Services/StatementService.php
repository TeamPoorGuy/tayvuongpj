<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Mail\StatementReminder;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\OwnerStatement;
use App\Models\SportsField;
use App\Models\User;
use App\Support\SafeMail;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatementService
{
    /**
     * Lập bảng đối soát hoa hồng cho một kỳ (YYYY-MM).
     *
     * @return list<OwnerStatement>
     */
    public function generate(string $period, ?int $ownerProfileId = null): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new ConflictException('Định dạng kỳ đối soát không hợp lệ. Vui lòng dùng YYYY-MM.');
        }

        $periodDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        if ($periodDate->isAfter(now()->startOfMonth())) {
            throw new ConflictException('Không thể sinh bảng đối soát cho các kỳ trong tương lai.');
        }

        $startDate = $periodDate->toDateString();
        $endDate = $periodDate->copy()->endOfMonth()->toDateString();

        $dueDays = Settings::getInt('statement_due_days', 7);
        $dueAt = Carbon::createFromFormat('Y-m', $period)->addMonth()->startOfMonth()->addDays($dueDays - 1)->endOfDay();

        $profiles = FieldOwnerProfile::query()
            ->when($ownerProfileId, fn ($q) => $q->whereKey($ownerProfileId))
            ->where('verification_status', 'approved')
            ->get();

        $createdStatements = [];

        foreach ($profiles as $profile) {
            $statement = DB::transaction(function () use ($profile, $period, $startDate, $endDate, $dueAt) {
                $statement = OwnerStatement::where('owner_profile_id', $profile->id)
                    ->where('period', $period)
                    ->first();

                // Đã thanh toán, miễn phí hoặc chủ sân đang báo chuyển tiền thì giữ nguyên, không ghi đè số tiền
                if ($statement && in_array($statement->status, ['paid', 'waived', 'reported'], true)) {
                    return $statement;
                }

                $fieldIds = SportsField::withTrashed()->where('field_owner_id', $profile->user_id)->pluck('id');

                $bookings = Booking::whereIn('sports_field_id', $fieldIds)
                    ->whereDate('booking_date', '>=', $startDate)
                    ->whereDate('booking_date', '<=', $endDate)
                    ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Cancelled->value])
                    ->get();

                $billableBookings = $bookings->filter(fn (Booking $b) => $b->commissionBase() > 0);

                $totalGmv = 0.0;
                $totalCommission = 0.0;

                foreach ($billableBookings as $b) {
                    $totalGmv += (float) $b->total_price;
                    $totalCommission += $b->computeCommission();
                }

                // Kỳ có phí hoa hồng = 0 (không phát sinh phí) thì tự động hoàn tất (paid) để không khoá chủ sân
                $status = $totalCommission > 0 ? 'unpaid' : 'paid';
                $paidAt = $totalCommission > 0 ? null : now();

                if (! $statement) {
                    $statement = OwnerStatement::create([
                        'owner_profile_id' => $profile->id,
                        'period' => $period,
                        'booking_count' => $billableBookings->count(),
                        'gmv' => $totalGmv,
                        'commission_amount' => $totalCommission,
                        'status' => $status,
                        'paid_at' => $paidAt,
                        'due_at' => $dueAt,
                        'transfer_code' => OwnerStatement::generateTransferCode($period, $profile->id),
                    ]);
                } else {
                    $statement->update([
                        'booking_count' => $billableBookings->count(),
                        'gmv' => $totalGmv,
                        'commission_amount' => $totalCommission,
                        'status' => $status,
                        'paid_at' => $paidAt ?? $statement->paid_at,
                        'due_at' => $statement->due_at ?? $dueAt,
                    ]);
                }

                foreach ($billableBookings as $b) {
                    $b->update([
                        'statement_id' => $statement->id,
                        'commission_amount' => $b->computeCommission(),
                    ]);
                }

                return $statement;
            });

            $createdStatements[] = $statement;

            if ($statement->wasRecentlyCreated && $statement->status === 'unpaid') {
                $this->remind($statement, 'issued');
            }
        }

        return $createdStatements;
    }

    /**
     * Gửi mail nhắc nợ phí đối soát cho chủ sân. Chạy hằng ngày: nhắc khi còn 2 ngày,
     * rồi khi quá hạn ngày 1, 4, 7... (lặp mỗi 3 ngày, không cần cột lưu lần nhắc).
     */
    public function sendDueReminders(): int
    {
        $sent = 0;

        OwnerStatement::with('ownerProfile.user')
            ->where('status', 'unpaid')
            ->where('commission_amount', '>', 0)
            ->whereNotNull('due_at')
            ->each(function (OwnerStatement $statement) use (&$sent) {
                $left = (int) today()->diffInDays($statement->due_at->copy()->startOfDay(), false);

                $kind = match (true) {
                    $left === 2 => 'due_soon',
                    $left < 0 && abs($left) % 3 === 1 => 'overdue',
                    default => null,
                };

                if ($kind) {
                    $this->remind($statement, $kind);
                    $sent++;
                }
            });

        return $sent;
    }

    private function remind(OwnerStatement $statement, string $kind): void
    {
        SafeMail::queue(
            $statement->ownerProfile?->user?->email,
            new StatementReminder($statement, $kind),
        );
    }

    /**
     * Tổng phí hoa hồng đã thu / chưa thu (chờ nộp + đã báo chuyển, chờ duyệt). Bỏ qua kỳ miễn thu.
     *
     * @return array{unpaid_amount: float, unpaid_count: int, paid_amount: float, paid_count: int}
     */
    public function totals(?int $ownerProfileId = null): array
    {
        $rows = OwnerStatement::query()
            ->when($ownerProfileId, fn ($q) => $q->where('owner_profile_id', $ownerProfileId))
            ->where('commission_amount', '>', 0)
            ->whereIn('status', ['unpaid', 'reported', 'paid'])
            ->selectRaw('status, COUNT(*) as n, SUM(commission_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $sum = fn (array $statuses, string $col) => collect($statuses)->sum(fn ($s) => $rows[$s]->{$col} ?? 0);

        return [
            'unpaid_amount' => (float) $sum(['unpaid', 'reported'], 'amount'),
            'unpaid_count' => (int) $sum(['unpaid', 'reported'], 'n'),
            'paid_amount' => (float) $sum(['paid'], 'amount'),
            'paid_count' => (int) $sum(['paid'], 'n'),
        ];
    }

    /**
     * Chủ sân báo đã chuyển khoản thanh toán phí đối soát.
     */
    public function report(User $owner, OwnerStatement $statement, ?string $note = null): OwnerStatement
    {
        if ($statement->ownerProfile?->user_id !== $owner->id) {
            throw new ForbiddenException('Bạn không có quyền thao tác trên kỳ đối soát này.');
        }

        if ($statement->status !== 'unpaid') {
            throw new ConflictException('Kỳ đối soát này hiện không ở trạng thái chờ thanh toán.');
        }

        $statement->update([
            'status' => 'reported',
            'reported_at' => now(),
            'note' => $note,
        ]);

        return $statement->refresh();
    }

    /**
     * Admin xác nhận chủ sân đã nộp phí hoa hồng.
     */
    public function confirm(User $admin, OwnerStatement $statement): OwnerStatement
    {
        if (! in_array($statement->status, ['unpaid', 'reported'], true)) {
            throw new ConflictException('Kỳ đối soát này đã được xử lý trước đó.');
        }

        $statement->update([
            'status' => 'paid',
            'paid_at' => now(),
            'confirmed_by' => $admin->id,
        ]);

        AdminLog::record('confirm_statement', $statement, [
            'period' => $statement->period,
            'commission_amount' => $statement->commission_amount,
        ], $admin);

        return $statement->refresh()->load(['ownerProfile.user', 'confirmedBy']);
    }

    /**
     * Admin từ chối báo cáo chuyển khoản (chưa nhận được tiền) và trả kỳ đối soát về unpaid.
     */
    public function rejectReport(User $admin, OwnerStatement $statement, ?string $reason = null): OwnerStatement
    {
        if ($statement->status !== 'reported') {
            throw new ConflictException('Chỉ có thể từ chối đối soát đối với kỳ đang ở trạng thái đã báo chuyển tiền.');
        }

        $note = $reason ? "Từ chối báo chuyển: {$reason}" : 'Báo cáo chuyển khoản chưa nhận được tiền.';

        $statement->update([
            'status' => 'unpaid',
            'reported_at' => null,
            'note' => $note,
        ]);

        AdminLog::record('reject_statement_payment', $statement, [
            'period' => $statement->period,
            'reason' => $reason,
        ], $admin);

        return $statement->refresh()->load(['ownerProfile.user', 'confirmedBy']);
    }

    /**
     * Admin miễn phí đối soát cho chủ sân (ví dụ tháng khuyến mãi, bồi thường...).
     */
    public function waive(User $admin, OwnerStatement $statement, string $reason): OwnerStatement
    {
        if ($statement->status === 'paid') {
            throw new ConflictException('Kỳ đối soát đã được thanh toán, không thể miễn thu.');
        }

        $statement->update([
            'status' => 'waived',
            'confirmed_by' => $admin->id,
            'note' => $reason,
        ]);

        AdminLog::record('waive_statement', $statement, [
            'period' => $statement->period,
            'reason' => $reason,
        ], $admin);

        return $statement->refresh()->load(['ownerProfile.user', 'confirmedBy']);
    }

    /**
     * Kiểm tra chủ sân có kỳ đối soát nào bị nợ quá hạn không (chỉ xét nợ phí > 0).
     */
    public function hasOverdueDebt(User $owner): bool
    {
        $profile = $owner->fieldOwnerProfile;
        if (! $profile) {
            return false;
        }

        return OwnerStatement::where('owner_profile_id', $profile->id)
            ->where('status', 'unpaid')
            ->where('commission_amount', '>', 0)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->exists();
    }

    /**
     * Tóm tắt ước tính phí hoa hồng tháng đang chạy của chủ sân.
     */
    public function currentMonthSummary(User $owner): array
    {
        $profile = $owner->fieldOwnerProfile;
        $period = now()->format('Y-m');
        $rate = Settings::getInt('commission_percent', 8);

        if (! $profile) {
            return [
                'period' => $period,
                'booking_count' => 0,
                'estimated_gmv' => 0.0,
                'estimated_commission' => 0.0,
                'commission_rate' => $rate,
                'has_overdue_debt' => false,
            ];
        }

        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $bookings = Booking::whereIn('sports_field_id', $fieldIds)
            ->whereDate('booking_date', '>=', $startDate)
            ->whereDate('booking_date', '<=', $endDate)
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
                BookingStatus::Cancelled->value,
            ])
            ->get();

        $billableBookings = $bookings->filter(fn (Booking $b) => $b->commissionBase() > 0);

        $totalGmv = 0.0;
        $totalCommission = 0.0;

        foreach ($billableBookings as $b) {
            $totalGmv += (float) $b->total_price;
            $base = $b->commissionBase();
            $bookingRate = (float) ($b->commission_rate ?? $rate);
            $totalCommission += round($base * $bookingRate / 100, 2);
        }

        return [
            'period' => $period,
            'booking_count' => $billableBookings->count(),
            'estimated_gmv' => (float) $totalGmv,
            'estimated_commission' => (float) $totalCommission,
            'commission_rate' => $rate,
            'has_overdue_debt' => $this->hasOverdueDebt($owner),
        ];
    }

    public function exportStatementsCsv(?string $period = null, ?string $status = null, ?string $keyword = null): string
    {
        $query = OwnerStatement::query()
            ->with(['ownerProfile.user', 'confirmedBy'])
            ->when($period, fn ($q) => $q->where('period', $period))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('transfer_code', 'like', "%{$keyword}%")
                        ->orWhereHas('ownerProfile', fn ($op) => $op->where('business_name', 'like', "%{$keyword}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$keyword}%")
                                ->orWhere('email', 'like', "%{$keyword}%")
                                ->orWhere('phone', 'like', "%{$keyword}%")));
                });
            })
            ->orderByDesc('period')
            ->orderByDesc('id');

        $statements = $query->get();

        $csv = "\xEF\xBB\xBF";
        $csv .= "Mã đối soát,Kỳ đối soát,Tên cơ sở,Chủ tài khoản,Số điện thoại,Email,Số đơn,Tổng GMV (VND),Hoa hồng sàn (VND),Mã chuyển khoản,Hạn nộp,Trạng thái,Quá hạn,Ghi chú,Ngày tạo\n";

        foreach ($statements as $st) {
            $statusLabel = match ($st->status) {
                'paid' => 'Đã thanh toán',
                'reported' => 'Khách báo chuyển',
                'waived' => 'Miễn thu',
                default => 'Chờ nộp',
            };

            $line = [
                $st->id,
                $st->period,
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->business_name ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->user?->name ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->business_phone ?? $st->ownerProfile?->user?->phone ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->user?->email ?? '—')).'"',
                $st->booking_count,
                number_format((float) $st->gmv, 0, '', ''),
                number_format((float) $st->commission_amount, 0, '', ''),
                $st->transfer_code,
                $st->due_at?->format('Y-m-d') ?? '—',
                $statusLabel,
                $st->is_overdue ? 'Có' : 'Không',
                '"'.str_replace('"', '""', (string) ($st->note ?? '')).'"',
                $st->created_at?->format('Y-m-d H:i:s') ?? '',
            ];

            $csv .= implode(',', $line)."\n";
        }

        return $csv;
    }

    public function exportStatementBookingsCsv(OwnerStatement $statement): string
    {
        $statement->load([
            'ownerProfile.user',
            'bookings' => fn ($q) => $q->with(['customer', 'sportsField', 'timeSlot'])->latest(),
        ]);

        $csv = "\xEF\xBB\xBF";
        $csv .= "Mã đơn,Khách hàng,Số điện thoại,Sân thể thao,Ngày chơi,Khung giờ,Tổng tiền (VND),Hoa hồng %,Tiền hoa hồng (VND),Trạng thái đơn,Ngày tạo\n";

        foreach ($statement->bookings as $b) {
            $line = [
                $b->id,
                '"'.str_replace('"', '""', (string) ($b->customer?->name ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($b->customer?->phone ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($b->sportsField?->name ?? '—')).'"',
                $b->booking_date?->format('Y-m-d') ?? '—',
                ($b->start_time ? substr($b->start_time, 0, 5).' - '.substr($b->end_time, 0, 5) : '—'),
                number_format((float) $b->total_price, 0, '', ''),
                $b->commission_rate ?? 0,
                number_format((float) ($b->commission_amount ?? 0), 0, '', ''),
                $b->status instanceof BookingStatus ? $b->status->value : (string) $b->status,
                $b->created_at?->format('Y-m-d H:i:s') ?? '',
            ];

            $csv .= implode(',', $line)."\n";
        }

        return $csv;
    }
}
