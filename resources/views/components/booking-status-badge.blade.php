@props(['status'])

@php
    $badgeClasses = match($status) {
        'pending' => 'bg-amber-100 text-amber-800 border-amber-300',
        'confirmed' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'completed' => 'bg-blue-100 text-blue-800 border-blue-300',
        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300',
        'rejected' => 'bg-slate-100 text-slate-800 border-slate-300',
        default => 'bg-gray-100 text-gray-800 border-gray-300',
    };

    $label = match($status) {
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'completed' => 'Đã hoàn thành',
        'cancelled' => 'Đã hủy',
        'rejected' => 'Bị từ chối',
        default => $status,
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {$badgeClasses}"]) }}>
    {{ $label }}
</span>
