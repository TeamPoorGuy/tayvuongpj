@extends('layouts.admin')

@section('title', 'Quản Lý Đánh Giá')
@section('page_title', 'Giám Sát & Xử Lý Đánh Giá / Bình Luận Vi Phạm')

@section('content')
<div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 space-y-4">
    @foreach($reviews as $r)
        <div class="p-4 bg-slate-900/80 rounded-xl border border-slate-700 flex justify-between items-start">
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="font-bold text-white text-sm">{{ $r->user->name }}</span>
                    <x-star-rating :rating="$r->rating" />
                </div>
                <span class="text-xs text-emerald-400 block mb-2">Sân: {{ $r->sportsField->name }}</span>
                <p class="text-xs text-slate-300 bg-slate-800 p-3 rounded-lg border border-slate-700">{{ $r->comment }}</p>
            </div>
            <div class="text-right flex flex-col items-end gap-2">
                <span class="text-[10px] text-slate-400">{{ $r->created_at->format('d/m/Y H:i') }}</span>
                <form action="{{ route('admin.reviews.toggle', $r->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3 py-1 text-xs font-bold rounded-lg border {{ $r->is_visible ? 'border-amber-500 text-amber-400' : 'border-emerald-500 text-emerald-400' }}">
                        {{ $r->is_visible ? 'Ẩn bình luận' : 'Hiển thị lại' }}
                    </button>
                </form>
            </div>
        </div>
    @endforeach

    <div class="pt-4 border-t border-slate-700">
        {{ $reviews->links() }}
    </div>
</div>
@endsection
