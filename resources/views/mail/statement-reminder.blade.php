@component('mail::message')
# {{ $kind === 'paid_out' ? 'SportHub đã chuyển tiền cho bạn' : 'Bảng đối soát tháng '.$statement->period }}

@if ($kind === 'paid_out')
SportHub đã chuyển khoản tiền thu hộ kỳ **{{ $statement->period }}** vào tài khoản ngân hàng của bạn.
@else
Bảng đối soát kỳ **{{ $statement->period }}** đã được lập. SportHub sẽ chuyển khoản cho bạn trước {{ $statement->due_at?->format('d/m/Y') }}.
@endif

- **Tiền khách đã thanh toán qua SportHub:** {{ number_format($statement->collected_amount, 0, ',', '.') }}đ ({{ $statement->booking_count }} đơn)
- **Hoàn tiền cho khách:** {{ number_format($statement->refunded_amount, 0, ',', '.') }}đ
- **Hoa hồng nền tảng:** {{ number_format($statement->commission_amount, 0, ',', '.') }}đ
- **Số tiền chi trả cho bạn:** {{ number_format($statement->payout_amount, 0, ',', '.') }}đ
@if ($statement->payout_ref)
- **Mã giao dịch:** {{ $statement->payout_ref }}
@endif

@component('mail::button', ['url' => config('frontend.url').'/owner/statements'])
Xem chi tiết đối soát
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
