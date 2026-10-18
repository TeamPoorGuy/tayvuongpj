<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Models\AdminLog;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\OwnerStatement;
use App\Models\SportsField;
use App\Models\User;
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
        }

        return $createdStatements;
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
            ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Cancelled->value])
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
}
