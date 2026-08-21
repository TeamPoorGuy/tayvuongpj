@extends('layouts.app')

@section('title', 'Lịch Sử Đặt Sân - SportHub')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Lịch Sử Đặt Sân Của Tôi</h1>
        <p class="text-sm text-slate-500 mt-1">Theo dõi danh sách và trạng thái các lượt đặt sân thể thao</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        @if($bookings->isEmpty())
            <div class="p-12 text-center">
                <div class="text-5xl mb-3">📅</div>
                <h3 class="font-bold text-slate-700 text-lg">Bạn chưa có lịch đặt sân nào</h3>
                <p class="text-xs text-slate-400 mt-1">Hãy khám phá các sân bóng đá, cầu lông, tennis và đặt ngay hôm nay!</p>
                <a href="{{ route('customer.fields.index') }}" class="inline-block mt-4 px-6 py-2.5 bg-emerald-500 text-slate-950 font-bold rounded-xl text-xs">Đặt sân ngay</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-700 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4">Sân thể thao</th>
                            <th class="px-6 py-4">Ngày đá</th>
                            <th class="px-6 py-4">Khung giờ</th>
                            <th class="px-6 py-4">Tổng tiền</th>
                            <th class="px-6 py-4">Trạng thái</th>
                            <th class="px-6 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($bookings as $b)
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900">{{ $b->sportsField->name }}</div>
                                    <span class="text-xs text-slate-400 block">{{ $b->sportsField->address }}</span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-800">
                                    {{ $b->booking_date->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 bg-slate-100 font-mono text-xs rounded-md text-slate-700">
                                        {{ substr($b->timeSlot->start_time, 0, 5) }} - {{ substr($b->timeSlot->end_time, 0, 5) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-emerald-600">
                                    {{ number_format($b->total_price) }}đ
                                </td>
                                <td class="px-6 py-4">
                                    <x-booking-status-badge :status="$b->status" />
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @if($b->canBeCancelled())
                                        <form action="{{ route('customer.bookings.cancel', $b->id) }}" method="POST" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn đặt sân này?')">
                                            @csrf
                                            <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 px-3 py-1.5 rounded-lg border border-rose-200 hover:bg-rose-50 transition">
                                                Hủy Đơn
                                            </button>
                                        </form>
                                    @endif

                                    @if($b->status === 'completed')
                                        @if($b->review)
                                            <span class="text-xs text-emerald-600 font-bold">✓ Đã đánh giá</span>
                                        @else
                                            <button onclick="openReviewModal('{{ $b->id }}', '{{ $b->sportsField->name }}')" class="text-xs font-bold bg-amber-400 hover:bg-amber-500 text-slate-950 px-3 py-1.5 rounded-lg transition shadow-sm">
                                                ⭐ Đánh giá
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Đánh Giá -->
<div id="review-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <h3 class="text-lg font-bold text-slate-900 mb-1">Viết Đánh Giá Sân</h3>
        <p class="text-xs text-slate-500 mb-4" id="modal-field-name"></p>

        <form action="{{ route('customer.reviews.store') }}" method="POST">
            @csrf
            <input type="hidden" name="booking_id" id="modal-booking-id">

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 mb-2">Số sao đánh giá *</label>
                <select name="rating" class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold">
                    <option value="5">⭐⭐⭐⭐⭐ (5/5 - Rất tốt)</option>
                    <option value="4">⭐⭐⭐⭐ (4/5 - Tốt)</option>
                    <option value="3">⭐⭐⭐ (3/3 - Bình thường)</option>
                    <option value="2">⭐⭐ (2/5 - Kém)</option>
                    <option value="1">⭐ (1/5 - Rất kém)</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">Bình luận & Gợi ý *</label>
                <textarea name="comment" rows="3" required placeholder="Chia sẻ trải nghiệm mặt sân, hệ thống đèn, thái độ phục vụ..." class="w-full px-4 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="closeReviewModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">Hủy</button>
                <button type="submit" class="flex-1 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs shadow-md">Gửi Đánh Giá</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReviewModal(bookingId, fieldName) {
        document.getElementById('modal-booking-id').value = bookingId;
        document.getElementById('modal-field-name').innerText = fieldName;
        document.getElementById('review-modal').classList.remove('hidden');
    }
    function closeReviewModal() {
        document.getElementById('review-modal').classList.add('hidden');
    }
</script>
@endsection
