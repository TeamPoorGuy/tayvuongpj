<?php

namespace App\Services;

use App\DTOs\Field\UpsertFieldData;
use App\Enums\BookingStatus;
use App\Enums\FieldStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Exceptions\ConflictException;
use App\Mail\BookingStatusChanged;
use App\Models\FieldImage;
use App\Support\SafeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FieldOwnerService
{
    public function dashboard(User $owner): array
    {
        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');
        $statementService = app(StatementService::class);
        $monthSummary = $statementService->currentMonthSummary($owner);

        $now = Carbon::now();
        $totalGmv = (float) Booking::whereIn('sports_field_id', $fieldIds)->whereIn('status', ['confirmed', 'completed'])->sum('total_price');
        $currentMonthGmv = (float) Booking::whereIn('sports_field_id', $fieldIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereYear('booking_date', $now->year)
            ->whereMonth('booking_date', $now->month)
            ->sum('total_price');

        $currentMonthPaid = (float) Booking::whereIn('sports_field_id', $fieldIds)
            ->where('payment_status', 'paid')
            ->whereYear('booking_date', $now->year)
            ->whereMonth('booking_date', $now->month)
            ->sum('payment_amount');

        return [
            'total_fields' => $fieldIds->count(),
            'total_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->count(),
            'pending_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->where('status', 'pending')->count(),
            'total_gmv' => $totalGmv,
            'current_month_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->whereYear('booking_date', $now->year)->whereMonth('booking_date', $now->month)->count(),
            'current_month_gmv' => $currentMonthGmv,
            'current_month_collected' => $currentMonthPaid,
            'current_month_commission' => $monthSummary['estimated_commission'],
            'current_month_net_revenue' => max(0.0, $currentMonthPaid - $monthSummary['estimated_commission']),
            'has_overdue_debt' => $monthSummary['has_overdue_debt'],
            'statement_summary' => $monthSummary,
            'most_booked_field' => SportsField::where('field_owner_id', $owner->id)->with(['primaryImage', 'fieldType.sportCategory'])->withRating()->withCount('bookings')->orderByDesc('bookings_count')->first(),
            // Dashboard summary only — deliberately omits 'review', unlike Booking::resourceRelations().
            'recent_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->with(['sportsField.primaryImage', 'customer', 'timeSlot'])->latest()->take(5)->get(),
        ];
    }

    public function fields(User $owner, int $perPage = 10): LengthAwarePaginator
    {
        return SportsField::where('field_owner_id', $owner->id)
            ->with(['fieldType.sportCategory', 'primaryImage', 'images', 'timeSlots'])
            ->withRating()
            ->withCount('bookings')
            ->latest()
            ->paginate(min(max($perPage, 1), 100));
    }

    public function createField(User $owner, UpsertFieldData $data): SportsField
    {
        return DB::transaction(function () use ($owner, $data) {
            $field = SportsField::create([
                'field_owner_id' => $owner->id,
                'field_type_id' => $data->fieldTypeId,
                'name' => $data->name,
                'slug' => Str::slug($data->name).'-'.Str::lower(Str::random(6)),
                'address' => $data->address,
                'description' => $data->description,
                'price_per_hour' => $data->pricePerHour,
                'status' => FieldStatus::Pending->value,
                'is_active' => true,
                ...$data->coordinates,
            ]);

            foreach ($data->images as $index => $image) {
                FieldImage::create([
                    'sports_field_id' => $field->id,
                    'image_path' => '/storage/'.$image->store('fields', 'public'),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }

            foreach (TimeSlot::DEFAULT_SCHEDULE as [$start, $end]) {
                TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => $start, 'end_time' => $end, 'is_active' => true]);
            }

            return $field->load(['images', 'primaryImage', 'fieldType.sportCategory', 'reviews']);
        });
    }

    public function updateField(User $owner, SportsField $field, UpsertFieldData $data): SportsField
    {
        $this->assertOwner($owner, $field);

        // Đổi tên/địa chỉ/loại sân sau khi duyệt phải được admin duyệt lại; sân bị từ chối được gửi lại khi sửa.
        $sensitive = $field->name !== $data->name || $field->address !== $data->address || $field->field_type_id !== $data->fieldTypeId;
        $statusValue = $field->status instanceof FieldStatus ? $field->status->value : $field->status;
        $resubmit = $statusValue === FieldStatus::Rejected->value
            || ($statusValue === FieldStatus::Approved->value && $sensitive);

        $field->update([
            'name' => $data->name,
            'field_type_id' => $data->fieldTypeId,
            'address' => $data->address,
            'price_per_hour' => $data->pricePerHour,
            'description' => $data->description,
            ...$data->coordinates,
            ...($resubmit ? ['status' => FieldStatus::Pending->value, 'rejection_reason' => null] : []),
        ]);

        return $field->refresh()->load(['images', 'primaryImage', 'fieldType.sportCategory', 'reviews']);
    }

    public function toggleField(User $owner, SportsField $field): SportsField
    {
        $this->assertOwner($owner, $field);

        if ($field->is_active) {
            $hasUpcoming = Booking::where('sports_field_id', $field->id)
                ->whereIn('status', BookingStatus::blockingCases())
                ->whereDate('booking_date', '>=', today())
                ->exists();

            if ($hasUpcoming) {
                throw new ConflictException('Sân đang có đơn đặt sắp tới, không thể tạm dừng hoạt động.');
            }
        }

        $field->update(['is_active' => ! $field->is_active]);

        return $field->refresh()->load(['primaryImage', 'fieldType.sportCategory', 'reviews']);
    }

    public function bookings(User $owner, array $filters): LengthAwarePaginator
    {
        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');
        $perPage = min(200, max(1, (int) ($filters['per_page'] ?? 10)));

        return Booking::whereIn('sports_field_id', $fieldIds)
            ->with(Booking::resourceRelations(withCustomer: true))
            ->when($filters['field_id'] ?? null, fn ($query, $id) => $query->where('sports_field_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn ($query, $payment) => $query->where('payment_status', $payment))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('booking_date', $date))
            ->when($filters['start_date'] ?? null, fn ($query, $start) => $query->whereDate('booking_date', '>=', $start))
            ->when($filters['end_date'] ?? null, fn ($query, $end) => $query->whereDate('booking_date', '<=', $end))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function updateBookingStatus(User $owner, Booking $booking, string $status, ?string $reason = null): Booking
    {
        Gate::forUser($owner)->authorize('manage', $booking);

        $next = BookingStatus::from($status);
        if (! $booking->status->canTransitionTo($next)) {
            throw new ConflictException('Chuyển trạng thái đơn không hợp lệ.');
        }

        if (in_array($next, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            if ($booking->payment_status === 'reported') {
                throw new ConflictException('Khách đã báo chuyển khoản, vui lòng xác nhận hoặc đối soát thanh toán thay vì từ chối/huỷ.');
            }
        }

        if ($next === BookingStatus::Cancelled && $booking->startsAt()->isPast()) {
            throw new ConflictException('Đơn đã quá giờ bắt đầu, không thể huỷ.');
        }

        if ($next === BookingStatus::Completed) {
            if ($booking->endsAt()->isFuture()) {
                throw new ConflictException('Chưa hết giờ thi đấu nên chưa thể hoàn thành đơn.');
            }
            if ($booking->checked_in_at === null) {
                throw new ConflictException('Chỉ có thể hoàn thành đơn sau khi khách đã check-in.');
            }
        }

        if ($next === BookingStatus::Cancelled) {
            $booking->markCancelled('field_owner', $reason ?: 'Chủ sân huỷ đơn.');
        } else {
            $attributes = ['status' => $next->value];
            if ($next === BookingStatus::Rejected) {
                $attributes += [
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason ?: 'Chủ sân từ chối đơn.',
                ];
            }
            $booking->update($attributes);
        }

        $booking = $booking->refresh()->load(Booking::resourceRelations(withCustomer: true));

        if (in_array($next, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            app(\App\Services\WaitlistService::class)->handleSlotFreed(
                $booking->sports_field_id,
                $booking->time_slot_id,
                $booking->booking_date->toDateString()
            );
        }

        if ($next !== BookingStatus::Completed) {
            SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
        }

        return $booking;
    }

    public function recordNoShow(User $owner, Booking $booking): Booking
    {
        Gate::forUser($owner)->authorize('manage', $booking);

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new ConflictException('Chỉ có thể ghi nhận vắng mặt cho đơn đã xác nhận.');
        }

        if (! $booking->startsAt()->isPast()) {
            throw new ConflictException('Chưa đến giờ thi đấu, chưa thể ghi nhận vắng mặt.');
        }

        if ($booking->checked_in_at !== null) {
            throw new ConflictException('Đơn này đã check-in, không thể ghi nhận vắng mặt.');
        }

        if ($booking->no_show_at !== null) {
            throw new ConflictException('Đơn này đã được ghi nhận vắng mặt trước đó.');
        }

        $booking->update([
            'status' => BookingStatus::Completed->value,
            'no_show_at' => now(),
        ]);

        if ($booking->customer) {
            TrustScoreService::penalizeNoShow($booking->customer, $booking);
        }

        return $booking->refresh()->load(Booking::resourceRelations(withCustomer: true));
    }

    public function reviews(User $owner): LengthAwarePaginator
    {
        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');

        // Đánh giá bị admin ẩn (vi phạm) không hiển thị lại cho chủ sân.
        return Review::whereIn('sports_field_id', $fieldIds)->where('is_visible', true)->with(['user', 'sportsField'])->latest()->paginate(10);
    }

    public function replyReview(User $owner, Review $review, string $reply): Review
    {
        $review->loadMissing('sportsField');
        $this->assertOwner($owner, $review->sportsField);

        $review->update([
            'owner_reply' => trim($reply),
            'owner_replied_at' => now(),
        ]);

        return $review->refresh()->load(['user', 'sportsField']);
    }

    public function deleteField(User $owner, SportsField $field): void
    {
        $this->assertOwner($owner, $field);

        $hasUpcoming = Booking::where('sports_field_id', $field->id)
            ->whereIn('status', BookingStatus::blockingCases())
            ->whereDate('booking_date', '>=', today())
            ->exists();

        if ($hasUpcoming) {
            throw new ConflictException('Sân còn đơn đặt sắp tới, hãy xử lý các đơn trước khi xoá.');
        }

        $field->delete(); // soft delete: giữ lịch sử đơn & doanh thu
    }

    /** @param list<\Illuminate\Http\UploadedFile> $files */
    public function addImages(User $owner, SportsField $field, array $files): SportsField
    {
        $this->assertOwner($owner, $field);

        if ($field->images()->count() + count($files) > 8) {
            throw new ConflictException('Mỗi sân tối đa 8 ảnh.');
        }

        $order = (int) $field->images()->max('sort_order');
        $hasPrimary = $field->images()->where('is_primary', true)->exists();
        foreach ($files as $i => $file) {
            FieldImage::create([
                'sports_field_id' => $field->id,
                'image_path' => '/storage/'.$file->store('fields', 'public'),
                'is_primary' => ! $hasPrimary && $i === 0,
                'sort_order' => ++$order,
            ]);
        }

        return $this->freshField($field);
    }

    public function deleteImage(User $owner, SportsField $field, FieldImage $image): SportsField
    {
        $this->assertOwner($owner, $field);
        abort_unless($image->sports_field_id === $field->id, 404);

        Storage::disk('public')->delete(Str::after($image->image_path, '/storage/'));
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $field->images()->first()?->update(['is_primary' => true]);
        }

        return $this->freshField($field);
    }

    public function setPrimaryImage(User $owner, SportsField $field, FieldImage $image): SportsField
    {
        $this->assertOwner($owner, $field);
        abort_unless($image->sports_field_id === $field->id, 404);

        $field->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return $this->freshField($field);
    }

    /** @param array{start_time:string,end_time:string,price_per_hour?:mixed,is_active?:bool} $data */
    public function saveSlot(User $owner, SportsField $field, array $data, ?TimeSlot $slot = null): TimeSlot
    {
        $this->assertOwner($owner, $field);
        abort_if($slot && $slot->sports_field_id !== $field->id, 404);

        $start = Carbon::createFromFormat('H:i', $data['start_time'])->format('H:i:s');
        $end = Carbon::createFromFormat('H:i', $data['end_time'])->format('H:i:s');

        $overlaps = TimeSlot::where('sports_field_id', $field->id)
            ->where('is_active', true)
            ->when($slot, fn ($q) => $q->whereKeyNot($slot->id))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($overlaps) {
            throw new ConflictException('Khung giờ bị trùng với khung giờ khác của sân.');
        }

        if ($slot && ($slot->start_time !== $start || $slot->end_time !== $end) && $this->slotHasUpcomingBookings($slot)) {
            throw new ConflictException('Khung giờ đang có đơn sắp tới, không thể đổi giờ.');
        }

        if ($slot && isset($data['is_active']) && ! (bool) $data['is_active'] && $slot->is_active && $this->slotHasUpcomingBookings($slot)) {
            throw new ConflictException('Khung giờ đang có đơn sắp tới, không thể tắt hoạt động.');
        }

        $attributes = [
            'start_time' => $start,
            'end_time' => $end,
            'price_per_hour' => $data['price_per_hour'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        return $slot ? tap($slot)->update($attributes) : TimeSlot::create(['sports_field_id' => $field->id] + $attributes);
    }

    /** Xoá hẳn nếu chưa từng có đơn; ngược lại chỉ ngưng hoạt động. Trả về true nếu đã xoá. */
    public function deleteSlot(User $owner, SportsField $field, TimeSlot $slot): bool
    {
        $this->assertOwner($owner, $field);
        abort_unless($slot->sports_field_id === $field->id, 404);

        if ($this->slotHasUpcomingBookings($slot)) {
            throw new ConflictException('Khung giờ đang có đơn sắp tới, không thể xoá.');
        }

        if ($slot->bookings()->exists()) {
            $slot->update(['is_active' => false]);

            return false;
        }

        $slot->delete();

        return true;
    }

    private function slotHasUpcomingBookings(TimeSlot $slot): bool
    {
        return $slot->bookings()
            ->whereIn('status', BookingStatus::blockingCases())
            ->whereDate('booking_date', '>=', today())
            ->exists();
    }

    private function freshField(SportsField $field): SportsField
    {
        return $field->refresh()->load(['images', 'primaryImage', 'fieldType.sportCategory', 'timeSlots'])->loadCount('bookings');
    }

    private function assertOwner(User $owner, SportsField $field): void
    {
        Gate::forUser($owner)->authorize('manage', $field);
    }
}
