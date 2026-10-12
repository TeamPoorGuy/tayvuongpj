@component('mail::message')
# ⚡ Có khung giờ trống dành cho bạn!

Xin chào **{{ $waitlist->user->name }}**,

Khung giờ bạn đang chờ trong danh sách hàng đợi tại **{{ $waitlist->sportsField->name }}** vừa có người hủy hoặc được giải phóng:

- **Sân:** {{ $waitlist->sportsField->name }}
- **Ngày thi đấu:** {{ $waitlist->booking_date->format('d/m/Y') }}
- **Khung giờ:** {{ substr($waitlist->timeSlot->start_time, 0, 5) }} – {{ substr($waitlist->timeSlot->end_time, 0, 5) }}
- **Thời hạn xác nhận:** {{ $waitlist->offer_expires_at?->format('H:i d/m/Y') }} (trong vòng 15 phút)

Nhờ điểm uy tín cao (**{{ $waitlist->priority_score }} điểm**), bạn là người được ưu tiên nhận giữ chỗ trước. Vui lòng bấm vào nút bên dưới để nhận sân trước khi thời hạn kết thúc:

@component('mail::button', ['url' => route('customer.waitlists.index')])
Xác nhận & Giữ sân ngay
@endcomponent

*Nếu bạn không xác nhận trong vòng 15 phút, cơ hội sẽ tự động được chuyển cho người tiếp theo trong hàng đợi.*

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
