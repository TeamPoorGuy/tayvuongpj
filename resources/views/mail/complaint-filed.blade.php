<x-mail::message>
# Khiếu nại #{{ $complaint->id }} liên quan tới bạn

Xin chào {{ $complaint->against?->name ?? 'bạn' }},

{{ $complaint->direction === 'owner_to_customer' ? 'Chủ sân' : 'Khách hàng' }} **{{ $complaint->user?->name ?? 'ẩn danh' }}** đã gửi khiếu nại về đơn đặt sân #BK-{{ $complaint->booking_id }}:
> "{{ Str::limit($complaint->content, 300) }}"

Bạn có thể gửi **một phản hồi** của mình trước {{ $complaint->respond_by?->format('H:i d/m/Y') }} trong mục Khiếu nại. Ban quản trị sẽ xem xét ý kiến của cả hai bên rồi đưa ra quyết định.

Trân trọng,<br>
Ban quản trị {{ config('app.name') }}
</x-mail::message>
