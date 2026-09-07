@extends('layouts.app')

@section('title', 'SportHub - Nền Tảng Đặt Sân Thể Thao Trực Tuyến Hàng Đầu')

@section('content')
<!-- Hero Section -->
<div class="relative bg-slate-900 text-white py-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-emerald-950/60 to-slate-900/90 z-10"></div>
    <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1600&q=80')] bg-cover bg-center opacity-30"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-20 text-center md:text-left grid md:grid-cols-2 items-center gap-12">
        <div>
            <span class="inline-block px-3 py-1 bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 rounded-full text-xs font-bold uppercase tracking-wider mb-4">
                🔥 Đặt sân nhanh chỉ trong 30 giây
            </span>
            <h1 class="text-4xl md:text-5xl font-black leading-tight tracking-tight">
                Tìm & Đặt Sân Thể Thao <span class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">Dễ Dàng Hơn Bao Giờ Hết</span>
            </h1>
            <p class="text-slate-300 text-base mt-4 leading-relaxed">
                Kết nối hàng ngàn chủ sân bóng đá, cầu lông, tennis, bóng rổ trên toàn quốc. Kiểm tra lịch trống realtime và giữ chỗ ngay lập tức.
            </p>
            <div class="mt-8 flex flex-wrap gap-4 justify-center md:justify-start">
                <a href="{{ route('customer.fields.index') }}" class="px-8 py-3.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-2xl shadow-lg shadow-emerald-500/25 transition-all hover:scale-105">
                    Đặt sân ngay bây giờ →
                </a>
            </div>
        </div>

        <!-- Quick Search Box -->
        <div class="bg-white/10 backdrop-blur-xl p-6 rounded-3xl border border-white/20 shadow-2xl">
            <h3 class="text-lg font-bold text-white mb-4">🔍 Tìm kiếm sân nhanh</h3>
            <form action="{{ route('customer.fields.index') }}" method="GET" class="space-y-4">
                <div>
                    <label class="block text-xs text-slate-300 mb-1">Tên sân hoặc khu vực</label>
                    <input type="text" name="keyword" placeholder="Nhập tên sân, địa chỉ, quận huyện..." class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white placeholder-slate-400 text-sm focus:outline-none focus:border-emerald-400">
                </div>
                <div>
                    <label class="block text-xs text-slate-300 mb-1">Bộ môn thể thao</label>
                    <select name="category" class="w-full px-4 py-3 rounded-xl bg-slate-800/80 border border-slate-700 text-white text-sm focus:outline-none focus:border-emerald-400">
                        <option value="">-- Tất cả bộ môn --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}">{{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-teal-400 text-slate-950 font-bold rounded-xl shadow-lg hover:brightness-110 transition">
                    Tìm kiếm sân trống
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Categories Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <h2 class="text-3xl font-black text-slate-900 dark:text-white">Danh Mục Bộ Môn Thể Thao</h2>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-2">Đa dạng sân chơi đáp ứng mọi nhu cầu rèn luyện của bạn</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
        @foreach($categories as $cat)
            <a href="{{ route('customer.fields.index', ['category' => $cat->slug]) }}" class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300 text-center group">
                <div class="text-4xl mb-3 group-hover:scale-110 transition-transform">{{ $cat->icon }}</div>
                <h3 class="font-bold text-slate-900 dark:text-white text-base group-hover:text-emerald-500">{{ $cat->name }}</h3>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ $cat->field_types_count }} loại sân</p>
            </a>
        @endforeach
    </div>
</div>

<!-- Featured Fields Section -->
<div class="bg-slate-100 dark:bg-slate-900/50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-end mb-10">
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white">Sân Thể Thao Nổi Bật</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Các cơ sở được yêu thích và đánh giá cao nhất</p>
            </div>
            <a href="{{ route('customer.fields.index') }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700">Xem tất cả →</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($featuredFields as $field)
                <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-md border border-slate-200/60 dark:border-slate-800 hover:shadow-xl transition group flex flex-col">
                    <div class="relative h-48 bg-slate-200 dark:bg-slate-800 overflow-hidden">
                        @if($field->primaryImage)
                            <img src="{{ $field->primaryImage->image_path }}" alt="{{ $field->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl bg-slate-800 text-white">🏟️</div>
                        @endif
                        <span class="absolute top-3 left-3 bg-slate-900/80 backdrop-blur text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                            {{ $field->fieldType->sportCategory->name }}
                        </span>
                    </div>
                    <div class="p-6 flex-grow flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold px-2 py-0.5 bg-emerald-50 dark:bg-emerald-950/60 rounded-md">{{ $field->fieldType->name }}</span>
                                <x-star-rating :rating="$field->averageRating()" />
                            </div>
                            <h3 class="font-bold text-lg text-slate-900 dark:text-white group-hover:text-emerald-500 transition line-clamp-1">
                                <a href="{{ route('customer.fields.show', $field->slug) }}">{{ $field->name }}</a>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2">📍 {{ $field->address }}</p>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <div>
                                <span class="text-xs text-slate-400 block">Giá thuê chỉ từ</span>
                                <span class="text-lg font-black text-slate-900 dark:text-white">{{ number_format($field->price_per_hour) }} <span class="text-xs font-normal text-slate-500">đ/giờ</span></span>
                            </div>
                            <a href="{{ route('customer.fields.show', $field->slug) }}" class="px-4 py-2 bg-slate-900 dark:bg-emerald-600 hover:bg-emerald-600 dark:hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">
                                Đặt Sân
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
