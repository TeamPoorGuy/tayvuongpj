@extends('layouts.admin')

@section('title', 'Quản Lý Phòng Ghép Đội - Admin SportHub')
@section('page_title', 'Quản Lý Phòng Ghép Đội & Tìm Đối')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-semibold rounded-xl">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm font-semibold rounded-xl">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- Filter bar -->
    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('admin.matches.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Tìm theo tiêu đề phòng..." class="px-4 py-2 bg-slate-900 border border-slate-700 text-xs text-white rounded-xl focus:outline-none focus:border-emerald-500">
            <select name="status" class="px-3 py-2 bg-slate-900 border border-slate-700 text-xs text-slate-300 rounded-xl focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Đang mở (Open)</option>
                <option value="full" {{ request('status') === 'full' ? 'selected' : '' }}>Đã đủ người (Full)</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Đã hoàn thành</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs rounded-xl transition">
                Lọc
            </button>
            <a href="{{ route('admin.matches.index') }}" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs rounded-xl transition">
                Xóa lọc
            </a>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-slate-950 rounded-2xl border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-900/60 text-slate-400 uppercase font-bold border-b border-slate-800 text-[10px]">
                    <tr>
                        <th class="px-6 py-4">Phòng đấu & Bộ môn</th>
                        <th class="px-6 py-4">Chủ phòng</th>
                        <th class="px-6 py-4">Thời gian</th>
                        <th class="px-6 py-4">Tiến độ</th>
                        <th class="px-6 py-4">Trạng thái</th>
                        <th class="px-6 py-4 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($matches as $m)
                        <tr class="hover:bg-slate-900/30 transition">
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 bg-slate-800 text-emerald-400 rounded text-[10px] font-bold">
                                    {{ $m->sportCategory->name }}
                                </span>
                                <p class="font-bold text-white text-sm mt-1">{{ $m->title }}</p>
                                @if($m->sportsField)
                                    <span class="text-[11px] text-slate-400 block mt-0.5">🏟️ {{ $m->sportsField->name }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-semibold text-white">{{ $m->host->name }}</span>
                                <span class="text-[11px] text-slate-500 block">📞 {{ $m->contact_phone }} · {{ $m->host->trust_score ?? 100 }}⭐</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-200">{{ $m->play_date->format('d/m/Y') }}</span>
                                <span class="text-[11px] text-slate-400 block">{{ substr($m->start_time, 0, 5) }} - {{ substr($m->end_time, 0, 5) }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-emerald-400">{{ $m->joined_count }} / {{ $m->players_needed + 1 }}</span>
                                <span class="text-[10px] text-slate-500 block">Thành viên</span>
                            </td>
                            <td class="px-6 py-4">
                                @if($m->status === 'open')
                                    <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 rounded-full font-bold">Đang mở</span>
                                @elseif($m->status === 'full')
                                    <span class="px-2.5 py-1 bg-sky-500/10 text-sky-400 rounded-full font-bold">Đã đủ</span>
                                @elseif($m->status === 'cancelled')
                                    <span class="px-2.5 py-1 bg-rose-500/10 text-rose-400 rounded-full font-bold">Đã hủy</span>
                                @else
                                    <span class="px-2.5 py-1 bg-slate-800 text-slate-400 rounded-full font-bold">Hoàn thành</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if(in_array($m->status, ['open', 'full']))
                                    <form action="{{ route('admin.matches.cancel', $m->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn hủy phòng thi đấu này? Thông báo email sẽ được gửi đến tất cả người tham gia.')">
                                        @csrf
                                        <input type="hidden" name="reason" value="Quản trị viên đã hủy phòng đấu do vi phạm chính sách hoặc lý do an toàn.">
                                        <button type="submit" class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 rounded-lg font-bold text-xs transition">
                                            Hủy Phòng
                                        </button>
                                    </form>
                                @else
                                    <span class="text-slate-600 text-xs italic">Đã đóng</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                Không tìm thấy phòng đấu nào.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-800">
            {{ $matches->links() }}
        </div>
    </div>
</div>
@endsection
