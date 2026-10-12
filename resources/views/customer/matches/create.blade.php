@extends('layouts.app')

@section('title', 'Đăng Tin Tìm Đối Thủ & Ghép Đội - SportHub')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="{{ route('customer.matches.index') }}" class="text-xs font-bold text-slate-500 hover:text-emerald-600 transition flex items-center gap-1 mb-2">
            ← Quay lại danh sách phòng
        </a>
        <h1 class="text-2xl font-black text-slate-900">Đăng Tin Tìm Đối Thủ / Ghép Đội</h1>
        <p class="text-xs text-slate-500 mt-1">
            Tạo phòng để tìm đội giao hữu hoặc kêu gọi thêm thành viên cho trận đấu của bạn.
        </p>
    </div>

    @if(session('error'))
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold rounded-xl">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-sm">
        <form action="{{ route('customer.matches.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tiêu đề phòng đấu *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="Ví dụ: Tìm kèo giao lưu sân 7 tối thứ 5, trình độ trung bình..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                @error('title') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Môn thể thao *</label>
                    <select name="sport_category_id" id="category_select" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">-- Chọn bộ môn --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('sport_category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('sport_category_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Sân thể thao (Tùy chọn)</label>
                    <select name="sports_field_id" id="field_select" onchange="onFieldSelected(this)" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="">-- Chọn sân nếu đã có địa điểm --</option>
                        @foreach($fields as $field)
                            <option value="{{ $field->id }}" data-category="{{ $field->fieldType->sport_category_id }}" {{ old('sports_field_id') == $field->id ? 'selected' : '' }}>
                                {{ $field->name }} ({{ $field->fieldType->sportCategory->name }})
                            </option>
                        @endforeach
                    </select>
                    @error('sports_field_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Ngày thi đấu *</label>
                    <input type="date" name="play_date" min="{{ date('Y-m-d') }}" value="{{ old('play_date', date('Y-m-d')) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('play_date') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Giờ bắt đầu *</label>
                    <input type="text" name="start_time" placeholder="18:30" value="{{ old('start_time', '18:30') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('start_time') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Giờ kết thúc *</label>
                    <input type="text" name="end_time" placeholder="20:00" value="{{ old('end_time', '20:00') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('end_time') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Trình độ đối thủ *</label>
                    <select name="skill_level" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        <option value="any" {{ old('skill_level') == 'any' ? 'selected' : '' }}>Giao lưu vui vẻ (Bất kỳ)</option>
                        <option value="beginner" {{ old('skill_level') == 'beginner' ? 'selected' : '' }}>Mới chơi (Beginner)</option>
                        <option value="intermediate" {{ old('skill_level', 'intermediate') == 'intermediate' ? 'selected' : '' }}>Trung bình (Intermediate)</option>
                        <option value="advanced" {{ old('skill_level') == 'advanced' ? 'selected' : '' }}>Bán chuyên / Chuyên nghiệp</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Số người cần tìm thêm *</label>
                    <input type="number" name="players_needed" min="1" max="50" value="{{ old('players_needed', 1) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Số điện thoại liên hệ *</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', Auth::user()->phone) }}" required placeholder="0901234567" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mô tả / Yêu cầu thêm</label>
                <textarea name="description" rows="3" placeholder="Chia sẻ thêm thông tin (màu áo, chia tiền sân nước, quy định thi đấu...)" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description') }}</textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('customer.matches.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition">
                    Hủy bỏ
                </a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-xl shadow-md transition text-xs">
                    Tạo Phòng Đấu Ngay
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function onFieldSelected(select) {
        const option = select.options[select.selectedIndex];
        const categoryId = option.getAttribute('data-category');
        if (categoryId) {
            document.getElementById('category_select').value = categoryId;
        }
    }
</script>
@endsection
