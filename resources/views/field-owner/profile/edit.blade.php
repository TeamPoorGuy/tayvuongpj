@extends('layouts.dashboard')

@section('title', 'Hồ Sơ Cơ Sở Kinh Doanh')
@section('page_title', 'Thông Tin Cơ Sở Kinh Doanh Chủ Sân')

@section('content')
<div class="max-w-2xl bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
    <div class="mb-6 flex items-center justify-between p-4 rounded-xl {{ $profile->verification_status === 'approved' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
        <div>
            <span class="text-xs font-bold block">Trạng thái xác minh tài khoản chủ sân</span>
            <span class="text-sm font-black capitalize">{{ $profile->verification_status }}</span>
        </div>
    </div>

    <form action="{{ route('field-owner.profile.update') }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Tên Cơ Sở Kinh Doanh *</label>
            <input type="text" name="business_name" value="{{ old('business_name', $profile->business_name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Số Điện Thoại Kinh Doanh *</label>
                <input type="text" name="business_phone" value="{{ old('business_phone', $profile->business_phone) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mã GPKD (Nếu có)</label>
                <input type="text" name="business_license" value="{{ old('business_license', $profile->business_license) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Địa Chỉ Cơ Sở *</label>
            <input type="text" name="business_address" value="{{ old('business_address', $profile->business_address) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Giới Thiệu Cơ Sở</label>
            <textarea name="description" rows="3" class="w-full px-4 py-2 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('description', $profile->description) }}</textarea>
        </div>

        <button type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs shadow-md">Lưu Thay Đổi</button>
    </form>
</div>
@endsection
