@component('mail::message')
# Báo cáo đối soát tháng {{ $statement->period }}

Báo cáo đối soát kỳ **{{ $statement->period }}** đã được lập. Tiền các đơn đã được cộng vào ví SportHub của bạn sau khi trừ hoàn tiền và hoa hồng; bạn có thể rút về ngân hàng bất cứ lúc nào.

- **Tiền khách đã thanh toán qua SportHub:** {{ number_format($statement->collected_amount, 0, ',', '.') }}đ ({{ $statement->booking_count }} đơn)
- **Hoàn tiền cho khách:** {{ number_format($statement->refunded_amount, 0, ',', '.') }}đ
- **Hoa hồng nền tảng:** {{ number_format($statement->commission_amount, 0, ',', '.') }}đ
- **Tiền về ví của bạn:** {{ number_format($statement->payout_amount, 0, ',', '.') }}đ

@component('mail::button', ['url' => config('frontend.url').'/owner/statements'])
Xem báo cáo đối soát
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
