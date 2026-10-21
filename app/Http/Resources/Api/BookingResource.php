<?php

namespace App\Http\Resources\Api;

use App\Support\Banks;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_date' => $this->booking_date?->format('Y-m-d'),
            'status' => $this->status->value,
            'total_price' => (float) $this->total_price,
            'notes' => $this->notes,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'can_be_cancelled' => $this->canBeCancelled(),
            'cancel_penalty' => $this->canBeCancelled() && $this->isLateToCancel(),
            'cancel_until' => $this->cancelUntil()?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'check_in_code' => $this->check_in_code,
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'no_show' => $this->no_show_at !== null,
            'recurring_group_id' => $this->recurring_group_id,
            'payment' => [
                'status' => $this->payment_status,
                'option' => $this->payment_option,
                'amount' => $this->payment_amount !== null ? (float) $this->payment_amount : null,
                'code' => $this->payment_code,
                'due_at' => $this->payment_due_at?->toISOString(),
                'reported_at' => $this->payment_reported_at?->toISOString(),
                'paid_at' => $this->paid_at?->toISOString(),
                'deposit_amount' => $this->depositAmount(),
                'deposit_percent' => Settings::getInt('deposit_percent', 30),
                'proof_url' => $this->payment_proof_path ? url("/api/v1/bookings/{$this->id}/payment-proof") : null,
            ],
            'refund' => $this->refund_status !== null ? [
                'status' => $this->refund_status,
                'amount' => (float) $this->refund_amount,
                'refunded_at' => $this->refunded_at?->toISOString(),
            ] : null,
            'commission' => [
                'rate' => (float) ($this->commission_rate ?? Settings::getInt('commission_percent', 8)),
                'amount' => $this->commission_amount !== null ? (float) $this->commission_amount : $this->computeCommission(),
                'base' => $this->commissionBase(),
                'statement_id' => $this->statement_id,
            ],

            // Tài khoản nhận tiền: chỉ trả cho chính khách của đơn, sau khi chủ sân đã duyệt, ở endpoint chi tiết.
            'bank' => $this->when(
                $this->payment_status !== null
                    && $request->user()?->id === $this->user_id
                    && $this->relationLoaded('sportsField')
                    && $this->sportsField->relationLoaded('owner'),
                fn () => [
                    'bank_name' => $this->sportsField->owner->fieldOwnerProfile?->bank_name,
                    'bank_bin' => $this->sportsField->owner->fieldOwnerProfile?->bank_bin ?? Banks::guessBin($this->sportsField->owner->fieldOwnerProfile?->bank_name),
                    'account_number' => $this->sportsField->owner->fieldOwnerProfile?->bank_account_number,
                    'account_holder' => $this->sportsField->owner->fieldOwnerProfile?->bank_account_holder,
                ]
            ),
            'field' => $this->whenLoaded('sportsField', fn () => new FieldResource($this->sportsField)),
            'customer' => $this->whenLoaded('customer', fn () => new UserResource($this->customer)),
            // Giữ tên time_slot cho giao diện cũ; giờ lấy từ chính đơn (đơn theo khoảng giờ không có khung giờ).
            'time_slot' => [
                'id' => $this->time_slot_id,
                'start_time' => substr($this->start_time, 0, 5),
                'end_time' => substr($this->end_time, 0, 5),
            ],
            'review' => $this->whenLoaded('review', fn () => $this->review ? new ReviewResource($this->review) : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
