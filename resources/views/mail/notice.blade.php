<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if ($buttonLabel && $buttonPath)
<x-mail::button :url="config('frontend.url').$buttonPath">
{{ $buttonLabel }}
</x-mail::button>
@endif

Trân trọng,<br>
{{ config('app.name') }}
</x-mail::message>
