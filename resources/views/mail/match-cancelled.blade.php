@component('mail::message')
# Trận đấu đã bị huỷ

Rất tiếc, trận **{{ $match->title }}** đã bị huỷ (do chủ phòng huỷ hoặc sân thi đấu không còn hoạt động).

- **Ngày:** {{ $match->play_date->format('d/m/Y') }}
- **Giờ:** {{ substr($match->start_time, 0, 5) }} - {{ substr($match->end_time, 0, 5) }}
@if (!empty($reason))
- **Lý do:** {{ $reason }}
@endif

@component('mail::button', ['url' => config('frontend.url').'/matches'])
Tìm trận khác
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
