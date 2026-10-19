@component('mail::message')
# {{ $kind === 'overdue' ? 'Phí đối soát đã quá hạn' : 'Phí đối soát nền tảng tháng '.$statement->period }}

@if ($kind === 'overdue')
Kỳ đối soát đã quá hạn nộp. Trong lúc còn nợ, tài khoản của bạn **không thể duyệt đơn mới**.
@elseif ($kind === 'due_soon')
Kỳ đối soát sắp đến hạn nộp, vui lòng chuyển khoản đúng hạn để không bị khoá duyệt đơn.
@else
Bảng đối soát phí nền tảng của bạn đã được tạo.
@endif

- **Số tiền cần nộp:** {{ number_format($statement->commission_amount, 0, ',', '.') }}đ ({{ $statement->booking_count }} đơn)
- **Hạn nộp:** {{ $statement->due_at->format('d/m/Y') }}
- **Nội dung chuyển khoản:** {{ $statement->transfer_code }}

@component('mail::button', ['url' => config('frontend.url').'/owner/statements'])
Xem & thanh toán (có mã QR)
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
