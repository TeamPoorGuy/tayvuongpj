@component('mail::message')
# Có đơn đặt sân mới

Khách **{{ $booking->customer->name }}** ({{ $booking->customer->phone }}) vừa đặt sân **{{ $booking->sportsField->name }}**.

- **Ngày:** {{ $booking->booking_date->format('d/m/Y') }}
- **Giờ:** {{ substr($booking->timeSlot->start_time, 0, 5) }} - {{ substr($booking->timeSlot->end_time, 0, 5) }}
- **Tổng tiền:** {{ number_format($booking->total_price, 0, ',', '.') }}đ
@if ($booking->notes)
- **Ghi chú:** {{ $booking->notes }}
@endif

Vui lòng xác nhận hoặc từ chối đơn trong thời gian sớm nhất.

@component('mail::button', ['url' => config('frontend.url').'/owner/bookings'])
Quản lý đơn đặt sân
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
