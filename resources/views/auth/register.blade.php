@extends('layouts.app')

@section('title', 'Đăng Ký Tài Khoản - SportHub')

@section('content')
<div class="min-h-[85vh] flex items-center justify-center py-12 px-4">
    <div class="max-w-xl w-full bg-white p-8 rounded-2xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-black text-slate-900">Tạo Tài Khoản Khách Hàng</h2>
            <p class="text-xs text-slate-500 mt-1">Sau khi đăng ký, bạn có thể nộp hồ sơ xin làm chủ sân.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và tên *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                    @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Số điện thoại *</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                    @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Email *</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Địa chỉ</label>
                <input type="text" name="address" value="{{ old('address') }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu *</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                    @error('password') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Xác nhận mật khẩu *</label>
                    <input type="password" name="password_confirmation" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl">
                Đăng ký tài khoản
            </button>
        </form>

        <div class="mt-6 text-center text-xs text-slate-500">
            Đã có tài khoản? <a href="{{ route('login') }}" class="text-emerald-600 font-bold">Đăng nhập</a>
        </div>
    </div>
</div>
@endsection
