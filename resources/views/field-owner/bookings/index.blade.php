@extends('layouts.dashboard')

@section('title', 'Quản Lý Đơn Đặt Sân')
@section('page_title', 'Danh Sách Yêu Cầu & Đơn Đặt Sân')

@section('content')
<!-- Quick Check-in Bar -->
<div class="bg-gradient-to-r from-emerald-500 to-teal-600 p-5 rounded-2xl shadow-md text-white mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-black flex items-center gap-2">
                <span>📱</span> Check-in Nhanh Cho Khách
            </h3>
            <p class="text-xs text-emerald-100 mt-0.5">
                Nhập mã check-in (dạng CK-XXXXXXXX) khách cung cấp để xác nhận có mặt và thưởng +10 điểm uy tín.
            </p>
        </div>
        <form action="{{ route('field-owner.bookings.check-in') }}" method="POST" class="flex items-center gap-2">
            @csrf
            <input type="text" name="check_in_code" placeholder="CK-A1B2C3D4" required class="px-4 py-2 rounded-xl text-xs font-mono font-bold text-slate-900 bg-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-white">
            <button type="submit" class="px-4 py-2 bg-slate-950 hover:bg-slate-900 text-white font-extrabold text-xs rounded-xl shadow transition">
                Xác Nhận Check-in
            </button>
        </form>
    </div>
</div>

<!-- Filter bar -->
<div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-6 flex flex-wrap items-center gap-4">
    <form action="{{ route('field-owner.bookings.index') }}" method="GET" class="flex flex-wrap items-center gap-4 w-full">
        <div>
            <select name="field_id" class="px-3 py-2 border border-slate-300 text-xs font-semibold rounded-xl">
                <option value="">-- Tất cả sân --</option>
                @foreach($fields as $f)
                    <option value="{{ $f->id }}" {{ request('field_id') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <select name="status" class="px-3 py-2 border border-slate-300 text-xs font-semibold rounded-xl">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ xác nhận</option>
                <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Đã xác nhận</option>
                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Đã hoàn thành</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy / Từ chối</option>
            </select>
        </div>

        <div>
            <input type="date" name="date" value="{{ request('date') }}" class="px-3 py-2 border border-slate-300 text-xs font-semibold rounded-xl">
        </div>

        <button type="submit" class="px-4 py-2 bg-emerald-500 text-slate-950 font-bold text-xs rounded-xl shadow">Lọc dữ liệu</button>
        <a href="{{ route('field-owner.bookings.index') }}" class="px-3 py-2 bg-slate-200 text-slate-700 text-xs rounded-xl">Xóa lọc</a>
    </form>
</div>

<!-- Bookings Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs font-bold text-slate-700 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Mã đơn & Sân</th>
                    <th class="px-6 py-4">Khách hàng</th>
                    <th class="px-6 py-4">Ngày đá</th>
                    <th class="px-6 py-4">Khung giờ</th>
                    <th class="px-6 py-4">Mã Check-in</th>
                    <th class="px-6 py-4">Thành tiền</th>
                    <th class="px-6 py-4">Trạng thái</th>
                    <th class="px-6 py-4 text-right">Xử lý đơn</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($bookings as $b)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <span class="font-mono text-xs text-slate-400">#BK-{{ $b->id }}</span>
                            <span class="font-bold text-slate-900 block">{{ $b->sportsField->name }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-bold text-slate-800 block">{{ $b->customer->name }}</span>
                            <span class="text-xs text-slate-400">📞 {{ $b->customer->phone ?? 'N/A' }} · Uy tín: {{ $b->customer->trust_score ?? 100 }}⭐</span>
                        </td>
                        <td class="px-6 py-4 font-semibold text-slate-800">{{ $b->booking_date->format('d/m/Y') }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 bg-slate-100 text-xs font-mono rounded-md text-slate-700">
                                {{ substr($b->timeSlot->start_time, 0, 5) }} - {{ substr($b->timeSlot->end_time, 0, 5) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($b->check_in_code)
                                <span class="px-2 py-1 bg-slate-100 rounded-md font-mono text-xs font-bold text-slate-800 block mb-1">
                                    {{ $b->check_in_code }}
                                </span>
                            @endif
                            @if($b->checked_in_at)
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[10px] font-bold">
                                    ✓ Đã đến {{ $b->checked_in_at->format('H:i') }}
                                </span>
                            @elseif($b->no_show_at)
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded text-[10px] font-bold">
                                    ✗ Vắng mặt (No-show)
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-bold text-emerald-600">{{ number_format($b->total_price) }}đ</td>
                        <td class="px-6 py-4"><x-booking-status-badge :status="$b->status" /></td>
                        <td class="px-6 py-4 text-right space-x-1">
                            @if($b->status === 'pending')
                                <form action="{{ route('field-owner.bookings.update', $b->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs rounded-lg shadow-sm">
                                        Đồng ý
                                    </button>
                                </form>
                                <form action="{{ route('field-owner.bookings.update', $b->id) }}" method="POST" class="inline" onsubmit="return confirm('Bạn có chắc muốn từ chối đơn này? Slot sẽ được mở lại cho khách khác.')">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="px-3 py-1 bg-rose-100 hover:bg-rose-200 text-rose-700 font-bold text-xs rounded-lg">
                                        Từ chối
                                    </button>
                                </form>
                            @elseif($b->status === 'confirmed')
                                @if(! $b->checked_in_at)
                                    <form action="{{ route('field-owner.bookings.check-in') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="booking_id" value="{{ $b->id }}">
                                        <button type="submit" class="px-3 py-1 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-lg shadow-sm">
                                            📱 Check-in
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('field-owner.bookings.update', $b->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg shadow-sm">
                                        ✓ Hoàn thành
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-xs text-slate-400">Không tìm thấy đơn đặt sân nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t border-slate-100">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
