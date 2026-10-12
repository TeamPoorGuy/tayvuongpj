@extends('layouts.dashboard')

@section('title', 'Sửa Sân Thể Thao')
@section('page_title', 'Chỉnh Sửa Sân: ' . $field->name)

@section('content')
<div class="max-w-3xl bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
    <form action="{{ route('field-owner.fields.update', $field->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tên Sân Thể Thao *</label>
            <input type="text" name="name" value="{{ old('name', $field->name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Loại Sân Thể Thao *</label>
                <select name="field_type_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @foreach($fieldTypes as $type)
                        <option value="{{ $type->id }}" {{ $field->field_type_id == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Giá Thuê Theo Giờ (VNĐ) *</label>
                <input type="number" name="price_per_hour" value="{{ old('price_per_hour', $field->price_per_hour) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Địa Chỉ *</label>
            <input type="text" name="address" value="{{ old('address', $field->address) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Mô Tả Chi Tiết</label>
            <textarea name="description" rows="4" class="w-full px-4 py-2 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $field->description) }}</textarea>
        </div>

        <div class="flex gap-4 pt-4 border-t border-slate-100">
            <a href="{{ route('field-owner.fields.index') }}" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">Hủy</a>
            <button type="submit" class="px-8 py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs shadow-md">Cập Nhập</button>
        </div>
    </form>
</div>
@endsection
