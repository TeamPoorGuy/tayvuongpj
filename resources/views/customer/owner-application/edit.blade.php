@extends('layouts.app')

@section('title', 'Đăng Ký Làm Chủ Sân - SportHub')

@section('content')
@php($status = $profile?->verification_status)

<div class="max-w-3xl mx-auto px-4 py-10">
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Hồ sơ chủ sân</p>
        <h1 class="text-3xl font-black text-slate-900 mt-2">Đăng ký làm chủ sân</h1>
        <p class="text-sm text-slate-500 mt-2">Tài khoản mới luôn là khách hàng. Sau khi admin duyệt hồ sơ này, bạn sẽ có thêm quyền quản lý sân.</p>
    </div>

    @if($status === 'approved')
        <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800">
            Hồ sơ đã được duyệt. <a class="font-bold underline" href="{{ route('field-owner.dashboard') }}">Vào trang quản lý chủ sân</a>.
        </div>
    @elseif($status === 'pending')
        <div class="p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800">
            Hồ sơ đang chờ admin xét duyệt. Bạn chưa thể vào khu vực quản lý sân.
        </div>
    @else
        @if($status === 'rejected')
            <div class="mb-6 p-5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800">
                Hồ sơ bị từ chối: {{ $profile->rejection_reason ?: 'Chưa có lý do cụ thể.' }} Bạn có thể sửa và gửi lại.
            </div>
        @endif

        <form action="{{ route('customer.owner-application.store') }}" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm space-y-5">
            @csrf

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Họ tên chủ sân *</label>
                    <input name="owner_name" value="{{ old('owner_name', $profile?->owner_name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                    @error('owner_name') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Số CCCD/CMND *</label>
                    <input name="owner_id_number" value="{{ old('owner_id_number', $profile?->owner_id_number) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                    @error('owner_id_number') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tên cơ sở kinh doanh *</label>
                <input name="business_name" value="{{ old('business_name', $profile?->business_name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Địa chỉ kinh doanh *</label>
                    <input name="business_address" value="{{ old('business_address', $profile?->business_address) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">SĐT kinh doanh *</label>
                    <input name="business_phone" value="{{ old('business_phone', $profile?->business_phone) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Số giấy phép kinh doanh *</label>
                <input name="business_license" value="{{ old('business_license', $profile?->business_license) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Ảnh/PDF giấy phép</label>
                <input type="file" name="license_file" accept="image/*,.pdf" class="w-full text-sm">
                @error('license_file') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Mô tả cơ sở</label>
                <textarea name="description" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-300">{{ old('description', $profile?->description) }}</textarea>
            </div>

            <button class="px-6 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold">
                {{ $status === 'rejected' ? 'Gửi lại hồ sơ' : 'Gửi hồ sơ đăng ký' }}
            </button>
        </form>
    @endif
</div>
@endsection
