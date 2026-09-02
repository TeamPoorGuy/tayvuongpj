<?php

namespace App\Services;

use App\DTOs\Field\UpsertFieldData;
use App\Enums\BookingStatus;
use App\Enums\FieldStatus;
use App\Exceptions\ForbiddenException;
use App\Models\Booking;
use App\Models\FieldImage;
use App\Models\Review;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FieldOwnerService
{
    public function dashboard(User $owner): array
    {
        $fieldIds = SportsField::where('field_owner_id', $owner->id)->pluck('id');

        return [
            'total_fields' => $fieldIds->count(),
            'total_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->count(),
            'total_revenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)->whereIn('status', ['confirmed', 'completed'])->sum('total_price'),
            'current_month_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->whereYear('booking_date', Carbon::now()->year)->whereMonth('booking_date', Carbon::now()->month)->count(),
            'current_month_revenue' => (float) Booking::whereIn('sports_field_id', $fieldIds)->whereIn('status', ['confirmed', 'completed'])->whereYear('booking_date', Carbon::now()->year)->whereMonth('booking_date', Carbon::now()->month)->sum('total_price'),
            'most_booked_field' => SportsField::where('field_owner_id', $owner->id)->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])->withCount('bookings')->orderByDesc('bookings_count')->first(),
            'recent_bookings' => Booking::whereIn('sports_field_id', $fieldIds)->with(['sportsField.primaryImage', 'sportsField.reviews', 'customer', 'timeSlot'])->latest()->take(5)->get(),
        ];
    }

    public function fields(User $owner): LengthAwarePaginator
    {
        return SportsField::where('field_owner_id', $owner->id)
            ->with(['fieldType.sportCategory', 'primaryImage', 'reviews'])
            ->withCount('bookings')
            ->latest()
            ->paginate(10);
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
            ]);

            foreach ($data->images as $index => $image) {
                FieldImage::create([
                    'sports_field_id' => $field->id,
                    'image_path' => '/storage/'.$image->store('fields', 'public'),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }

            foreach ([['06:00', '07:30'], ['07:30', '09:00'], ['09:00', '10:30'], ['14:00', '15:30'], ['15:30', '17:00'], ['17:00', '18:30'], ['18:30', '20:00'], ['20:00', '21:30']] as [$start, $end]) {
                TimeSlot::create(['sports_field_id' => $field->id, 'start_time' => $start, 'end_time' => $end, 'is_active' => true]);
            }

            return $field->load(['images', 'primaryImage', 'fieldType.sportCategory', 'reviews']);
        });
    }

    public function updateField(User $owner, SportsField $field, UpsertFieldData $data): SportsField
    {
        $this->assertOwner($owner, $field);
        $field->update([
            'name' => $data->name,
            'field_type_id' => $data->fieldTypeId,
            'address' => $data->address,
            'price_per_hour' => $data->pricePerHour,
            'description' => $data->description,
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
        $fieldIds = SportsField::where('field_owner_id', $owner->id)->pluck('id');

        return Booking::whereIn('sports_field_id', $fieldIds)
            ->with(['sportsField.primaryImage', 'sportsField.reviews', 'customer', 'timeSlot', 'review'])
            ->when($filters['field_id'] ?? null, fn ($query, $id) => $query->where('sports_field_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('booking_date', $date))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function updateBookingStatus(User $owner, Booking $booking, string $status): Booking
    {
        $ownsField = SportsField::where('field_owner_id', $owner->id)->whereKey($booking->sports_field_id)->exists();
        if (! $ownsField) {
            throw new ForbiddenException();
        }

        $allowed = match ($booking->status) {
            BookingStatus::Pending->value => [BookingStatus::Confirmed->value, BookingStatus::Rejected->value],
            BookingStatus::Confirmed->value => [BookingStatus::Completed->value, BookingStatus::Cancelled->value],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new \App\Exceptions\ConflictException('Chuyển trạng thái đơn không hợp lệ.');
        }

        $booking->update(['status' => $status]);

        return $booking->refresh()->load(['sportsField.primaryImage', 'sportsField.reviews', 'customer', 'timeSlot', 'review']);
    }

    public function reviews(User $owner): LengthAwarePaginator
    {
        $fieldIds = SportsField::where('field_owner_id', $owner->id)->pluck('id');

        return Review::whereIn('sports_field_id', $fieldIds)->with(['user', 'sportsField'])->latest()->paginate(10);
    }

    private function assertOwner(User $owner, SportsField $field): void
    {
        if ($field->field_owner_id !== $owner->id) {
            throw new ForbiddenException();
        }
    }
}
