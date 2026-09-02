<?php

namespace App\Services;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\BookingStatus;
use App\Enums\FieldStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function customerBookings(User $user): LengthAwarePaginator
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->with(['sportsField.primaryImage', 'sportsField.reviews', 'timeSlot', 'review'])
            ->latest()
            ->paginate(10);
    }

    public function create(User $user, CreateBookingData $data): Booking
    {
        return DB::transaction(function () use ($user, $data) {
            $field = SportsField::query()->lockForUpdate()->findOrFail($data->sportsFieldId);

            if ($field->status !== FieldStatus::Approved->value || ! $field->is_active) {
                throw new ConflictException('Sân chưa được duyệt hoặc đang dừng hoạt động.');
            }

            $slot = TimeSlot::query()
                ->where('sports_field_id', $field->id)
                ->where('is_active', true)
                ->findOrFail($data->timeSlotId);

            $isBooked = Booking::query()
                ->where('sports_field_id', $field->id)
                ->where('time_slot_id', $slot->id)
                ->whereDate('booking_date', $data->bookingDate)
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->lockForUpdate()
                ->exists();

            if ($isBooked) {
                throw new ConflictException('Khung giờ này đã có người đặt. Vui lòng chọn giờ khác.');
            }

            return Booking::create([
                'user_id' => $user->id,
                'sports_field_id' => $field->id,
                'time_slot_id' => $slot->id,
                'booking_date' => $data->bookingDate,
                'status' => BookingStatus::Pending->value,
                'total_price' => $field->price_per_hour * 1.5,
                'notes' => $data->notes,
            ])->load(['sportsField.primaryImage', 'sportsField.reviews', 'timeSlot', 'review']);
        });
    }

    public function cancel(User $user, Booking $booking, ?string $reason): Booking
    {
        if ($booking->user_id !== $user->id) {
            throw new ForbiddenException();
        }

        if (! $booking->canBeCancelled()) {
            throw new ConflictException('Đơn đặt sân này không còn đủ điều kiện để hủy.');
        }

        $booking->update([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason ?: 'Khách hàng tự hủy đơn.',
        ]);

        return $booking->refresh()->load(['sportsField.primaryImage', 'sportsField.reviews', 'timeSlot', 'review']);
    }
}
