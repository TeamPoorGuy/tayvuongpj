@component('mail::message')
# Xin chào {{ $profile->owner_name ?? $profile->user->name }},

@if ($profile->verification_status->value === 'approved')
Chúc mừng! Hồ sơ đăng ký làm chủ sân của bạn cho cơ sở **{{ $profile->business_name }}** đã được duyệt.

Bạn có thể đăng nhập và bắt đầu quản lý sân ngay bây giờ.

@component('mail::button', ['url' => config('frontend.url').'/owner'])
Vào trang quản lý chủ sân
@endcomponent
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
