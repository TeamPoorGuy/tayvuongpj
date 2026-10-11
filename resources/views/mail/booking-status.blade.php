@component('mail::message')
# Xin chào {{ $booking->customer->name }},

@php($status = $booking->status->value)
@if ($status === 'confirmed')
Đơn đặt sân **{{ $booking->sportsField->name }}** của bạn đã được chủ sân **xác nhận**.
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

@component('mail::button', ['url' => config('frontend.url').'/bookings'])
Xem đơn đặt sân
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
