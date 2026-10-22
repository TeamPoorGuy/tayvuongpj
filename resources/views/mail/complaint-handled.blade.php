<x-mail::message>
# Phản hồi khiếu nại #{{ $complaint->id }}

Xin chào {{ $complaint->user?->name ?? 'quý khách' }},

Quản trị viên SportHub đã xem xét và phản hồi khiếu nại của bạn về nội dung:
> "{{ Str::limit($complaint->content, 120) }}"

**Trạng thái xử lý:** {{ match ($complaint->status) { 'resolved' => 'Đã giải quyết thoả đáng', 'processing' => 'Đang xác minh', default => 'Đã phản hồi / Từ chối' } }}

**Kết quả giải quyết:**
{{ $complaint->resolution ?: 'Không có nội dung bổ sung.' }}

@if ($complaint->refund_amount)
**Hoàn tiền:** SportHub sẽ hoàn {{ number_format($complaint->refund_amount, 0, ',', '.') }}đ cho bạn qua tài khoản đã thanh toán.

@endif
@if ($complaint->attendance_confirmed)
**Điểm uy tín:** SportHub đã ghi nhận bạn có đến sân, gỡ ghi nhận vắng mặt và hoàn lại 25 điểm uy tín.

@endif
Nếu bạn cần hỗ trợ thêm, vui lòng phản hồi email này hoặc liên hệ hotline chăm sóc khách hàng của SportHub.

Trân trọng,<br>
Ban quản trị {{ config('app.name') }}
</x-mail::message>
