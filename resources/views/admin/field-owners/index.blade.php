@extends('layouts.admin')

@section('title', 'Kiểm Duyệt Chủ Sân')
@section('page_title', 'Danh Sách Hồ Sơ Đăng Ký Chủ Sân')

@section('content')
<div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
    <table class="w-full text-left text-sm text-slate-300">
        <thead class="bg-slate-950 text-xs font-bold text-slate-400 uppercase border-b border-slate-700">
            <tr>
                <th class="px-6 py-4">Tên Chủ Sân</th>
                <th class="px-6 py-4">Tên Cơ Sở KD</th>
                <th class="px-6 py-4">SĐT Kinh Doanh</th>
                <th class="px-6 py-4">Địa Chỉ</th>
                <th class="px-6 py-4">Trạng Thái</th>
                <th class="px-6 py-4 text-right">Duyệt Hồ Sơ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-700">
            @foreach($profiles as $p)
                <tr class="hover:bg-slate-750">
                    <td class="px-6 py-4 font-bold text-white">{{ $p->user->name }}</td>
                    <td class="px-6 py-4 font-semibold text-emerald-400">{{ $p->business_name }}</td>
                    <td class="px-6 py-4">{{ $p->business_phone }}</td>
                    <td class="px-6 py-4 text-xs">{{ $p->business_address }}</td>
                    <td class="px-6 py-4">
                        @if($p->verification_status === 'approved')
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-400">Đã duyệt</span>
                        @elseif($p->verification_status === 'pending')
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400">Chờ duyệt</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-500/20 text-rose-400">Từ chối</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        @if($p->verification_status === 'pending')
                            <form action="{{ route('admin.field-owners.verify', $p->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-bold text-xs rounded-lg">Duyệt</button>
                            </form>
                            <form action="{{ route('admin.field-owners.verify', $p->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-lg">Từ chối</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4 border-t border-slate-700">
        {{ $profiles->links() }}
    </div>
</div>
@endsection
