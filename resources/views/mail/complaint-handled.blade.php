<x-mail::message>
# Phản hồi khiếu nại #{{ $complaint->id }}

Xin chào {{ $complaint->user?->name ?? 'quý khách' }},

Quản trị viên SportHub đã xem xét và phản hồi khiếu nại của bạn về nội dung:
> "{{ Str::limit($complaint->content, 120) }}"

**Trạng thái xử lý:** {{ $complaint->status === 'resolved' ? 'Đã giải quyết thoả đáng' : 'Đã phản hồi / Từ chối' }}

**Kết quả giải quyết:**
{{ $complaint->resolution ?: 'Không có nội dung bổ sung.' }}

Nếu bạn cần hỗ trợ thêm, vui lòng phản hồi email này hoặc liên hệ hotline chăm sóc khách hàng của SportHub.

Trân trọng,<br>
Ban quản trị {{ config('app.name') }}
</x-mail::message>
