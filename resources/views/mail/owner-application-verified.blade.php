@component('mail::message')
# Xin chào {{ $profile->owner_name ?? $profile->user->name }},

@if ($profile->verification_status->value === 'approved')
Chúc mừng! Hồ sơ đăng ký làm chủ sân của bạn cho cơ sở **{{ $profile->business_name }}** đã được duyệt.

Bạn có thể đăng nhập và bắt đầu quản lý sân ngay bây giờ.

@component('mail::button', ['url' => config('frontend.url').'/owner'])
Vào trang quản lý chủ sân
@endcomponent
@elseif ($profile->verification_status->value === 'suspended')
Quyền chủ sân của cơ sở **{{ $profile->business_name }}** đã bị **tạm khoá**.

**Lý do:** {{ $profile->rejection_reason }}

Các sân của bạn tạm thời không hiển thị công khai. Vui lòng liên hệ quản trị viên để được hỗ trợ.
@else
Rất tiếc, hồ sơ đăng ký làm chủ sân của bạn cho cơ sở **{{ $profile->business_name }}** đã bị từ chối.

**Lý do:** {{ $profile->rejection_reason }}

Bạn có thể chỉnh sửa và gửi lại hồ sơ.

@component('mail::button', ['url' => config('frontend.url').'/become-owner'])
Nộp lại hồ sơ
@endcomponent
@endif

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
