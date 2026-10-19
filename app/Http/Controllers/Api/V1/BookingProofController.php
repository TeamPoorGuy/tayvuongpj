<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BookingProofController extends Controller
{
    public function show(Request $request, Booking $booking): Response
    {
        $user = $request->user();
        $isCustomer = $user->id === $booking->user_id;
        $isOwner = $user->id === $booking->sportsField->field_owner_id;
        $isAdmin = $user->isAdmin();

        abort_unless($isCustomer || $isOwner || $isAdmin, 403, 'Bạn không có quyền xem chứng từ thanh toán của đơn này.');

        if (! $booking->payment_proof_path || ! Storage::disk('local')->exists($booking->payment_proof_path)) {
            abort(404, 'Không tìm thấy chứng từ thanh toán.');
        }

        return Storage::disk('local')->response($booking->payment_proof_path);
    }
}
