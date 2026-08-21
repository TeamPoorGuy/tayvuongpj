@extends('layouts.dashboard')

@section('title', 'Đánh Giá Khách Hàng')
@section('page_title', 'Danh Sách Đánh Giá & Bình Luận')

@section('content')
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <div class="space-y-4">
        @forelse($reviews as $rev)
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="font-bold text-slate-900 text-sm">{{ $rev->user->name }}</span>
                        <x-star-rating :rating="$rev->rating" />
                    </div>
                    <p class="text-xs text-slate-500 mb-2">Sân: <span class="font-semibold text-slate-700">{{ $rev->sportsField->name }}</span></p>
                    <p class="text-xs text-slate-700 leading-relaxed font-medium bg-white p-3 rounded-lg border border-slate-200">{{ $rev->comment }}</p>
                </div>
                <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ $rev->created_at->format('d/m/Y H:i') }}</span>
            </div>
        @empty
            <p class="text-xs text-slate-400 text-center py-6">Chưa có đánh giá nào từ phía khách hàng.</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $reviews->links() }}
    </div>
</div>
@endsection
