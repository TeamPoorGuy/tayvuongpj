<?php

return [
    // Đơn chờ duyệt: khách huỷ lúc nào cũng được, không bị trừ điểm.
    // Đơn đã duyệt: khách chỉ huỷ được trong N giờ kể từ lúc chủ sân duyệt.
    'confirmed_cancel_window_hours' => (int) env('BOOKING_CONFIRMED_CANCEL_WINDOW_HOURS', 2),
    // Đơn chờ duyệt quá N giờ mà chủ sân không phản hồi thì tự huỷ (hoặc đến giờ chơi, nếu sớm hơn).
    'pending_expire_hours' => (int) env('BOOKING_PENDING_EXPIRE_HOURS', 24),
    // Huỷ đơn đã duyệt khi còn dưới N giờ trước giờ bắt đầu thì bị trừ điểm uy tín.
    'late_cancel_hours' => (int) env('BOOKING_LATE_CANCEL_HOURS', 4),
    // Thanh toán chuyển khoản: mức cọc (% tổng tiền, làm tròn lên nghìn đồng) nếu khách không trả hết.
    'deposit_percent' => (int) env('BOOKING_DEPOSIT_PERCENT', 30),
    // Sau khi chủ sân duyệt, khách có N giờ để chuyển khoản (không muộn hơn giờ bắt đầu chơi).
    'payment_window_hours' => (int) env('BOOKING_PAYMENT_WINDOW_HOURS', 2),
    // Thời gian cho phép huỷ miễn phí trước giờ chơi (thay ý nghĩa của late_cancel_hours).
    'free_cancel_hours' => (int) env('BOOKING_FREE_CANCEL_HOURS', 4),
    // Tỉ lệ hoa hồng nền tảng mặc định (%)
    'commission_percent' => (int) env('PLATFORM_COMMISSION_PERCENT', 8),
    // Số ngày chủ sân cần thanh toán đối soát kể từ ngày xuất bảng
    'statement_due_days' => (int) env('STATEMENT_DUE_DAYS', 7),
    // Số đơn chờ duyệt tối đa một khách được tạo cùng lúc
    'max_pending_bookings' => (int) env('BOOKING_MAX_PENDING', 3),
    // Số ngày tối đa được đặt trước
    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 30),
    // Thông tin tài khoản nhận phí nền tảng
    'platform_bank_name' => env('PLATFORM_BANK_NAME', 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)'),
    'platform_bank_account' => env('PLATFORM_BANK_ACCOUNT', '1234567890'),
    'platform_bank_holder' => env('PLATFORM_BANK_HOLDER', 'CONG TY SPORTHUB VIET NAM'),
    // Thông tin liên hệ hỗ trợ
    'support_email' => env('SUPPORT_EMAIL', 'hotro@sporthub.vn'),
    'support_hotline' => env('SUPPORT_HOTLINE', '1900 6868'),
    // Phiên bản điều khoản chủ sân; đổi giá trị khi sửa nội dung điều khoản.
    'owner_terms_version' => '2026-10',
];
