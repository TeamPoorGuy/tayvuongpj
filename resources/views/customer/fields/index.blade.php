@extends('layouts.app')

@section('title', 'Danh Sách Sân Thể Thao - SportHub')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Tìm Kiếm & Thuê Sân Thể Thao</h1>
        <p class="text-sm text-slate-500 mt-1">Khám phá các sân tập chất lượng cao xung quanh bạn</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Lọc -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm h-fit space-y-6">
            <h3 class="font-bold text-slate-900 text-lg border-b pb-3">Bộ Lọc Tìm Kiếm</h3>

            <form action="{{ route('customer.fields.index') }}" method="GET" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Từ khóa</label>
                    <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Tên sân, địa chỉ..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Bộ môn thể thao</label>
                    <select name="category" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">Tất cả bộ môn</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Loại sân cụ thể</label>
                    <select name="type_id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">Tất cả loại sân</option>
                        @foreach($fieldTypes as $type)
                            <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mức giá tối đa (đ/giờ)</label>
                    <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="VD: 500000" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>

                <div class="pt-2 flex gap-2">
                    <button type="submit" class="flex-1 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition">Áp dụng lọc</button>
                    <a href="{{ route('customer.fields.index') }}" class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold rounded-xl text-xs transition">Xóa</a>
                </div>
            </form>
        </div>

        <!-- Main Field List -->
        <div class="lg:col-span-3">
            @if($fields->isEmpty())
                <div class="bg-white p-12 rounded-2xl text-center border border-slate-200">
                    <div class="text-5xl mb-4">🔍</div>
                    <h3 class="text-lg font-bold text-slate-800">Không tìm thấy sân thể thao phù hợp</h3>
                    <p class="text-xs text-slate-500 mt-1">Vui lòng thử lại với các tiêu chí tìm kiếm hoặc bộ lọc khác.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($fields as $field)
                        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-200 hover:shadow-lg transition flex flex-col">
                            <div class="relative h-44 bg-slate-200">
                                @if($field->primaryImage)
                                    <img src="{{ $field->primaryImage->image_path }}" alt="{{ $field->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-3xl bg-slate-800 text-white">🏟️</div>
                                @endif
                                <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-md">
                                    {{ $field->fieldType->name }}
                                </span>
                            </div>
                            <div class="p-5 flex-grow flex flex-col justify-between">
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <x-star-rating :rating="$field->averageRating()" />
                                    </div>
                                    <h3 class="font-bold text-base text-slate-900 line-clamp-1">
                                        <a href="{{ route('customer.fields.show', $field->slug) }}" class="hover:text-emerald-600">{{ $field->name }}</a>
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2">📍 {{ $field->address }}</p>
                                </div>

                                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-base font-black text-emerald-600">{{ number_format($field->price_per_hour) }}đ<span class="text-[10px] text-slate-400 font-normal">/h</span></span>
                                    <a href="{{ route('customer.fields.show', $field->slug) }}" class="px-3.5 py-1.5 bg-slate-900 hover:bg-emerald-500 hover:text-slate-950 text-white font-bold text-xs rounded-xl transition">
                                        Chi tiết
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $fields->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
