<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),
            'direction' => $this->direction,
            'against_user_id' => $this->against_user_id,
            'against' => $this->whenLoaded('against', fn () => $this->against ? [
                'id' => $this->against->id,
                'name' => $this->against->name,
            ] : null),
            // Admin cần số liệu của hai bên để cân nhắc (chủ sân huỷ đơn, vắng mặt, khiếu nại đã nhận).
            'parties' => $this->when($request->user()?->isAdmin() && $this->relationLoaded('user'), fn () => [
                'complainant' => $this->user ? app(\App\Services\ComplaintService::class)->partyStats($this->user) : null,
                'respondent' => $this->against ? app(\App\Services\ComplaintService::class)->partyStats($this->against) : null,
            ]),
            'response' => $this->response,
            'responded_at' => $this->responded_at?->toISOString(),
            'respond_by' => $this->respond_by?->toISOString(),
            // Người đang xem là bên bị khiếu nại, còn đang xử lý và chưa phản hồi lần nào.
            'can_respond' => $request->user()?->id !== null
                && $request->user()->id === $this->against_user_id
                && in_array($this->status, ['open', 'processing'], true)
                && $this->response === null,
            'trust_penalty' => $this->trust_penalty,
            'booking_id' => $this->booking_id,
            'booking' => $this->whenLoaded('booking', fn () => [
                'id' => $this->booking->id,
                'booking_date' => $this->booking->booking_date->toDateString(),
                'status' => $this->booking->status instanceof \BackedEnum ? $this->booking->status->value : $this->booking->status,
                'total_price' => (float) $this->booking->total_price,
                'refund_status' => $this->booking->refund_status,
                'refund_amount' => (float) $this->booking->refund_amount,
                'payment_amount' => (float) $this->booking->payment_amount,
                // Số còn hoàn thêm được: tiền khách đã trả trừ phần đã/đang hoàn (0 nếu đơn không thu qua SportHub).
                'refundable' => $this->booking->payment_id && $this->booking->payment_status === 'paid'
                    ? max(0.0, (float) $this->booking->payment_amount - (in_array($this->booking->refund_status, ['pending', 'done'], true) ? (float) ($this->booking->refund_amount ?? $this->booking->payment_amount) : 0.0))
                    : 0.0,
                'no_show' => $this->booking->no_show_at !== null,
                'field_name' => $this->booking->sportsField?->name,
            ]),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'category' => $this->category,
            'content' => $this->content,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'refund_amount' => $this->refund_amount !== null ? (float) $this->refund_amount : null,
            'attendance_confirmed' => (bool) $this->attendance_confirmed,
            'handled_by' => $this->handled_by,
            'handler' => $this->whenLoaded('handler', fn () => [
                'id' => $this->handler->id,
                'name' => $this->handler->name,
            ]),
            'handled_at' => $this->handled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
