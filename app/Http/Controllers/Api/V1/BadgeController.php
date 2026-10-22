<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingWaitlist;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\SportsField;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Số việc đang chờ người dùng, theo đường dẫn menu: để huy hiệu trên menu thay cho việc phải vào từng trang kiểm tra. */
class BadgeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json(['data' => array_filter([
                '/admin/owners' => FieldOwnerProfile::where('verification_status', VerificationStatus::Pending)->count(),
                '/admin/fields' => SportsField::where('status', 'pending')->count(),
                '/admin/complaints' => Complaint::where('status', 'open')->count(),
                '/admin/withdrawals' => Withdrawal::where('status', 'pending')->count(),
            ])]);
        }

        // Khiếu nại nhắm vào mình mà chưa phản hồi: cả khách và chủ sân đều có thể bị khiếu nại.
        $toRespond = Complaint::active()->where('against_user_id', $user->id)->whereNull('response')->count();
        $badges = [
            '/bookings' => $toRespond + BookingWaitlist::where('user_id', $user->id)->where('status', 'offered')->count(),
        ];

        if ($user->isApprovedOwner()) {
            $fieldIds = SportsField::withTrashed()->where('field_owner_id', $user->id)->pluck('id');
            $badges['/owner/complaints'] = $toRespond;
            // Đơn hôm nay đã xác nhận mà khách chưa check-in.
            $badges['/owner/bookings'] = Booking::whereIn('sports_field_id', $fieldIds)
                ->where('status', BookingStatus::Confirmed->value)
                ->whereDate('booking_date', today())
                ->whereNull('checked_in_at')
                ->count();
        }

        return response()->json(['data' => array_filter($badges)]);
    }
}
