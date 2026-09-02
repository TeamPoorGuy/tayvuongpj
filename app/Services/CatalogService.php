<?php

namespace App\Services;

use App\Enums\FieldStatus;
use App\Http\Requests\Api\Field\FieldIndexRequest;
use App\Models\Booking;
use App\Models\FieldType;
use App\Models\SportCategory;
use App\Models\SportsField;
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
            ->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
            ->latest()
            ->take(6)
            ->get();
    }

    public function fields(FieldIndexRequest $request): LengthAwarePaginator
    {
        return SportsField::approved()
            ->with(['primaryImage', 'fieldType.sportCategory', 'reviews'])
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->string('keyword')->toString();
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%"));
            })
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

    public function fieldBySlug(string $slug): SportsField
    {
        return SportsField::approved()
            ->where('slug', $slug)
            ->with(['images', 'primaryImage', 'fieldType.sportCategory', 'owner.fieldOwnerProfile', 'timeSlots', 'reviews.user'])
            ->firstOrFail();
    }

    public function availableSlots(SportsField $field, string $date): array
    {
        abort_unless(
            $field->status === FieldStatus::Approved->value && $field->is_active,
            404,
            'Không tìm thấy sân đang hoạt động.'
        );

        $bookedSlotIds = Booking::query()
            ->where('sports_field_id', $field->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('time_slot_id');

        return $field->timeSlots()->where('is_active', true)->get()->map(fn ($slot) => [
            'id' => $slot->id,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'is_booked' => $bookedSlotIds->contains($slot->id),
        ])->all();
    }
}
