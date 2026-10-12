@extends('layouts.app')

@section('title', 'Hàng đợi thông minh (Waitlist) - SportHub')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                ⏳ Hàng Đợi Thông Minh (Waitlist)
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Theo dõi các khung giờ kín bạn đã đăng ký chờ. Khi có khách hủy, hệ thống sẽ ưu tiên giữ chỗ cho bạn theo điểm uy tín!
            </p>
        </div>
        <div class="px-4 py-2 bg-emerald-50 rounded-xl border border-emerald-200 text-xs font-semibold text-emerald-800">
            Điểm uy tín của bạn: <span class="font-black text-emerald-700 text-sm">{{ Auth::user()->trust_score ?? 100 }}</span> ({{ Auth::user()->trustScoreLabel() }})
        </div>
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

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        @if($waitlists->isEmpty())
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                    ⏳
                </div>
                <h3 class="text-base font-bold text-slate-800">Bạn chưa đăng ký hàng đợi nào</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Khi một khung giờ tại sân thể thao yêu thích đã kín chỗ, hãy bấm "Đăng ký hàng đợi" để có cơ hội nhận sân ngay khi có người hủy lịch!
                </p>
                <a href="{{ route('customer.fields.index') }}" class="mt-4 inline-block px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition">
                    Khám phá sân thể thao
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Sân thể thao</th>
                            <th class="px-6 py-4">Ngày thi đấu</th>
                            <th class="px-6 py-4">Khung giờ</th>
                            <th class="px-6 py-4">Trạng thái</th>
                            <th class="px-6 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($waitlists as $item)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($item->sportsField->primaryImage)
                                            <img src="{{ $item->sportsField->primaryImage->image_path }}" class="w-10 h-10 rounded-lg object-cover">
                                        @else
                                            <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center text-base">🏟️</div>
                                        @endif
                                        <div>
                                            <a href="{{ route('customer.fields.show', $item->sportsField->slug) }}" class="font-bold text-slate-900 hover:text-emerald-600 transition">
                                                {{ $item->sportsField->name }}
                                            </a>
                                            <p class="text-[11px] text-slate-400">Ưu tiên: {{ $item->priority_score }} điểm</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-700">
                                    {{ $item->booking_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg font-bold">
                                        {{ substr($item->timeSlot->start_time, 0, 5) }} - {{ substr($item->timeSlot->end_time, 0, 5) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($item->status === 'offered')
                                        <div class="inline-flex flex-col">
                                            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full font-black text-[10px] animate-pulse">
                                                ⚡ Đang có lời mời nhận sân!
                                            </span>
                                            @if($item->offer_expires_at)
                                                <span class="text-[10px] text-amber-600 mt-1">
                                                    Hết hạn lúc: {{ $item->offer_expires_at->format('H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                    @elseif($item->status === 'waiting')
                                        <span class="px-2.5 py-1 bg-sky-100 text-sky-800 rounded-full font-bold text-[10px]">
                                            Đang chờ chỗ trống
                                        </span>
                                    @elseif($item->status === 'accepted')
                                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-full font-bold text-[10px]">
                                            Đã nhận sân
                                        </span>
                                    @elseif($item->status === 'expired')
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-500 rounded-full font-semibold text-[10px]">
                                            Đã hết hạn
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 bg-rose-100 text-rose-800 rounded-full font-semibold text-[10px]">
                                            Đã hủy
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($item->status === 'offered')
                                            <form action="{{ route('customer.waitlists.accept', $item->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-lg shadow-sm transition">
                                                    Nhận Sân Ngay
                                                </button>
                                            </form>
                                        @endif

                                        @if(in_array($item->status, ['waiting', 'offered']))
                                            <form action="{{ route('customer.waitlists.cancel', $item->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn hủy đăng ký chờ khung giờ này?')">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg border border-rose-200 transition">
                                                    Hủy Chờ
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-100">
                {{ $waitlists->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
