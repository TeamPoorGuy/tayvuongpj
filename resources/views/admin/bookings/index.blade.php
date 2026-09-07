@extends('layouts.admin')

@section('title', 'Quản Lý Đơn Đặt Sân Toàn Hệ Thống')
@section('page_title', 'Toàn Bộ Đơn Đặt Sân Trên Hệ Thống')

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden transition-colors">
    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
        <thead class="bg-slate-100 dark:bg-slate-950 text-xs font-bold text-slate-600 dark:text-slate-400 uppercase border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="px-6 py-4">Mã & Sân</th>
                <th class="px-6 py-4">Khách hàng</th>
                <th class="px-6 py-4">Ngày & Khung giờ</th>
                <th class="px-6 py-4">Tổng tiền</th>
                <th class="px-6 py-4">Trạng thái</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($bookings as $b)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                    <td class="px-6 py-4">
                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">#BK-{{ $b->id }}</span>
                        <span class="font-bold text-slate-900 dark:text-white block">{{ $b->sportsField->name }}</span>
                    </td>
                    <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200">{{ $b->customer->name }}</td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-bold block text-slate-900 dark:text-white">{{ $b->booking_date->format('d/m/Y') }}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ substr($b->timeSlot->start_time, 0, 5) }} - {{ substr($b->timeSlot->end_time, 0, 5) }}</span>
                    </td>
                    <td class="px-6 py-4 font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($b->total_price) }}đ</td>
                    <td class="px-6 py-4"><x-booking-status-badge :status="$b->status" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
