<?php

return [
    // Đơn chờ duyệt: khách huỷ lúc nào cũng được, không bị trừ điểm.
    // Đơn đã duyệt: khách chỉ huỷ được trong N giờ kể từ lúc chủ sân duyệt.
    'confirmed_cancel_window_hours' => (int) env('BOOKING_CONFIRMED_CANCEL_WINDOW_HOURS', 2),
    // Đơn chờ duyệt quá N giờ mà chủ sân không phản hồi thì tự huỷ (hoặc đến giờ chơi, nếu sớm hơn).
    'pending_expire_hours' => (int) env('BOOKING_PENDING_EXPIRE_HOURS', 24),
    // Huỷ đơn đã duyệt khi còn dưới N giờ trước giờ bắt đầu thì bị trừ điểm uy tín.
    'late_cancel_hours' => (int) env('BOOKING_LATE_CANCEL_HOURS', 4),
    // Phiên bản điều khoản chủ sân; đổi giá trị khi sửa nội dung điều khoản.
    'owner_terms_version' => '2026-10',
];
