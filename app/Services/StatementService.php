<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Mail\StatementReminder;
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
     * Lập báo cáo đối soát cho một kỳ (YYYY-MM): tiền SportHub thu hộ − tiền hoàn cho khách − hoa hồng = tiền về ví chủ sân.
     * Tiền thật đã được cộng vào ví theo từng đơn (EarningService); báo cáo chỉ tổng hợp và lập lại được bất cứ lúc nào.
     * Chỉ tính đơn đã kết thúc và đã thanh toán qua nền tảng (đơn kiểu cũ khách trả thẳng chủ sân thì không có tiền thu hộ).
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

        $profiles = FieldOwnerProfile::query()
            ->when($ownerProfileId, fn ($q) => $q->whereKey($ownerProfileId))
            ->where('verification_status', 'approved')
            ->get();

        $createdStatements = [];

        foreach ($profiles as $profile) {
            $statement = DB::transaction(function () use ($profile, $period, $startDate, $endDate) {
                $statement = OwnerStatement::where('owner_profile_id', $profile->id)
                    ->where('period', $period)
                    ->first();

                $fieldIds = SportsField::withTrashed()->where('field_owner_id', $profile->user_id)->pluck('id');

                // Kỳ chưa chi trả được lập lại: nhả các đơn đã gán để tính lại (đơn mới có khiếu nại sẽ bị giữ lại).
                if ($statement) {
                    Booking::where('statement_id', $statement->id)->update(['statement_id' => null]);
                }

                // Đơn đang có khiếu nại chưa đóng bị giữ lại, không chi trả cho chủ sân; khi khiếu nại đóng,
                // đơn chưa vào kỳ nào được tính bù vào kỳ kế tiếp.
                $bookings = Booking::whereIn('sports_field_id', $fieldIds)
                    ->whereNotNull('payment_id')
                    ->where('payment_status', 'paid')
                    ->where(fn ($q) => $q
                        ->where(fn ($inPeriod) => $inPeriod->whereDate('booking_date', '>=', $startDate)->whereDate('booking_date', '<=', $endDate))
                        ->orWhere(fn ($carried) => $carried->whereNull('statement_id')->whereDate('booking_date', '<', $startDate)))
                    ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Cancelled->value])
                    ->whereDoesntHave('complaints', fn ($c) => $c->holdingPayout())
                    ->get();

                $totals = ['gmv' => 0.0, 'collected' => 0.0, 'refunded' => 0.0, 'commission' => 0.0];
                foreach ($bookings as $b) {
                    $totals['gmv'] += $b->commissionBase() > 0 ? (float) $b->total_price : 0.0;
                    $totals['collected'] += (float) $b->payment_amount;
                    $totals['refunded'] += $b->refundedAmount();
                    $totals['commission'] += $b->computeCommission();
                }
                $payout = max(0.0, $totals['collected'] - $totals['refunded'] - $totals['commission']);

                $attributes = [
                    'booking_count' => $bookings->count(),
                    'gmv' => $totals['gmv'],
                    'collected_amount' => $totals['collected'],
                    'refunded_amount' => $totals['refunded'],
                    'commission_amount' => $totals['commission'],
                    'payout_amount' => $payout,
                    // Báo cáo: tiền đã về ví chủ sân theo từng đơn nên không còn kỳ "chờ chi trả".
                    'status' => 'paid',
                    'paid_at' => $statement?->paid_at ?? now(),
                ];

                if (! $statement) {
                    $statement = OwnerStatement::create($attributes + [
                        'owner_profile_id' => $profile->id,
                        'period' => $period,
                        'transfer_code' => OwnerStatement::generateTransferCode($period, $profile->id),
                    ]);
                } else {
                    $statement->update($attributes);
                }

                foreach ($bookings as $b) {
                    $b->update(['statement_id' => $statement->id, 'commission_amount' => $b->computeCommission()]);
                }

                return $statement;
            });

            $createdStatements[] = $statement;

            if ($statement->wasRecentlyCreated && $statement->booking_count > 0) {
                $this->notify($statement, 'issued');
            }
        }

        return $createdStatements;
    }

    private function notify(OwnerStatement $statement, string $kind): void
    {
        SafeMail::queue(
            $statement->ownerProfile?->user?->email,
            new StatementReminder($statement, $kind),
        );
    }

    /**
     * Tổng tiền đã chi trả / còn phải chi trả cho chủ sân (bỏ qua kỳ không có tiền chuyển).
     *
     * @return array{unpaid_amount: float, unpaid_count: int, paid_amount: float, paid_count: int}
     */
    public function totals(?int $ownerProfileId = null): array
    {
        $rows = OwnerStatement::query()
            ->when($ownerProfileId, fn ($q) => $q->where('owner_profile_id', $ownerProfileId))
            ->where('payout_amount', '>', 0)
            ->selectRaw('status, COUNT(*) as n, SUM(payout_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'unpaid_amount' => (float) ($rows['unpaid']->amount ?? 0),
            'unpaid_count' => (int) ($rows['unpaid']->n ?? 0),
            'paid_amount' => (float) ($rows['paid']->amount ?? 0),
            'paid_count' => (int) ($rows['paid']->n ?? 0),
        ];
    }

    /** Ước tính tháng đang chạy của chủ sân: tiền SportHub đã thu hộ, hoa hồng và số sẽ được chi trả. */
    public function currentMonthSummary(User $owner): array
    {
        $period = now()->format('Y-m');
        $rate = Settings::getInt('commission_percent', 8);
        $summary = [
            'period' => $period,
            'booking_count' => 0,
            'estimated_gmv' => 0.0,
            'collected' => 0.0,
            'refunded' => 0.0,
            'estimated_commission' => 0.0,
            'estimated_payout' => 0.0,
            'commission_rate' => $rate,
        ];

        if (! $owner->fieldOwnerProfile) {
            return $summary;
        }

        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');
        $bookings = Booking::whereIn('sports_field_id', $fieldIds)
            ->whereNotNull('payment_id')
            ->where('payment_status', 'paid')
            ->whereDate('booking_date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('booking_date', '<=', now()->endOfMonth()->toDateString())
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value, BookingStatus::Cancelled->value])
            ->get();

        foreach ($bookings as $b) {
            $summary['booking_count']++;
            $summary['estimated_gmv'] += $b->commissionBase() > 0 ? (float) $b->total_price : 0.0;
            $summary['collected'] += (float) $b->payment_amount;
            $summary['refunded'] += $b->refundedAmount();
            $summary['estimated_commission'] += round($b->commissionBase() * (float) ($b->commission_rate ?? $rate) / 100, 2);
        }
        $summary['estimated_payout'] = max(0.0, $summary['collected'] - $summary['refunded'] - $summary['estimated_commission']);

        return $summary;
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
        $csv .= "Mã đối soát,Kỳ đối soát,Tên cơ sở,Chủ tài khoản,Số điện thoại,Email,Số đơn,Tổng GMV (VND),Đã thu hộ (VND),Hoàn khách (VND),Hoa hồng sàn (VND),Chi trả chủ sân (VND),Nội dung chuyển khoản,Hạn chi trả,Trạng thái,Quá hạn,Mã giao dịch chi trả,Ngày tạo\n";

        foreach ($statements as $st) {
            $statusLabel = 'Đã cộng ví';

            $line = [
                $st->id,
                $st->period,
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->business_name ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->user?->name ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->business_phone ?? $st->ownerProfile?->user?->phone ?? '—')).'"',
                '"'.str_replace('"', '""', (string) ($st->ownerProfile?->user?->email ?? '—')).'"',
                $st->booking_count,
                number_format((float) $st->gmv, 0, '', ''),
                number_format((float) $st->collected_amount, 0, '', ''),
                number_format((float) $st->refunded_amount, 0, '', ''),
                number_format((float) $st->commission_amount, 0, '', ''),
                number_format((float) $st->payout_amount, 0, '', ''),
                $st->transfer_code,
                $st->due_at?->format('Y-m-d') ?? '—',
                $statusLabel,
                $st->isOverdue() ? 'Có' : 'Không',
                '"'.str_replace('"', '""', (string) ($st->payout_ref ?? '')).'"',
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
