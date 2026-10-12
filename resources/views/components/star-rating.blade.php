@props(['rating' => 5])

<div class="flex items-center text-amber-400">
    @for($i = 1; $i <= 5; $i++)
        @if($i <= round($rating))
            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
        @else
            <svg class="w-4 h-4 fill-slate-300" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
        @endif
    @endfor
    <span class="ml-1 text-xs font-semibold text-slate-600">{{ number_format($rating, 1) }}</span>
</div>
