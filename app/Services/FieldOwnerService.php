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

        return [
            'total_fields' => $fieldIds->count(),
            'total_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->count(),
            'pending_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->where('status', 'pending')->count(),
            'total_revenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)->whereIn('status', ['confirmed', 'completed'])->sum('total_price'),
            'current_month_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->whereYear('booking_date', Carbon::now()->year)->whereMonth('booking_date', Carbon::now()->month)->count(),
            'current_month_revenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)->whereIn('status', ['confirmed', 'completed'])->whereYear('booking_date', Carbon::now()->year)->whereMonth('booking_date', Carbon::now()->month)->sum('total_price'),
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
        $resubmit = $field->status === FieldStatus::Rejected->value
            || ($field->status === FieldStatus::Approved->value && $sensitive);

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

        if ($next === BookingStatus::Completed && $booking->endsAt()->isFuture()) {
            throw new ConflictException('Chưa hết giờ thi đấu nên chưa thể hoàn thành đơn.');
        }

        $attributes = ['status' => $next->value];
        if (in_array($next, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
            $attributes += [
                'cancelled_at' => now(),
                'cancel_reason' => $reason ?: ($next === BookingStatus::Rejected ? 'Chủ sân từ chối đơn.' : 'Chủ sân huỷ đơn.'),
            ];
        }

        $booking->update($attributes);
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

    public function reviews(User $owner): LengthAwarePaginator
    {
        $fieldIds = SportsField::withTrashed()->where('field_owner_id', $owner->id)->pluck('id');

        // Đánh giá bị admin ẩn (vi phạm) không hiển thị lại cho chủ sân.
        return Review::whereIn('sports_field_id', $fieldIds)->where('is_visible', true)->with(['user', 'sportsField'])->latest()->paginate(10);
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
