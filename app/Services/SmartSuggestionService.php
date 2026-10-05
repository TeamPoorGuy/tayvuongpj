<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Carbon\Carbon;

class SmartSuggestionService
{
    /**
     * Gợi ý các khung giờ hoặc sân thay thế thông minh khi một slot đã kín.
     */
    public function getSuggestions(SportsField $field, TimeSlot $targetSlot, string $dateString): array
    {
        $date = Carbon::parse($dateString)->startOfDay();
        $suggestions = [];

        // 1. Gợi ý cùng sân, cùng ngày nhưng khung giờ khác còn trống
        $sameFieldOtherSlots = TimeSlot::where('sports_field_id', $field->id)
            ->where('id', '!=', $targetSlot->id)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $bookedSlotIdsToday = Booking::where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->pluck('time_slot_id')
            ->all();

        foreach ($sameFieldOtherSlots as $slot) {
            if ($date->isToday() && Carbon::parse($date->toDateString().' '.$slot->start_time)->isPast()) {
                continue;
            }
            if (! in_array($slot->id, $bookedSlotIdsToday, true)) {
                $targetPrice = $targetSlot->price_per_hour ?? $field->price_per_hour;
                $thisPrice = $slot->price_per_hour ?? $field->price_per_hour;

                $reason = 'Cùng sân hôm nay';
                if ($thisPrice < $targetPrice) {
                    $savingPercent = round((($targetPrice - $thisPrice) / $targetPrice) * 100);
                    $reason = "Tiết kiệm {$savingPercent}% chi phí";
                }

                $suggestions[] = [
                    'type' => 'same_field_today',
                    'field_id' => $field->id,
                    'field_name' => $field->name,
                    'field_slug' => $field->slug,
                    'time_slot_id' => $slot->id,
                    'start_time' => substr($slot->start_time, 0, 5),
                    'end_time' => substr($slot->end_time, 0, 5),
                    'date' => $date->toDateString(),
                    'date_label' => 'Hôm nay (' . $date->format('d/m') . ')',
                    'price_per_hour' => $thisPrice,
                    'badge' => $reason,
                ];

                if (count($suggestions) >= 2) {
                    break;
                }
            }
        }

        // 2. Gợi ý cùng sân, cùng khung giờ nhưng ngày mai
        $tomorrow = (clone $date)->addDay();
        $isBookedTomorrow = Booking::where('sports_field_id', $field->id)
            ->where('time_slot_id', $targetSlot->id)
            ->whereDate('booking_date', $tomorrow)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->exists();

        if (! $isBookedTomorrow) {
            $suggestions[] = [
                'type' => 'same_slot_tomorrow',
                'field_id' => $field->id,
                'field_name' => $field->name,
                'field_slug' => $field->slug,
                'time_slot_id' => $targetSlot->id,
                'start_time' => substr($targetSlot->start_time, 0, 5),
                'end_time' => substr($targetSlot->end_time, 0, 5),
                'date' => $tomorrow->toDateString(),
                'date_label' => 'Ngày mai (' . $tomorrow->format('d/m') . ')',
                'price_per_hour' => $targetSlot->price_per_hour ?? $field->price_per_hour,
                'badge' => 'Cùng khung giờ ngày mai',
            ];
        }

        // 3. Gợi ý sân khác cùng loại sân (FieldType) còn trống khung giờ này hôm nay
        $otherFields = SportsField::where('field_type_id', $field->field_type_id)
            ->where('id', '!=', $field->id)
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['timeSlots'])
            ->limit(3)
            ->get();

        foreach ($otherFields as $otherField) {
            // Tìm slot có giờ bắt đầu tương tự
            $matchedSlot = $otherField->timeSlots
                ->where('is_active', true)
                ->first(fn ($s) => substr($s->start_time, 0, 5) === substr($targetSlot->start_time, 0, 5));

            if ($matchedSlot) {
                $isOtherBooked = Booking::where('sports_field_id', $otherField->id)
                    ->where('time_slot_id', $matchedSlot->id)
                    ->whereDate('booking_date', $date)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->exists();

                if (! $isOtherBooked) {
                    $suggestions[] = [
                        'type' => 'other_field_today',
                        'field_id' => $otherField->id,
                        'field_name' => $otherField->name,
                        'field_slug' => $otherField->slug,
                        'time_slot_id' => $matchedSlot->id,
                        'start_time' => substr($matchedSlot->start_time, 0, 5),
                        'end_time' => substr($matchedSlot->end_time, 0, 5),
                        'date' => $date->toDateString(),
                        'date_label' => 'Hôm nay (' . $date->format('d/m') . ')',
                        'price_per_hour' => $matchedSlot->price_per_hour ?? $otherField->price_per_hour,
                        'badge' => 'Sân khác cùng bộ môn',
                    ];
                    break;
                }
            }
        }

        return $suggestions;
    }
}
