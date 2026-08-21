@extends('layouts.dashboard')

@section('title', 'Quản Lý Sân Thể Thao')
@section('page_title', 'Danh Sách Sân Thể Thao Thuộc Sở Hữu')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <p class="text-xs text-slate-500">Quản lý giá thuê, hình ảnh và trạng thái hoạt động của các sân bóng.</p>
    <a href="{{ route('field-owner.fields.create') }}" class="px-5 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
        ➕ Thêm Sân Mới
    </a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-xs font-bold text-slate-700 uppercase border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4">Hình ảnh</th>
                    <th class="px-6 py-4">Tên Sân</th>
                    <th class="px-6 py-4">Loại Sân</th>
                    <th class="px-6 py-4">Giá Thuê / Giờ</th>
                    <th class="px-6 py-4">Duyệt Admin</th>
                    <th class="px-6 py-4">Hoạt động</th>
                    <th class="px-6 py-4 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($fields as $f)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            @if($f->primaryImage)
                                <img src="{{ $f->primaryImage->image_path }}" class="w-16 h-12 object-cover rounded-lg">
                            @else
                                <div class="w-16 h-12 bg-slate-800 text-white flex items-center justify-center text-xs rounded-lg">🏟️</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-bold text-slate-900 block">{{ $f->name }}</span>
                            <span class="text-xs text-slate-400 block line-clamp-1">{{ $f->address }}</span>
                        </td>
                        <td class="px-6 py-4 font-semibold text-slate-700">{{ $f->fieldType->name }}</td>
                        <td class="px-6 py-4 font-bold text-emerald-600">{{ number_format($f->price_per_hour) }}đ</td>
                        <td class="px-6 py-4">
                            @if($f->status === 'approved')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Đã duyệt</span>
                            @elseif($f->status === 'pending')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Chờ duyệt</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Từ chối</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <form action="{{ route('field-owner.fields.toggle', $f->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3 py-1 text-xs font-bold rounded-lg {{ $f->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-300' : 'bg-slate-100 text-slate-500 border border-slate-300' }}">
                                    {{ $f->is_active ? 'Mở cửa' : 'Đóng cửa' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="{{ route('field-owner.fields.edit', $f->id) }}" class="text-xs font-bold text-blue-600 hover:underline">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-xs text-slate-400">Bạn chưa đăng bán sân thể thao nào.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t border-slate-100">
        {{ $fields->links() }}
    </div>
</div>
@endsection
