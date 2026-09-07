@extends('layouts.admin')

@section('title', 'Bảng Điều Khiển Quản Trị')
@section('page_title', 'Thống Kê Toàn Hệ Thống SportHub')

@section('content')
<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl font-bold">👥</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Khách hàng</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalCustomers) }}</span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-purple-500/10 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl font-bold">🏢</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Chủ sân</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalFieldOwners) }}</span>
            @if($pendingOwners > 0)
                <span class="text-[10px] text-amber-600 dark:text-amber-400 block font-semibold">({{ $pendingOwners }} chờ duyệt)</span>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl font-bold">🏟️</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Tổng số sân</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalFields) }}</span>
            @if($pendingFields > 0)
                <span class="text-[10px] text-amber-600 dark:text-amber-400 block font-semibold">({{ $pendingFields }} chờ duyệt)</span>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl font-bold">💵</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Doanh thu giao dịch</span>
            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($totalRevenue) }}đ</span>
        </div>
    </div>
</div>

<!-- Most Booked Fields -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm transition-colors">
    <h3 class="font-bold text-slate-900 dark:text-white text-lg mb-4">🏆 Sân Thể Thao Được Đặt Nhiều Nhất</h3>
    <div class="space-y-3">
        @foreach($mostBookedFields as $f)
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700 flex justify-between items-center transition-colors">
                <div>
                    <span class="font-bold text-slate-900 dark:text-white text-sm block">{{ $f->name }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $f->address }}</span>
                </div>
                <div class="text-right">
                    <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 block">{{ $f->bookings_count }} lượt đặt</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($f->price_per_hour) }}đ/h</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
