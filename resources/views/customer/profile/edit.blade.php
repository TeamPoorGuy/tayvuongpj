@extends('layouts.app')

@section('title', 'Thông Tin Cá Nhân - SportHub')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Quản Lý Hồ Sơ Cá Nhân</h1>
        <p class="text-sm text-slate-500 mt-1">Cập nhật thông tin chi tiết và bảo mật tài khoản</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Avatar card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="w-24 h-24 rounded-full bg-emerald-500 text-white font-black text-3xl flex items-center justify-center mx-auto mb-4 border-4 border-emerald-100 shadow-lg">
                @if($user->avatar)
                    <img src="{{ $user->avatar }}" class="w-full h-full rounded-full object-cover">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </div>
            <h3 class="font-bold text-slate-900 text-lg">{{ $user->name }}</h3>
            <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full mt-1 capitalize">{{ $user->role }}</span>
            <p class="text-xs text-slate-400 mt-3">Tham gia ngày: {{ $user->created_at->format('d/m/Y') }}</p>

            <div class="mt-4 pt-4 border-t border-slate-100">
                <span class="text-xs text-slate-500 block mb-1">Điểm uy tín</span>
                <span class="text-2xl font-black text-emerald-600">{{ $user->trust_score ?? 100 }} ⭐</span>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold mt-1 bg-emerald-50 text-emerald-800 border border-emerald-200">
                    {{ $user->trustScoreLabel() }}
                </span>
            </div>
        </div>

        <!-- Form fields -->
        <div class="md:col-span-2 space-y-6">
            <!-- Profile Info Form -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-slate-900 text-base mb-4 border-b pb-2">Thông Tin Tài Khoản</h3>
                <form action="{{ route('customer.profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Họ và Tên</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Số điện thoại</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Địa chỉ</label>
                        <input type="text" name="address" value="{{ old('address', $user->address) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Ảnh đại diện mới</label>
                        <input type="file" name="avatar" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>

                    <button type="submit" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition shadow-md">Lưu Thay Đổi</button>
                </form>
            </div>

            <!-- Password Form -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-slate-900 text-base mb-4 border-b pb-2">Đổi Mật Khẩu</h3>
                <form action="{{ route('customer.profile.password') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu hiện tại</label>
                        <input type="password" name="current_password" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Mật khẩu mới</label>
                            <input type="password" name="password" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Xác nhận mật khẩu mới</label>
                            <input type="password" name="password_confirmation" required class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs transition shadow-md">Đổi Mật Khẩu</button>
                </form>
            </div>

            <!-- Trust Score Logs -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-slate-900 text-base mb-4 border-b pb-2 flex items-center justify-between">
                    <span>⭐ Lịch Sử Điểm Uy Tín</span>
                    <span class="text-xs font-normal text-slate-500">Thang điểm 0 - 150</span>
                </h3>

                @if($user->trustScoreLogs->isEmpty())
                    <p class="text-xs text-slate-400 italic">Chưa có lịch sử biến động điểm uy tín.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px]">
                                <tr>
                                    <th class="px-4 py-2.5">Thời gian</th>
                                    <th class="px-4 py-2.5">Biến động</th>
                                    <th class="px-4 py-2.5">Điểm sau thay đổi</th>
                                    <th class="px-4 py-2.5">Lý do</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($user->trustScoreLogs->take(10) as $log)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-400">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-3 font-extrabold {{ $log->change >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $log->change >= 0 ? '+'.$log->change : $log->change }}
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-800">{{ $log->score_after }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $log->reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
