<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Mail\BookingStatusChanged;
use App\Mail\MatchCancelled;
use App\Mail\OwnerApplicationVerified;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\MatchRequest;
use App\Models\Review;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminService
{
    public function dashboard(): array
    {
        return [
            'total_customers' => User::where('role', UserRole::Customer->value)->count(),
            'total_field_owners' => FieldOwnerProfile::where('verification_status', VerificationStatus::Approved)->count(),
            'pending_owners' => FieldOwnerProfile::where('verification_status', VerificationStatus::Pending)->count(),
            'total_fields' => SportsField::count(),
            'pending_fields' => SportsField::where('status', 'pending')->count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_price'),
            'most_booked_fields' => SportsField::with(['primaryImage', 'fieldType.sportCategory'])->withRating()->withCount('bookings')->orderByDesc('bookings_count')->take(5)->get(),
        ];
    }

    public function users(array $filters): LengthAwarePaginator
    {
        return User::where('role', '!=', UserRole::Admin->value)
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%".addcslashes($keyword, '%_\\')."%")
                ->orWhere('email', 'like', "%".addcslashes($keyword, '%_\\')."%")
                ->orWhere('phone', 'like', "%".addcslashes($keyword, '%_\\')."%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function toggleUser(User $user): User
    {
        if ($user->role === UserRole::Admin->value) {
            throw new ForbiddenException('Không thể khóa tài khoản quản trị viên.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return $user->refresh();
    }

    public function ownerProfiles(?string $status = null): LengthAwarePaginator
    {
        return FieldOwnerProfile::with(['user', 'documents'])
            ->when($status, fn ($query) => $query->where('verification_status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();
    }

    public function verifyOwner(FieldOwnerProfile $profile, string $status, ?string $reason, User $admin): FieldOwnerProfile
    {
        $next = VerificationStatus::from($status);
        if (! $profile->verification_status->canTransitionTo($next)) {
            throw new ConflictException('Hồ sơ này đã được xử lý hoặc không thể chuyển sang trạng thái này.');
        }

        $profile->update([
            'verification_status' => $next,
            'rejection_reason' => $next === VerificationStatus::Approved ? null : $reason,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $profile->refresh()->load(['user', 'documents']);

        // Gửi mail qua queue; lỗi mail không được chặn việc duyệt hồ sơ.
        SafeMail::queue($profile->user->email, new OwnerApplicationVerified($profile));

        return $profile;
    }

    private function like(string $keyword): string
    {
        return '%'.addcslashes($keyword, '%_\\').'%';
    }

    public function fields(?string $status, ?string $keyword = null): LengthAwarePaginator
    {
        return SportsField::with(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage'])
            ->withRating()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', $this->like($keyword))
                ->orWhere('address', 'like', $this->like($keyword))
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function verifyField(SportsField $field, string $status, ?string $reason = null, bool $cancelFutureBookings = false): SportsField
    {
        DB::transaction(function () use ($field, $status, $reason, $cancelFutureBookings) {
            $field->update([
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? $reason : null,
            ]);

            if ($status === 'rejected') {
                // Sân đã bị gỡ: hàng đợi của sân này không còn ý nghĩa, đóng để khách không nhận lời mời giữ chỗ vô ích.
                BookingWaitlist::where('sports_field_id', $field->id)
                    ->whereIn('status', ['waiting', 'offered'])
                    ->update(['status' => 'cancelled']);

                // Sân đã bị gỡ: đóng các phòng tìm đối sắp tới gắn với sân này và gửi mail cho chủ phòng + người đã duyệt.
                $affectedMatches = MatchRequest::with(['host', 'participants.user'])
                    ->where('sports_field_id', $field->id)
                    ->whereIn('status', ['open', 'full'])
                    ->whereDate('play_date', '>=', today())
                    ->get();

                foreach ($affectedMatches as $match) {
                    if ($match->play_date->isToday() && Carbon::parse($match->end_time)->isPast()) {
                        continue;
                    }

                    $match->update(['status' => 'cancelled']);

                    $matchReason = $reason ? "Sân thi đấu đã bị gỡ khỏi hệ thống: {$reason}" : 'Sân thi đấu đã bị gỡ khỏi hệ thống.';

                    if ($match->host?->email) {
                        SafeMail::queue($match->host->email, new MatchCancelled($match, $matchReason));
                    }
                    foreach ($match->participants->where('status', 'accepted') as $participant) {
                        if ($participant->user?->email) {
                            SafeMail::queue($participant->user->email, new MatchCancelled($match, $matchReason));
                        }
                    }
                }
            }

            if ($status === 'rejected' && $cancelFutureBookings) {
                $cancelReason = $reason ? "Sân đã bị gỡ khỏi hệ thống: {$reason}" : 'Sân đã bị gỡ khỏi hệ thống.';

                $futureBookings = Booking::with(['customer', 'sportsField', 'timeSlot'])
                    ->where('sports_field_id', $field->id)
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->whereDate('booking_date', '>=', today())
                    ->get();

                foreach ($futureBookings as $booking) {
                    if ($booking->booking_date->isToday() && $booking->endsAt()->isPast()) {
                        continue;
                    }

                    $booking->update([
                        'status' => BookingStatus::Cancelled->value,
                        'cancelled_at' => now(),
                        'cancel_reason' => $cancelReason,
                    ]);

                    if ($booking->customer?->email) {
                        SafeMail::queue($booking->customer->email, new BookingStatusChanged($booking));
                    }
                }
            }
        });

        return $field->refresh()->load(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage', 'reviews']);
    }

    public function bookings(?string $status, ?string $keyword = null, ?string $date = null): LengthAwarePaginator
    {
        return Booking::with(Booking::resourceRelations(withCustomer: true))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($date, fn ($query) => $query->whereDate('booking_date', $date))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('customer', fn ($c) => $c->where('name', 'like', $this->like($keyword))
                    ->orWhere('email', 'like', $this->like($keyword))
                    ->orWhere('phone', 'like', $this->like($keyword)))
                ->orWhereHas('sportsField', fn ($f) => $f->where('name', 'like', $this->like($keyword)))))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function matches(?string $status, ?string $keyword = null): LengthAwarePaginator
    {
        return MatchRequest::with(['host', 'sportCategory', 'sportsField', 'participants.user'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($keyword, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', $this->like($keyword))
                ->orWhereHas('host', fn ($host) => $host->where('name', 'like', $this->like($keyword)))))
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

    public function updateCategory(SportCategory $category, array $data): SportCategory
    {
        // Giữ nguyên slug để link /fields?category=... không bị gãy.
        $category->update($data);

        return $category->load('fieldTypes')->loadCount('fieldTypes');
    }

    public function updateFieldType(FieldType $type, array $data): FieldType
    {
        $type->update($data);

        return $type->load('sportCategory');
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
