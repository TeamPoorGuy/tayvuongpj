@extends('layouts.app')

@section('title', 'Cộng Đồng Ghép Đội & Tìm Đối Thủ - SportHub')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-900 flex items-center gap-2">
                🤝 Ghép Đội & Tìm Đối Thủ
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Tìm kiếm đối thủ giao lưu, kêu gọi thêm đồng đội cho các trận bóng đá, cầu lông, tennis, bóng rổ, bóng chuyền.
            </p>
        </div>
        @auth
            <a href="{{ route('customer.matches.create') }}" class="px-5 py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-xl shadow-md hover:scale-[1.02] transition flex items-center gap-2 text-sm">
                <span>➕</span> Đăng Tin Tìm Đối
            </a>
        @else
            <a href="{{ route('login') }}" class="px-5 py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-xl shadow-md transition text-sm">
                Đăng Nhập Để Tạo Phòng
            </a>
        @endauth
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold rounded-xl">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold rounded-xl">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-8">
        <form method="GET" action="{{ route('customer.matches.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Môn thể thao</label>
                <select name="category_id" class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">Tất cả bộ môn</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Ngày thi đấu</label>
                <input type="date" name="date" value="{{ request('date') }}" class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Trình độ</label>
                <select name="skill_level" class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="any">Tất cả trình độ</option>
                    <option value="beginner" {{ request('skill_level') == 'beginner' ? 'selected' : '' }}>Mới chơi (Beginner)</option>
                    <option value="intermediate" {{ request('skill_level') == 'intermediate' ? 'selected' : '' }}>Trung bình (Intermediate)</option>
                    <option value="advanced" {{ request('skill_level') == 'advanced' ? 'selected' : '' }}>Bán chuyên / Chuyên nghiệp</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition">
                    Lọc Trận
                </button>
                <a href="{{ route('customer.matches.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                    Xóa
                </a>
            </div>
        </form>
    </div>

    <!-- Match List Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($matches as $match)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-lg text-xs font-bold">
                            {{ $match->sportCategory->name }}
                        </span>
                        @if($match->status === 'full')
                            <span class="px-2.5 py-1 bg-slate-200 text-slate-700 rounded-full text-[10px] font-bold">Đã Đủ Người</span>
                        @else
                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[10px] font-bold">
                                Còn thiếu {{ max(0, ($match->players_needed + 1) - $match->joined_count) }} người
                            </span>
                        @endif
                    </div>

                    <a href="{{ route('customer.matches.show', $match->id) }}" class="font-black text-lg text-slate-900 hover:text-emerald-600 transition block mb-2">
                        {{ $match->title }}
                    </a>

                    <div class="space-y-1.5 text-xs text-slate-600 mb-4">
                        <p class="flex items-center gap-1.5">
                            <span>📅</span> <span class="font-bold text-slate-800">{{ $match->play_date->format('d/m/Y') }}</span> ({{ substr($match->start_time, 0, 5) }} - {{ substr($match->end_time, 0, 5) }})
                        </p>
                        @if($match->sportsField)
                            <p class="flex items-center gap-1.5">
                                <span>🏟️</span> <span class="font-semibold text-slate-700">{{ $match->sportsField->name }}</span>
                            </p>
                        @endif
                        <p class="flex items-center gap-1.5">
                            <span>🎯</span> Trình độ:
                            <span class="font-semibold text-slate-800">
                                @if($match->skill_level === 'beginner') Mới chơi
                                @elseif($match->skill_level === 'intermediate') Trung bình
                                @elseif($match->skill_level === 'advanced') Chuyên nghiệp
                                @else Bất kỳ
                                @endif
                            </span>
                        </p>
                    </div>

                    @if($match->description)
                        <p class="text-xs text-slate-500 line-clamp-2 mb-4 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            {{ $match->description }}
                        </p>
                    @endif
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-emerald-500 text-slate-950 font-black rounded-full flex items-center justify-center text-xs">
                            {{ substr($match->host->name, 0, 1) }}
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800">{{ $match->host->name }}</p>
                            <p class="text-[10px] text-slate-400">Uy tín: {{ $match->host->trust_score ?? 100 }}⭐</p>
                        </div>
                    </div>

                    <a href="{{ route('customer.matches.show', $match->id) }}" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition">
                        Chi tiết
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 bg-white rounded-2xl border border-slate-200">
                <span class="text-4xl block mb-2">⚽</span>
                <p class="text-slate-600 font-bold text-sm">Hiện chưa có phòng thi đấu nào phù hợp với bộ lọc.</p>
                <p class="text-xs text-slate-400 mt-1">Hãy là người đầu tiên tạo phòng tìm đối thủ!</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $matches->links() }}
    </div>
</div>
@endsection
