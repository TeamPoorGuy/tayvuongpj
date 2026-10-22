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
