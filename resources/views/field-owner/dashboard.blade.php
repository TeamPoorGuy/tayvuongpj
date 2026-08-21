@extends('layouts.dashboard')

@section('title', 'Bảng Điều Khiển Chủ Sân')
@section('page_title', 'Tổng Quan Hoạt Động & Doanh Thu')

@section('content')
<!-- Stat Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl font-bold">🏟️</div>
        <div>
            <span class="text-xs text-slate-400 font-semibold block">Tổng số sân</span>
            <span class="text-2xl font-black text-slate-900">{{ number_format($totalFields) }}</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-2xl font-bold">📋</div>
        <div>
            <span class="text-xs text-slate-400 font-semibold block">Lượt đặt tháng này</span>
            <span class="text-2xl font-black text-slate-900">{{ number_format($currentMonthBookings) }}</span>
            <span class="text-[10px] text-slate-400">/ Tổng: {{ number_format($totalBookings) }}</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl font-bold">💰</div>
        <div>
            <span class="text-xs text-slate-400 font-semibold block">Doanh thu tháng</span>
            <span class="text-xl font-black text-emerald-600">{{ number_format($currentMonthRevenue) }}đ</span>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-2xl font-bold">🔥</div>
        <div>
            <span class="text-xs text-slate-400 font-semibold block">Sân hot nhất</span>
            <span class="text-sm font-bold text-slate-900 line-clamp-1">{{ $mostBookedField->name ?? 'Chưa có' }}</span>
        </div>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
        <h3 class="font-bold text-slate-900 text-lg">Lượt Đặt Sân Mới Nhất</h3>
        <a href="{{ route('field-owner.bookings.index') }}" class="text-xs text-emerald-600 font-bold hover:underline">Xem tất cả →</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs font-bold text-slate-700 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Sân</th>
                    <th class="px-4 py-3">Khách hàng</th>
                    <th class="px-4 py-3">Ngày & Khung giờ</th>
                    <th class="px-4 py-3">Số tiền</th>
                    <th class="px-4 py-3">Trạng thái</th>
                    <th class="px-4 py-3 text-right">Xác nhận nhanh</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentBookings as $b)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $b->sportsField->name }}</td>
                        <td class="px-4 py-3">
                            <span class="font-bold text-slate-800">{{ $b->customer->name }}</span>
                            <span class="block text-xs text-slate-400">{{ $b->customer->phone }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-bold">{{ $b->booking_date->format('d/m/Y') }}</span>
                            <span class="block text-xs text-slate-500">{{ substr($b->timeSlot->start_time, 0, 5) }} - {{ substr($b->timeSlot->end_time, 0, 5) }}</span>
                        </td>
                        <td class="px-4 py-3 font-bold text-emerald-600">{{ number_format($b->total_price) }}đ</td>
                        <td class="px-4 py-3"><x-booking-status-badge :status="$b->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if($b->status === 'pending')
                                <form action="{{ route('field-owner.bookings.update', $b->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs rounded-lg">Xác nhận</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-6 text-xs text-slate-400">Chưa có lượt đặt sân nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
