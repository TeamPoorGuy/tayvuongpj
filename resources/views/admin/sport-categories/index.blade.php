@extends('layouts.admin')

@section('title', 'Quản Lý Bộ Môn & Loại Sân')
@section('page_title', 'Danh Mục Thể Thao & Phân Loại Sân')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Form thêm mới -->
    <div class="space-y-6">
        <!-- Thêm Bộ Môn -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700">
            <h3 class="font-bold text-white text-sm mb-4 border-b border-slate-700 pb-2">Thêm Danh Mục Thể Thao Mới</h3>
            <form action="{{ route('admin.sport-categories.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Tên bộ môn *</label>
                    <input type="text" name="name" required placeholder="VD: Bóng Rổ" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-white text-xs rounded-xl focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Biểu tượng Icon Emoji</label>
                    <input type="text" name="icon" placeholder="🏀" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-white text-xs rounded-xl focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow">Tạo Danh Mục</button>
            </form>
        </div>

        <!-- Thêm Loại Sân -->
        <div class="bg-slate-800 p-6 rounded-2xl border border-slate-700">
            <h3 class="font-bold text-white text-sm mb-4 border-b border-slate-700 pb-2">Thêm Loại Sân Thuộc Danh Mục</h3>
            <form action="{{ route('admin.field-types.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Chọn danh mục cha *</label>
                    <select name="sport_category_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-white text-xs rounded-xl focus:outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Tên loại sân *</label>
                    <input type="text" name="name" required placeholder="VD: Sân 7 người cỏ nhân tạo" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 text-white text-xs rounded-xl focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow">Tạo Loại Sân</button>
            </form>
        </div>
    </div>

    <!-- Tree View hiển thị -->
    <div class="lg:col-span-2 bg-slate-800 p-6 rounded-2xl border border-slate-700">
        <h3 class="font-bold text-white text-base mb-4 border-b border-slate-700 pb-2">Cấu Trúc Danh Mục & Loại Sân Hiện Tại</h3>
        <div class="space-y-4">
            @foreach($categories as $cat)
                <div class="bg-slate-900/80 p-4 rounded-xl border border-slate-700">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-emerald-400 text-base flex items-center gap-2">
                            <span>{{ $cat->icon }}</span> {{ $cat->name }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $cat->fieldTypes->count() }} loại sân</span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 pl-4 border-l-2 border-slate-700">
                        @forelse($cat->fieldTypes as $type)
                            <div class="p-2 bg-slate-800 rounded-lg text-xs font-medium text-slate-300 flex items-center justify-between">
                                <span>• {{ $type->name }}</span>
                            </div>
                        @empty
                            <span class="text-xs text-slate-500 italic">Chưa có loại sân nào.</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
