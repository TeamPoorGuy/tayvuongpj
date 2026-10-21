@component('mail::message')
# Có đơn đặt sân mới đã thanh toán

Khách **{{ $booking->customer->name }}** ({{ $booking->customer->phone }}) vừa đặt và thanh toán sân **{{ $booking->sportsField->name }}**.

- **Ngày:** {{ $booking->booking_date->format('d/m/Y') }}
- **Giờ:** {{ substr($booking->start_time, 0, 5) }} - {{ substr($booking->end_time, 0, 5) }}
- **Tổng tiền:** {{ number_format($booking->total_price, 0, ',', '.') }}đ
@if ($booking->notes)
- **Ghi chú:** {{ $booking->notes }}
@endif

Đơn đã được xác nhận tự động sau khi khách thanh toán qua SportHub. Vui lòng chuẩn bị sân đúng giờ.

@component('mail::button', ['url' => config('frontend.url').'/owner/bookings'])
Quản lý đơn đặt sân
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
