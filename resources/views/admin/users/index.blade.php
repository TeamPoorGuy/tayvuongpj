@extends('layouts.admin')

@section('title', 'Quản Lý Người Dùng')
@section('page_title', 'Danh Sách Khách Hàng & Chủ Sân')

@section('content')
<div class="bg-white dark:bg-slate-800 p-4 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm mb-6 transition-colors">
    <form action="{{ route('admin.users.index') }}" method="GET" class="flex flex-wrap gap-4">
        <input type="text" name="keyword" value="{{ request('keyword') }}" placeholder="Tên, email, số điện thoại..." class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl text-xs focus:outline-none focus:border-indigo-500">
        <select name="role" class="px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl text-xs focus:outline-none focus:border-indigo-500">
            <option value="">Tất cả vai trò</option>
            <option value="customer" {{ request('role') == 'customer' ? 'selected' : '' }}>Khách hàng</option>
            <option value="field_owner" {{ request('role') == 'field_owner' ? 'selected' : '' }}>Chủ sân</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition">Lọc người dùng</button>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden transition-colors">
    <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
        <thead class="bg-slate-100 dark:bg-slate-950 text-xs font-bold text-slate-600 dark:text-slate-400 uppercase border-b border-slate-200 dark:border-slate-700">
            <tr>
                <th class="px-6 py-4">Họ & Tên</th>
                <th class="px-6 py-4">Email</th>
                <th class="px-6 py-4">Số điện thoại</th>
                <th class="px-6 py-4">Vai trò</th>
                <th class="px-6 py-4">Trạng thái</th>
                <th class="px-6 py-4 text-right">Khóa / Mở khóa</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($users as $u)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">{{ $u->name }}</td>
                    <td class="px-6 py-4">{{ $u->email }}</td>
                    <td class="px-6 py-4">{{ $u->phone ?? 'N/A' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold capitalize {{ $u->isFieldOwner() ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300' : 'bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' }}">
                            {{ $u->role }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $u->is_active ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : 'bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-400' }}">
                            {{ $u->is_active ? 'Hoạt động' : 'Đã khóa' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <form action="{{ route('admin.users.toggle', $u->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs font-bold px-3 py-1 rounded-lg border {{ $u->is_active ? 'border-rose-500 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10' : 'border-emerald-500 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10' }}">
                                {{ $u->is_active ? 'Khóa TK' : 'Mở Khóa' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">
        {{ $users->links() }}
    </div>
</div>
@endsection
