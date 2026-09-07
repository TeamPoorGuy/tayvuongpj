@extends('layouts.dashboard')

@section('title', 'Bảng Điều Khiển Chủ Sân')
@section('page_title', 'Tổng Quan Hoạt Động & Doanh Thu')

@section('content')
<!-- Stat Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl font-bold">🏟️</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Tổng số sân</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalFields) }}</span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl font-bold">📋</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Lượt đặt tháng này</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($currentMonthBookings) }}</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">/ Tổng: {{ number_format($totalBookings) }}</span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl font-bold">💰</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Doanh thu tháng</span>
            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($currentMonthRevenue) }}đ</span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-4 transition-colors">
        <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center text-2xl font-bold">🔥</div>
        <div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold block">Sân hot nhất</span>
            <span class="text-sm font-bold text-slate-900 dark:text-white line-clamp-1">{{ $mostBookedField->name ?? 'Chưa có' }}</span>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 transition-colors">
    <div class="flex justify-between items-center mb-6">
        <h3 class="font-bold text-slate-900 dark:text-white text-lg">Lượt Đặt Sân Mới Nhất</h3>
        <a href="{{ route('field-owner.bookings.index') }}" class="text-xs text-emerald-600 dark:text-emerald-400 font-bold hover:underline">Xem tất cả →</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-950 text-xs font-bold text-slate-700 dark:text-slate-400 uppercase border-b border-slate-200 dark:border-slate-700">
                <tr>
                    <th class="px-4 py-3">Sân</th>
                    <th class="px-4 py-3">Khách hàng</th>
                    <th class="px-4 py-3">Ngày & Khung giờ</th>
                    <th class="px-4 py-3">Số tiền</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Xác nhận nhanh</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($recentBookings as $b)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $b->sportsField->name }}</td>
                        <td class="px-4 py-3">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $b->customer->name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $b->customer->phone }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $b->booking_date->format('d/m/Y') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ substr($b->timeSlot->start_time, 0, 5) }} - {{ substr($b->timeSlot->end_time, 0, 5) }}</span>
                        </td>
                        <td class="px-4 py-3 font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($b->total_price) }}đ</td>
                        <td class="px-4 py-3"><x-booking-status-badge :status="$b->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if($b->status === 'pending')
                                <form action="{{ route('field-owner.bookings.update', $b->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs rounded-lg shadow-sm">Xác nhận</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-6 text-xs text-slate-500 dark:text-slate-400">Chưa có lượt đặt sân nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
