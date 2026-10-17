@component('mail::message')
# Xin chào {{ $booking->customer->name }},

@php($status = $booking->status->value)
@php($awaitingPayment = $status === 'pending' && $booking->payment_status === 'awaiting')
@if ($awaitingPayment)
Chủ sân đã **duyệt** đơn đặt sân **{{ $booking->sportsField->name }}** của bạn. Vui lòng chuyển khoản trước **{{ $booking->payment_due_at->format('H:i d/m/Y') }}** để giữ sân; quá hạn đơn sẽ tự huỷ.
@elseif ($status === 'confirmed')
Đơn đặt sân **{{ $booking->sportsField->name }}** của bạn đã được chủ sân **xác nhận**@if ($booking->payment_status === 'paid') (đã nhận {{ number_format($booking->payment_amount, 0, ',', '.') }}đ)@endif.
@elseif ($status === 'rejected')
Rất tiếc, đơn đặt sân **{{ $booking->sportsField->name }}** của bạn **không được chấp nhận**.
@elseif (str_contains((string) $booking->cancel_reason, 'Sân đã bị gỡ'))
Rất tiếc, đơn đặt sân **{{ $booking->sportsField->name }}** của bạn đã bị **huỷ do sân thể thao bị gỡ khỏi hệ thống**.
@else
Đơn đặt sân **{{ $booking->sportsField->name }}** của bạn đã bị **huỷ**.
@endif

- **Ngày:** {{ $booking->booking_date->format('d/m/Y') }}
- **Giờ:** {{ substr($booking->timeSlot->start_time, 0, 5) }} - {{ substr($booking->timeSlot->end_time, 0, 5) }}
- **Tổng tiền:** {{ number_format($booking->total_price, 0, ',', '.') }}đ
@if ($booking->cancel_reason)
- **Lý do:** {{ $booking->cancel_reason }}
@endif

@component('mail::button', ['url' => config('frontend.url').($awaitingPayment ? "/bookings/{$booking->id}/payment" : '/bookings')])
{{ $awaitingPayment ? 'Thanh toán ngay' : 'Xem đơn đặt sân' }}
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
