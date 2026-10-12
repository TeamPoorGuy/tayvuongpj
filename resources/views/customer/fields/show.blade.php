@extends('layouts.app')

@section('title', $field->name . ' - SportHub')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
    .leaflet-container { font-family: inherit; }
</style>
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Header info -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-bold">{{ $field->fieldType->sportCategory->name }}</span>
                    <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-semibold">{{ $field->fieldType->name }}</span>
                </div>
                <h1 class="text-3xl font-black text-slate-900">{{ $field->name }}</h1>
                <p class="text-sm text-slate-500 mt-1 flex items-center gap-1">📍 {{ $field->address }}</p>
                <p class="text-xs text-slate-400 mt-1">Chủ sân: <span class="font-bold text-slate-700">{{ $field->owner->fieldOwnerProfile->business_name ?? $field->owner->name }}</span></p>
            </div>

            <div class="text-right">
                <span class="text-xs text-slate-400 block">Giá thuê cố định</span>
                <span class="text-3xl font-black text-emerald-600">{{ number_format($field->price_per_hour) }} <span class="text-sm font-normal text-slate-500">VNĐ / Giờ</span></span>
                <div class="mt-2 flex justify-end">
                    <x-star-rating :rating="$field->averageRating()" />
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold rounded-xl">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold rounded-xl">
            ⚠️ {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Images & Description & Map -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Gallery -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                @if($field->images->isNotEmpty())
                    <img id="main-image" src="{{ $field->images->first()->image_path }}" class="w-full h-80 object-cover rounded-xl mb-4">
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach($field->images as $img)
                            <img src="{{ $img->image_path }}" onclick="document.getElementById('main-image').src = this.src" class="w-20 h-16 object-cover rounded-lg cursor-pointer border-2 border-transparent hover:border-emerald-500 transition">
                        @endforeach
                    </div>
                @else
                    <div class="w-full h-72 bg-slate-800 text-white flex items-center justify-center text-5xl rounded-xl">🏟️</div>
                @endif
            </div>

            <!-- Description -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-lg text-slate-900 mb-3">Mô tả sân thể thao</h3>
                <p class="text-sm text-slate-600 leading-relaxed">{{ $field->description ?? 'Chưa có thông tin mô tả chi tiết.' }}</p>
            </div>

            <!-- Location Map with Leaflet -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-lg text-slate-900 flex items-center gap-2">
                        📍 Vị trí & Bản đồ chỉ đường
                    </h3>
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($field->name . ' ' . $field->address) }}" target="_blank" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                        Mở Google Maps ↗
                    </a>
                </div>
                <div id="field-map" class="w-full h-64 rounded-xl border border-slate-200 z-0"></div>
                <p class="text-xs text-slate-500 mt-2">Địa chỉ: {{ $field->address }}</p>
            </div>

            <!-- Customer Reviews -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-lg text-slate-900 mb-4">Đánh giá từ khách hàng ({{ $field->reviews->count() }})</h3>
                <div class="space-y-4">
                    @forelse($field->reviews as $rev)
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-bold text-sm text-slate-800">{{ $rev->user->name }}</span>
                                <x-star-rating :rating="$rev->rating" />
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $rev->comment }}</p>
                            <span class="text-[10px] text-slate-400 block mt-2">{{ $rev->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 italic">Chưa có đánh giá nào cho sân này.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Booking Form Widget (AJAX Realtime Slot Checker + Waitlist + Smart Suggestions) -->
        <div>
            <div class="bg-white p-6 rounded-2xl border border-emerald-500/30 shadow-xl sticky top-24 space-y-4">
                <h3 class="font-black text-xl text-slate-900 flex items-center gap-2">
                    📅 Lịch Trống & Đặt Sân
                </h3>

                @auth
                    @if(Auth::user()->isCustomer())
                        <form action="{{ route('customer.bookings.store') }}" method="POST" id="booking-form" class="space-y-4">
                            @csrf
                            <input type="hidden" name="sports_field_id" value="{{ $field->id }}">
                            <input type="hidden" name="time_slot_id" id="selected_time_slot_id" required>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">1. Chọn ngày sử dụng sân *</label>
                                <input type="date" name="booking_date" id="booking_date" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" onchange="checkSlots()" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm font-semibold focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-2">2. Khung giờ khả dụng (AJAX Realtime) *</label>
                                <div id="slots-container" class="grid grid-cols-2 gap-2 min-h-[120px] flex items-center justify-center">
                                    <span class="text-xs text-slate-400">Đang tải lịch trống...</span>
                                </div>
                                <span id="slot-error" class="text-xs text-rose-500 mt-1 hidden block">Vui lòng chọn 1 khung giờ.</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">3. Ghi chú thêm</label>
                                <textarea name="notes" rows="2" placeholder="Ví dụ: Mượn thêm áo lưới, bóng..." class="w-full px-4 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                            </div>

                            <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                                <div class="flex justify-between items-center text-xs font-semibold text-slate-700">
                                    <span>Tạm tính (1 slot):</span>
                                    <span class="text-emerald-700 font-bold text-base" id="total_price">{{ number_format($field->price_per_hour * 1.5) }} đ</span>
                                </div>
                            </div>

                            <button type="submit" id="btn-submit-booking" disabled class="w-full py-3.5 bg-slate-300 text-slate-500 font-extrabold rounded-xl transition cursor-not-allowed">
                                Chọn Khung Giờ Để Tiếp Tục
                            </button>
                        </form>

                        <!-- Waitlist & Smart Suggestions Panel (appears when clicking a booked slot) -->
                        <div id="busy-slot-panel" class="hidden p-4 rounded-xl border border-amber-200 bg-amber-50/70 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-amber-900 flex items-center gap-1.5">
                                    <span>⚠️</span> Khung giờ này đã kín chỗ
                                </span>
                                <button type="button" onclick="document.getElementById('busy-slot-panel').classList.add('hidden')" class="text-amber-500 hover:text-amber-800 text-xs font-bold">✕ Đóng</button>
                            </div>

                            <!-- Waitlist registration form -->
                            <form action="{{ route('customer.waitlists.store') }}" method="POST" id="waitlist-form">
                                @csrf
                                <input type="hidden" name="sports_field_id" value="{{ $field->id }}">
                                <input type="hidden" name="time_slot_id" id="waitlist_slot_id">
                                <input type="hidden" name="booking_date" id="waitlist_booking_date">
                                <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl text-xs transition shadow-sm">
                                    ⏳ Đăng Ký Hàng Đợi (Nhận slot khi có người hủy)
                                </button>
                            </form>

                            <!-- Smart Suggestions Box -->
                            <div id="suggestions-box" class="pt-2 border-t border-amber-200 space-y-2">
                                <p class="text-[11px] font-bold text-slate-700">💡 Gợi ý khung giờ thay thế gần nhất:</p>
                                <div id="suggestions-list" class="space-y-1.5 text-xs">
                                    <span class="text-[11px] text-slate-400 italic">Đang tìm khung giờ phù hợp...</span>
                                </div>
                            </div>
                        </div>

                    @else
                        <div class="p-4 bg-amber-50 text-amber-800 rounded-xl text-xs font-semibold">
                            Tài khoản của bạn là {{ Auth::user()->role }}. Chỉ tài khoản Khách hàng mới có thể thực hiện đặt sân.
                        </div>
                    @endif
                @else
                    <div class="text-center py-6">
                        <p class="text-xs text-slate-500 mb-4">Vui lòng đăng nhập tài khoản Khách hàng để kiểm tra lịch trống và gửi yêu cầu đặt sân.</p>
                        <a href="{{ route('login') }}" class="block w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-xs transition shadow-md">Đăng Nhập Ngay</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    // 1. Initialize Leaflet Map
    document.addEventListener('DOMContentLoaded', function () {
        const defaultLat = {{ $field->latitude ?? 10.7769 }};
        const defaultLng = {{ $field->longitude ?? 106.7009 }};
        const mapContainer = document.getElementById('field-map');

        if (mapContainer && typeof L !== 'undefined') {
            const map = L.map('field-map').setView([defaultLat, defaultLng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            L.marker([defaultLat, defaultLng])
                .addTo(map)
                .bindPopup("<b>{{ $field->name }}</b><br>{{ $field->address }}")
                .openPopup();
        }
    });

    // 2. Realtime AJAX Slot Checker & Smart Suggestions
    function checkSlots() {
        const dateInput = document.getElementById('booking_date');
        const container = document.getElementById('slots-container');
        const hiddenSlotInput = document.getElementById('selected_time_slot_id');
        const btnSubmit = document.getElementById('btn-submit-booking');
        const busyPanel = document.getElementById('busy-slot-panel');

        if (!dateInput || !dateInput.value) return;

        if (busyPanel) busyPanel.classList.add('hidden');
        if (hiddenSlotInput) hiddenSlotInput.value = '';
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.className = "w-full py-3.5 bg-slate-300 text-slate-500 font-extrabold rounded-xl transition cursor-not-allowed";
            btnSubmit.innerText = "Chọn Khung Giờ Để Tiếp Tục";
        }

        container.innerHTML = '<span class="col-span-2 text-center text-xs text-slate-400 py-4">🔄 Đang kiểm tra lịch trống...</span>';

        fetch(`/ajax/fields/{{ $field->id }}/check-slots?date=${dateInput.value}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.slots.length === 0) {
                        container.innerHTML = '<span class="col-span-2 text-center text-xs text-rose-500 py-4">Chủ sân chưa cấu hình khung giờ cho sân này.</span>';
                        return;
                    }

                    container.innerHTML = '';
                    data.slots.forEach(slot => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        
                        if (slot.is_booked) {
                            button.className = 'py-2.5 px-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-xs font-semibold cursor-pointer hover:border-amber-400 hover:bg-amber-50 transition slot-booked-btn';
                            button.innerText = `${slot.start_time} - ${slot.end_time} (${slot.is_waitlisted ? 'Đang chờ' : 'Đã kín'})`;
                            button.title = "Khung giờ này đã kín. Bấm để xem gợi ý hoặc tham gia hàng đợi.";
                            button.onclick = function() {
                                showBusySlotOptions(slot.id, dateInput.value, `${slot.start_time} - ${slot.end_time}`);
                            };
                        } else {
                            button.className = 'py-2.5 px-3 rounded-xl border border-emerald-300 bg-emerald-50 hover:bg-emerald-500 hover:text-white text-emerald-800 text-xs font-bold transition slot-btn';
                            button.innerText = `${slot.start_time} - ${slot.end_time}`;
                            button.onclick = function() {
                                if (busyPanel) busyPanel.classList.add('hidden');
                                document.querySelectorAll('.slot-btn').forEach(b => b.className = 'py-2.5 px-3 rounded-xl border border-emerald-300 bg-emerald-50 hover:bg-emerald-500 hover:text-white text-emerald-800 text-xs font-bold transition slot-btn');
                                this.className = 'py-2.5 px-3 rounded-xl border-2 border-emerald-600 bg-emerald-600 text-white text-xs font-black shadow-md slot-btn';
                                hiddenSlotInput.value = slot.id;
                                btnSubmit.disabled = false;
                                btnSubmit.className = "w-full py-3.5 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-extrabold rounded-xl shadow-lg hover:scale-[1.02] transition cursor-pointer";
                                btnSubmit.innerText = "Xác Nhận Đặt Sân Ngay";
                            };
                        }
                        container.appendChild(button);
                    });
                }
            })
            .catch(err => {
                container.innerHTML = '<span class="col-span-2 text-center text-xs text-rose-500">Lỗi khi tải lịch trống.</span>';
            });
    }

    function showBusySlotOptions(slotId, date, slotLabel) {
        const busyPanel = document.getElementById('busy-slot-panel');
        const waitlistSlotInput = document.getElementById('waitlist_slot_id');
        const waitlistDateInput = document.getElementById('waitlist_booking_date');
        const suggestionsList = document.getElementById('suggestions-list');

        if (!busyPanel) return;

        busyPanel.classList.remove('hidden');
        waitlistSlotInput.value = slotId;
        waitlistDateInput.value = date;

        suggestionsList.innerHTML = '<span class="text-[11px] text-slate-400 italic">Đang tìm gợi ý thông minh...</span>';

        fetch(`/ajax/fields/{{ $field->id }}/suggestions?time_slot_id=${slotId}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.suggestions.length > 0) {
                    suggestionsList.innerHTML = '';
                    data.suggestions.forEach(item => {
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'p-2 bg-white rounded-lg border border-slate-200 flex items-center justify-between text-xs';
                        itemDiv.innerHTML = `
                            <div>
                                <span class="font-bold text-slate-900">${item.field_name}</span>
                                <span class="text-slate-500 block text-[11px]">${item.date_label} · ${item.start_time} - ${item.end_time}</span>
                                <span class="text-[10px] text-emerald-700 font-bold">${item.badge}</span>
                            </div>
                            <a href="/fields/${item.field_slug}" class="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-md text-[11px] transition">
                                Đặt sân này
                            </a>
                        `;
                        suggestionsList.appendChild(itemDiv);
                    });
                } else {
                    suggestionsList.innerHTML = '<span class="text-[11px] text-slate-400 italic">Không có gợi ý khả dụng khác. Bạn hãy đăng ký hàng đợi để chờ giải phóng slot.</span>';
                }
            })
            .catch(() => {
                suggestionsList.innerHTML = '<span class="text-[11px] text-slate-400 italic">Chưa thể tải gợi ý lúc này.</span>';
            });
    }

    document.addEventListener('DOMContentLoaded', checkSlots);
</script>
@endsection
