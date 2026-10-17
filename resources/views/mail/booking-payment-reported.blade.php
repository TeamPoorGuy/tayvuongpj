@component('mail::message')
# Khách báo đã chuyển khoản

Khách **{{ $booking->customer->name }}** báo đã chuyển khoản cho đơn đặt sân **{{ $booking->sportsField->name }}**.

- **Ngày:** {{ $booking->booking_date->format('d/m/Y') }}
- **Giờ:** {{ substr($booking->timeSlot->start_time, 0, 5) }} - {{ substr($booking->timeSlot->end_time, 0, 5) }}
- **Số tiền:** {{ number_format($booking->payment_amount, 0, ',', '.') }}đ ({{ $booking->payment_option === 'full' ? 'trả hết' : 'đặt cọc' }})
- **Nội dung chuyển khoản:** {{ $booking->payment_code }}

Hãy kiểm tra sao kê ngân hàng rồi bấm **Đã nhận tiền** hoặc **Chưa nhận được**.

@component('mail::button', ['url' => config('frontend.url').'/owner/bookings'])
Đối chiếu thanh toán
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
