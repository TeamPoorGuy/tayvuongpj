<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Http\Requests\Api\Field\FieldIndexRequest;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CatalogService
{
    public function categories(bool $withTypes = false): Collection
    {
        return SportCategory::query()
            ->where('is_active', true)
            ->withCount('fieldTypes')
            ->when($withTypes, fn ($query) => $query->with(['fieldTypes' => fn ($types) => $types->where('is_active', true)]))
            ->get();
    }

    public function fieldTypes(): Collection
    {
        return FieldType::query()->where('is_active', true)->with('sportCategory')->get();
    }

    public function featuredFields(): Collection
    {
        return SportsField::approved()
            ->with(['primaryImage', 'fieldType.sportCategory'])
            ->withRating()
            ->latest()
            ->take(6)
            ->get();
    }

    public function fields(FieldIndexRequest $request): LengthAwarePaginator
    {
        return SportsField::approved()
            ->with(['primaryImage', 'fieldType.sportCategory'])
            ->withRating()
            ->when($request->filled('keyword'), function ($query) use ($request) {
                // Tách từ, bỏ dấu: mỗi từ phải xuất hiện đâu đó trong tên/địa chỉ/quận/thành phố.
                $words = preg_split('/\s+/', SportsField::normalizeForSearch($request->string('keyword')->toString()), -1, PREG_SPLIT_NO_EMPTY);
                foreach (array_slice($words, 0, 8) as $word) {
                    $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->when($request->filled('city'), fn ($query) => $query->where('city', $request->string('city')->toString()))
            ->when($request->filled('district'), fn ($query) => $query->where('district', $request->string('district')->toString()))
            ->when($request->filled('category'), fn ($query) => $query->whereHas(
                'fieldType.sportCategory',
                fn ($category) => $category->where('slug', $request->string('category')->toString())
            ))
            ->when($request->filled('type_id'), fn ($query) => $query->where('field_type_id', $request->integer('type_id')))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price_per_hour', '>=', $request->input('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price_per_hour', '<=', $request->input('max_price')))
            ->latest()
            ->paginate($request->integer('per_page', 9))
            ->withQueryString();
    }

    /** @return list<array{city: string, districts: list<string>}> */
    public function locations(): array
    {
        return SportsField::approved()
            ->whereNotNull('city')
            ->select('city', 'district')
            ->distinct()
            ->orderBy('city')
            ->orderBy('district')
            ->get()
            ->groupBy('city')
            ->map(fn ($rows, $city) => [
                'city' => $city,
                'districts' => $rows->pluck('district')->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function fieldBySlug(string $slug): SportsField
    {
        return SportsField::approved()
            ->where('slug', $slug)
            ->with(['images', 'primaryImage', 'fieldType.sportCategory', 'owner.fieldOwnerProfile', 'timeSlots', 'closures', 'reviews' => fn ($q) => $q->where('is_visible', true)->with('user')])
            ->firstOrFail();
    }

    public function availableSlots(SportsField $field, string $date): array
    {
        abort_unless(
            SportsField::approved()->whereKey($field->id)->exists(),
            404,
            'Không tìm thấy sân đang hoạt động.'
        );

        $isClosed = $field->isClosedOn($date);

        $bookedSlotIds = Booking::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', BookingStatus::blockingCases())
            ->pluck('time_slot_id');

        // Slot đang được giữ chỗ 15 phút cho người trong hàng đợi.
        $heldSlotIds = BookingWaitlist::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->where('status', 'offered')
            ->where('offer_expires_at', '>', now())
            ->pluck('time_slot_id');

        return $field->timeSlots()->where('is_active', true)->get()->map(fn ($slot) => [
            'id' => $slot->id,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'is_booked' => $isClosed || $bookedSlotIds->contains($slot->id),
            'is_held' => ! $isClosed && ! $bookedSlotIds->contains($slot->id) && $heldSlotIds->contains($slot->id),
            'is_past' => Carbon::parse($date.' '.$slot->start_time)->isPast(),
            'price_per_hour' => $slot->price_per_hour !== null ? (float) $slot->price_per_hour : null,
        ])->all();
    }
}
