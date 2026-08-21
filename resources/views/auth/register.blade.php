@extends('layouts.app')

@section('title', 'Đăng Ký Tài Khoản - SportHub')

@section('content')
<div class="min-h-[85vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-xl w-full bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-black text-slate-900">Tạo Tài Khoản Mới</h2>
            <p class="text-xs text-slate-500 mt-1">Đăng ký tham gia hệ thống đặt sân thể thao</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <!-- Role Picker -->
            <div class="grid grid-cols-2 gap-4 p-1.5 bg-slate-100 rounded-xl mb-6">
                <label class="flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold" id="label-customer">
                    <input type="radio" name="role" value="customer" checked onclick="toggleRoleFields('customer')" class="sr-only">
                    ⚽ Khách Hàng Tìm Sân
                </label>
                <label class="flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold" id="label-owner">
                    <input type="radio" name="role" value="field_owner" onclick="toggleRoleFields('field_owner')" class="sr-only">
                    🏢 Chủ Sân Cho Thuê
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và Tên *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Số Điện Thoại *</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Địa chỉ Email *</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Field Owner specific inputs -->
            <div id="owner-fields" class="hidden space-y-4 pt-2 border-t border-slate-200">
                <div>
                    <label class="block text-xs font-semibold text-emerald-700 mb-1">Tên Cơ Sở Kinh Doanh *</label>
                    <input type="text" name="business_name" value="{{ old('business_name') }}" class="w-full px-4 py-2.5 rounded-xl border border-emerald-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('business_name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-emerald-700 mb-1">Địa Chỉ Cơ Sở *</label>
                    <input type="text" name="business_address" value="{{ old('business_address') }}" class="w-full px-4 py-2.5 rounded-xl border border-emerald-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mật Khẩu *</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    @error('password') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Xác Nhận Mật Khẩu *</label>
                    <input type="password" name="password_confirmation" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl shadow-lg shadow-emerald-500/20 transition duration-200 mt-4">
                Đăng Ký Tài Khoản
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
            Đã có tài khoản? <a href="{{ route('login') }}" class="text-emerald-600 font-bold hover:underline">Đăng nhập</a>
        </div>
    </div>
</div>

<script>
    function toggleRoleFields(role) {
        const ownerFields = document.getElementById('owner-fields');
        const labelCustomer = document.getElementById('label-customer');
        const labelOwner = document.getElementById('label-owner');

        if (role === 'field_owner') {
            ownerFields.classList.remove('hidden');
            labelOwner.className = 'flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold bg-white text-emerald-700 shadow';
            labelCustomer.className = 'flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold text-slate-500';
        } else {
            ownerFields.classList.add('hidden');
            labelCustomer.className = 'flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold bg-white text-emerald-700 shadow';
            labelOwner.className = 'flex items-center justify-center p-3 rounded-lg cursor-pointer transition text-xs font-bold text-slate-500';
        }
    }
    toggleRoleFields('customer');
</script>
@endsection
