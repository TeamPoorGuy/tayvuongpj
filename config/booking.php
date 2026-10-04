<?php

return [
    // Khách chỉ được huỷ khi còn cách giờ bắt đầu ít nhất N giờ.
    'cancel_cutoff_hours' => (int) env('BOOKING_CANCEL_CUTOFF_HOURS', 2),
    // Phiên bản điều khoản chủ sân; đổi giá trị khi sửa nội dung điều khoản.
    'owner_terms_version' => '2026-10',
];
