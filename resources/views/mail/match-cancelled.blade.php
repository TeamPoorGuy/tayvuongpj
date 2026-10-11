@component('mail::message')
# Trận đấu đã bị huỷ

Rất tiếc, chủ phòng đã huỷ trận **{{ $match->title }}**.

- **Ngày:** {{ $match->play_date->format('d/m/Y') }}
- **Giờ:** {{ substr($match->start_time, 0, 5) }} - {{ substr($match->end_time, 0, 5) }}

@component('mail::button', ['url' => config('frontend.url').'/matches'])
Tìm trận khác
@endcomponent

Trân trọng,<br>
{{ config('app.name') }}
@endcomponent
