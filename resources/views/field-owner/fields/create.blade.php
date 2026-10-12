@extends('layouts.dashboard')

@section('title', 'Thêm Sân Mới')
@section('page_title', 'Tạo Sân Thể Thao Mới')

@section('content')
<div class="max-w-3xl bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
    <form action="{{ route('field-owner.fields.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tên Sân Thể Thao *</label>
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="VD: Sân Bóng Đá Tây Vương 3" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            @error('name') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Loại Sân Thể Thao *</label>
                <select name="field_type_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    <option value="">-- Chọn loại sân --</option>
                    @foreach($fieldTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->sportCategory->name }} - {{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Giá Thuê Theo Giờ (VNĐ) *</label>
                <input type="number" name="price_per_hour" value="{{ old('price_per_hour') }}" required placeholder="VD: 300000" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                @error('price_per_hour') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Địa Chỉ Chi Tiết *</label>
            <input type="text" name="address" value="{{ old('address') }}" required placeholder="Số nhà, đường, quận/huyện, tỉnh/thành..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Mô Tả Chi Tiết Về Sân</label>
            <textarea name="description" rows="4" placeholder="Mặt cỏ nhân tạo, hệ thống đèn, cơ sở vật chất phụ trợ..." class="w-full px-4 py-2 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tải Ảnh Sân (Một hoặc nhiều ảnh)</label>
            <input type="file" name="images[]" multiple accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
        </div>

        <div class="flex gap-4 pt-4 border-t border-slate-100">
            <a href="{{ route('field-owner.fields.index') }}" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">Hủy</a>
            <button type="submit" class="px-8 py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs shadow-md">Đăng Sân Mới</button>
        </div>
    </form>
</div>
@endsection
