<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\ConflictException;
use App\Models\Booking;
use App\Models\FieldClosure;
use App\Models\SportsField;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

class FieldClosureService
{
    /** Lấy danh sách ngày nghỉ / đóng sân của sân thể thao. */
    public function closures(User $owner, SportsField $field): Collection
    {
        Gate::forUser($owner)->authorize('manage', $field);

        return $field->closures()->orderBy('start_date', 'desc')->get();
    }

    /** Tạo lịch nghỉ / bảo trì đóng sân. */
    public function createClosure(User $owner, SportsField $field, array $data): FieldClosure
    {
        Gate::forUser($owner)->authorize('manage', $field);

        $startDate = Carbon::parse($data['start_date'])->toDateString();
        $endDate = Carbon::parse($data['end_date'])->toDateString();

        if ($startDate < today()->toDateString()) {
            throw new ConflictException('Ngày bắt đầu không được ở trong quá khứ.');
        }

        if ($startDate > $endDate) {
            throw new ConflictException('Ngày bắt đầu không được sau ngày kết thúc.');
        }

        // 1. Kiểm tra đơn đặt sân đang hoạt động (pending, confirmed)
        $startTime = ($data['start_time'] ?? null) ?: null;
        $endTime = ($data['end_time'] ?? null) ?: null;

        $activeBookings = Booking::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', '>=', $startDate)
            ->whereDate('booking_date', '<=', $endDate)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->when($startTime, fn ($q) => $q->overlapping($startTime, $endTime))
            ->get();

        if ($activeBookings->isNotEmpty()) {
            $samples = $activeBookings->take(3)->map(fn ($b) => "#BK-{$b->id} ({$b->booking_date->format('d/m/Y')})")->join(', ');
            $more = $activeBookings->count() > 3 ? '...' : '';
            throw new ConflictException("Không thể đóng sân vì đã có {$activeBookings->count()} đơn đặt sân trong thời gian này ({$samples}{$more}). Vui lòng xử lý hoặc liên hệ khách trước khi đóng sân.");
        }

        // 2. Kiểm tra khoảng ngày trùng lặp với lịch nghỉ đã có
        $overlap = FieldClosure::query()
            ->where('sports_field_id', $field->id)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            // Trùng ngày chưa đủ: hai lịch khoá theo giờ khác nhau cùng ngày vẫn hợp lệ.
            ->get()
            ->contains(fn (FieldClosure $other) => $other->isWholeDay() || ! $startTime
                || ($other->start_time < Booking::normalizeTime($endTime) && $other->end_time > Booking::normalizeTime($startTime)));

        if ($overlap) {
            throw new ConflictException('Khoảng ngày đóng sân bị trùng lặp với một lịch đóng sân khác đã tạo.');
        }

        return FieldClosure::create([
            'sports_field_id' => $field->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => trim($data['reason']),
        ]);
    }

    /** Xoá lịch nghỉ để mở lại sân hoạt động bình thường. */
    public function deleteClosure(User $owner, SportsField $field, FieldClosure $closure): void
    {
        Gate::forUser($owner)->authorize('manage', $field);
        abort_unless($closure->sports_field_id === $field->id, 404, 'Không tìm thấy lịch đóng sân.');

        $closure->delete();
    }
}
