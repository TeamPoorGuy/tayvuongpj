@extends('layouts.app')

@section('title', $match->title . ' - SportHub')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('customer.matches.index') }}" class="text-xs font-bold text-slate-500 hover:text-emerald-600 transition flex items-center gap-1 mb-2">
            ← Quay lại danh sách phòng
        </a>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-lg text-xs font-bold">
                        {{ $match->sportCategory->name }}
                    </span>
                    @if($match->status === 'full')
                        <span class="px-3 py-1 bg-slate-200 text-slate-700 rounded-full text-xs font-bold">Phòng Đã Đủ Người</span>
                    @elseif($match->status === 'cancelled')
                        <span class="px-3 py-1 bg-rose-100 text-rose-800 rounded-full text-xs font-bold">Đã Hủy</span>
                    @elseif($match->status === 'completed')
                        <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-bold">Đã Hoàn Thành</span>
                    @else
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-bold">
                            Đang mở · Cần thêm {{ max(0, ($match->players_needed + 1) - $match->joined_count) }} người
                        </span>
                    @endif
                </div>

                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mb-4">{{ $match->title }}</h1>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 bg-slate-50 rounded-xl border border-slate-100 mb-6 text-xs">
                    <div>
                        <span class="text-slate-400 block mb-0.5">Ngày thi đấu</span>
                        <span class="font-extrabold text-slate-800">{{ $match->play_date->format('d/m/Y') }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Khung giờ</span>
                        <span class="font-extrabold text-slate-800">{{ substr($match->start_time, 0, 5) }} - {{ substr($match->end_time, 0, 5) }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Trình độ</span>
                        <span class="font-extrabold text-slate-800">
                            @if($match->skill_level === 'beginner') Mới chơi
                            @elseif($match->skill_level === 'intermediate') Trung bình
                            @elseif($match->skill_level === 'advanced') Chuyên nghiệp
                            @else Bất kỳ
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Số người tham gia</span>
                        <span class="font-extrabold text-emerald-700">{{ $match->joined_count }} / {{ $match->players_needed + 1 }}</span>
                    </div>
                    @if($match->sportsField)
                        <div class="col-span-2">
                            <span class="text-slate-400 block mb-0.5">Địa điểm</span>
                            <a href="{{ route('customer.fields.show', $match->sportsField->slug) }}" class="font-extrabold text-emerald-700 hover:underline">
                                {{ $match->sportsField->name }} 📍
                            </a>
                        </div>
                    @endif
                </div>

                @if($match->description)
                    <div class="mb-6">
                        <h3 class="font-bold text-sm text-slate-900 mb-2">Mô tả & Ghi chú từ chủ phòng:</h3>
                        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                            {{ $match->description }}
                        </p>
                    </div>
                @endif

                @if($match->sportsField)
                    <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-between gap-4">
                        <div class="text-xs">
                            <p class="font-bold text-emerald-950">Chưa đặt sân cho trận này?</p>
                            <p class="text-emerald-700">Kiểm tra khung giờ trống và đặt sân ngay trên hệ thống SportHub.</p>
                        </div>
                        <a href="{{ route('customer.fields.show', $match->sportsField->slug) }}" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition whitespace-nowrap">
                            Xem & Đặt Sân
                        </a>
                    </div>
                @endif
            </div>

            <!-- Participants list -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-black text-lg text-slate-900 mb-4 flex items-center justify-between">
                    <span>Thành Viên Tham Gia ({{ $match->participants->count() + 1 }})</span>
                    <span class="text-xs font-semibold text-slate-400">Chỉ tiêu: {{ $match->players_needed + 1 }} người</span>
                </h3>

                <div class="space-y-3">
                    <!-- Host card -->
                    <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-emerald-500 text-slate-950 font-black rounded-full flex items-center justify-center text-xs">
                                {{ substr($match->host->name, 0, 1) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-slate-900">{{ $match->host->name }}</span>
                                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md text-[10px] font-black">Chủ Phòng</span>
                                </div>
                                <span class="text-[10px] text-slate-400">Điểm uy tín: {{ $match->host->trust_score ?? 100 }}⭐</span>
                            </div>
                        </div>
                    </div>

                    <!-- Other participants -->
                    @forelse($match->participants as $part)
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-slate-200 text-slate-700 font-bold rounded-full flex items-center justify-center text-xs">
                                    {{ substr($part->user->name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-slate-900">{{ $part->user->name }}</span>
                                        @if($part->status === 'accepted')
                                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md text-[10px] font-bold">Đã Duyệt</span>
                                        @elseif($part->status === 'pending')
                                            <span class="px-2 py-0.5 bg-sky-100 text-sky-800 rounded-md text-[10px] font-bold">Chờ Duyệt</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-rose-100 text-rose-800 rounded-md text-[10px] font-bold">Từ Chối</span>
                                        @endif
                                    </div>
                                    @if($part->message)
                                        <p class="text-[11px] text-slate-500 mt-0.5 italic">"{{ $part->message }}"</p>
                                    @endif
                                </div>
                            </div>

                            @auth
                                @if(Auth::id() === $match->user_id && $part->status === 'pending')
                                    <div class="flex items-center gap-2">
                                        <form action="{{ route('customer.matches.participants.update', [$match->id, $part->id]) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="accepted">
                                            <button type="submit" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-lg text-xs transition">
                                                Duyệt
                                            </button>
                                        </form>
                                        <form action="{{ route('customer.matches.participants.update', [$match->id, $part->id]) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="px-3 py-1 bg-slate-100 hover:bg-rose-100 text-slate-600 hover:text-rose-700 font-bold rounded-lg text-xs transition">
                                                Từ chối
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            @endauth
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic py-2">Chưa có người nào xin tham gia phòng này.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-slate-900">Liên hệ & Thao tác</h3>

                <!-- Contact phone: only visible to host or accepted members -->
                @auth
                    @php
                        $isAccepted = $match->participants->where('user_id', Auth::id())->where('status', 'accepted')->isNotEmpty();
                        $isHost = Auth::id() === $match->user_id;
                    @endphp

                    @if($isHost || $isAccepted)
                        <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                            <span class="text-[11px] text-emerald-800 block mb-0.5 font-semibold">Số điện thoại liên hệ</span>
                            <span class="text-base font-black text-emerald-950">📞 {{ $match->contact_phone }}</span>
                        </div>
                    @else
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs text-slate-500">
                            🔒 Số điện thoại sẽ hiển thị sau khi bạn được chủ phòng duyệt tham gia.
                        </div>
                    @endif

                    <!-- User status in this match -->
                    @if($isHost)
                        @if($match->isOpen() || $match->status === 'full')
                            <form action="{{ route('customer.matches.cancel', $match->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn hủy phòng đấu này?')">
                                @csrf
                                <button type="submit" class="w-full py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl text-xs border border-rose-200 transition">
                                    Hủy Phòng Đấu Này
                                </button>
                            </form>
                        @endif
                    @else
                        @php
                            $userParticipant = $match->participants->where('user_id', Auth::id())->first();
                        @endphp

                        @if($userParticipant)
                            <div class="p-3 bg-slate-100 rounded-xl text-center text-xs text-slate-700 font-semibold">
                                Trạng thái của bạn:
                                @if($userParticipant->status === 'accepted')
                                    <span class="text-emerald-700 font-bold">Đã được duyệt tham gia</span>
                                @elseif($userParticipant->status === 'pending')
                                    <span class="text-amber-700 font-bold">Đang chờ chủ phòng duyệt</span>
                                @else
                                    <span class="text-rose-700 font-bold">Bị từ chối</span>
                                @endif
                            </div>

                            <form action="{{ route('customer.matches.leave', $match->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn rút lui khỏi trận này?')">
                                @csrf
                                <button type="submit" class="w-full py-2.5 bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-600 font-bold rounded-xl text-xs transition">
                                    Rút lui khỏi phòng
                                </button>
                            </form>
                        @elseif($match->isOpen())
                            <form action="{{ route('customer.matches.join', $match->id) }}" method="POST" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Lời nhắn gửi chủ phòng</label>
                                    <input type="text" name="message" placeholder="Ví dụ: Mình đá vị trí tiền vệ, cho mình 1 slot nha..." class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                </div>
                                <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black rounded-xl text-xs shadow-md transition">
                                    Xin Tham Gia Phòng
                                </button>
                            </form>
                        @else
                            <div class="p-3 bg-slate-100 rounded-xl text-center text-xs text-slate-500">
                                Phòng đấu đã đủ người hoặc đã đóng.
                            </div>
                        @endif
                    @endif
                @else
                    <div class="text-center py-4">
                        <p class="text-xs text-slate-500 mb-3">Đăng nhập tài khoản để xin tham gia phòng và xem thông tin liên hệ.</p>
                        <a href="{{ route('login') }}" class="block w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition">
                            Đăng Nhập Ngay
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
