<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ForbiddenException;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Review;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AdminService
{
    public function dashboard(): array
    {
        return [
            'total_customers' => User::where('role', UserRole::Customer->value)->count(),
            'total_field_owners' => User::where('role', UserRole::FieldOwner->value)->count(),
            'pending_owners' => FieldOwnerProfile::where('verification_status', 'pending')->count(),
            'total_fields' => SportsField::count(),
            'pending_fields' => SportsField::where('status', 'pending')->count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_price'),
            'most_booked_fields' => SportsField::with(['primaryImage', 'fieldType.sportCategory', 'reviews'])->withCount('bookings')->orderByDesc('bookings_count')->take(5)->get(),
        ];
    }

    public function users(array $filters): LengthAwarePaginator
    {
        return User::where('role', '!=', UserRole::Admin->value)
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('phone', 'like', "%{$keyword}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function toggleUser(User $user): User
    {
        if ($user->role === UserRole::Admin->value) {
            throw new ForbiddenException('Không thể khóa tài khoản quản trị viên.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return $user->refresh();
    }

    public function ownerProfiles(): LengthAwarePaginator
    {
        return FieldOwnerProfile::with('user')->latest()->paginate(10);
    }

    public function verifyOwner(FieldOwnerProfile $profile, string $status): FieldOwnerProfile
    {
        $profile->update(['verification_status' => $status]);

        return $profile->refresh()->load('user');
    }

    public function fields(?string $status): LengthAwarePaginator
    {
        return SportsField::with(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage', 'reviews'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function verifyField(SportsField $field, string $status): SportsField
    {
        $field->update(['status' => $status]);

        return $field->refresh()->load(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage', 'reviews']);
    }

    public function bookings(?string $status): LengthAwarePaginator
    {
        return Booking::with(['sportsField.primaryImage', 'sportsField.reviews', 'customer', 'timeSlot', 'review'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function reviews(): LengthAwarePaginator
    {
        return Review::with(['user', 'sportsField'])->latest()->paginate(15);
    }

    public function toggleReview(Review $review): Review
    {
        $review->update(['is_visible' => ! $review->is_visible]);

        return $review->refresh()->load(['user', 'sportsField']);
    }

    public function categories(): Collection
    {
        return SportCategory::with('fieldTypes')->withCount('fieldTypes')->latest()->get();
    }

    public function createCategory(array $data): SportCategory
    {
        return SportCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'] ?? '⚽',
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ])->load('fieldTypes')->loadCount('fieldTypes');
    }

    public function createFieldType(array $data): FieldType
    {
        return FieldType::create([
            'sport_category_id' => $data['sport_category_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ])->load('sportCategory');
    }
}
