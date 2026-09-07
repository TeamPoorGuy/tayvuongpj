@extends('layouts.admin')

@section('title', 'Kiểm Duyệt Sân Thể Thao')
@section('page_title', 'Danh Sách Sân Thể Thao Toàn Hệ Thống')

@section('content')
<div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6 flex gap-4 transition-colors">
    <a href="{{ route('admin.fields.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400' }}">Tất cả sân</a>
    <a href="{{ route('admin.fields.index', ['status' => 'pending']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ request('status') == 'pending' ? 'bg-amber-500 text-slate-950' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400' }}">Chờ duyệt</a>
    <a href="{{ route('admin.fields.index', ['status' => 'approved']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold {{ request('status') == 'approved' ? 'bg-emerald-600 text-white' : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400' }}">Đã duyệt</a>
</div>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden transition-colors">
    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
        <thead class="bg-slate-100 dark:bg-slate-950 text-xs font-bold text-slate-600 dark:text-slate-400 uppercase border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="px-6 py-4">Tên Sân</th>
                <th class="px-6 py-4">Chủ Sân</th>
                <th class="px-6 py-4">Loại Sân</th>
                <th class="px-6 py-4">Giá / Giờ</th>
                <th class="px-6 py-4">Trạng Thái</th>
                <th class="px-6 py-4 text-right">Duyệt Đăng Sân</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($fields as $f)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                        {{ $f->name }}
                        <span class="block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $f->address }}</span>
                    </td>
                    <td class="px-6 py-4">{{ $f->owner->name }}</td>
                    <td class="px-6 py-4 text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ $f->fieldType->name }}</td>
                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">{{ number_format($f->price_per_hour) }}đ</td>
                    <td class="px-6 py-4">
                        @if($f->status === 'approved')
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400">Đã duyệt</span>
                        @elseif($f->status === 'pending')
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400">Chờ duyệt</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400">Từ chối</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        @if($f->status === 'pending')
                            <form action="{{ route('admin.fields.verify', $f->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-lg shadow-sm">Duyệt</button>
                            </form>
                            <form action="{{ route('admin.fields.verify', $f->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-lg shadow-sm">Từ chối</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $fields->links() }}
    </div>
</div>
@endsection
