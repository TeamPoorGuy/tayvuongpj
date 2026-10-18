<x-mail::message>
# Thông báo trạng thái tài khoản

Xin chào {{ $targetUser->name }},

Tài khoản của bạn tại hệ thống SportHub hiện {{ $isActive ? 'đã được mở khoá trở lại.' : 'đã bị tạm khoá bởi ban quản trị.' }}

@if (! $isActive && $reason)
**Lý do tạm khoá:**
> {{ $reason }}
@endif

@if (! $isActive)
Trong thời gian tạm khoá, bạn sẽ không thể thực hiện các thao tác đặt sân hoặc quản lý cơ sở. Nếu bạn có thắc mắc hoặc cần giải trình, vui lòng liên hệ ban quản trị SportHub qua email hỗ trợ để được kiểm tra.
@else
Bạn có thể đăng nhập và tiếp tục sử dụng các dịch vụ đặt sân thể thao trên SportHub ngay bây giờ.
@endif

Trân trọng,<br>
Ban quản trị {{ config('app.name') }}
</x-mail::message>
