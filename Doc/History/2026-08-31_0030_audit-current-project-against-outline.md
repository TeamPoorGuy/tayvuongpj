# Rà soát khung hiện tại theo đề cương dự án

- Ngày giờ: `2026-08-31 00:30` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Đối chiếu implementation hiện tại với đề cương Website Quản lý và Đặt sân Thể thao Trực tuyến

## Mục tiêu

Xác định phần nào của project hiện tại đã đúng hoặc gần đúng với đề cương, và
phần nào chỉ là khung/chưa có. Không thay đổi code hoặc dữ liệu ứng dụng.

## Bối cảnh và đánh giá trước khi làm

- Đề cương là định hướng nghiệp vụ cần ưu tiên cho các lần phát triển sau.
- Route list hiện đăng ký 41 route. Mã có ba vai trò `admin`, `field_owner`,
  `customer`, cùng migration/model cho các thực thể nghiệp vụ cốt lõi.
- Đây là audit tĩnh theo route, controller, model, migration và Blade hiện có;
  không chạy test vì cấu hình MySQL hiện vẫn làm test chờ kết nối.

## Kết quả đối chiếu

### Đã đạt hoặc gần đạt

| Nhóm đề cương | Mức độ | Bằng chứng / ghi chú |
| --- | --- | --- |
| Ba role và phân quyền | Đạt | Có middleware `auth` + `role:*`, controller/view tách theo Customer, FieldOwner, Admin. |
| Đăng ký, đăng nhập, đăng xuất | Đạt | Có route/controller/form cho login, register, logout; đăng ký tạo customer hoặc field owner. |
| Hồ sơ khách hàng, đổi mật khẩu, avatar | Đạt | Customer có cập nhật `users`, upload avatar local public và đổi mật khẩu. Không có bảng `customer_profiles` riêng. |
| Danh sách/chi tiết sân | Đạt phần lớn | Hiển thị loại, ảnh, giá, mô tả, địa chỉ, chủ sân, đánh giá; chỉ truy cập public với sân approved + active. |
| Tìm kiếm/lọc sân cơ bản | Đạt phần lớn | Tìm theo tên/địa chỉ, lọc category, field type, khoảng giá; có phân trang. Chưa lọc danh sách theo ngày/khung giờ. |
| Kiểm tra slot trống AJAX | Đạt | Detail page gọi endpoint `check-slots`, xem slot bận/trống theo ngày. |
| Booking, lịch sử, giá và trạng thái | Đạt phần lớn | Customer gửi booking, thấy tổng tiền/trạng thái/lịch sử, hủy đơn tương lai phù hợp, review sau completed. Giá đang tính cứng 90 phút. |
| Chủ sân quản lý sân | Đạt phần lớn | Tạo, xem, sửa, bật/tắt sân; upload nhiều ảnh lúc tạo; giá theo giờ; chỉ quản lý sân của mình. Chưa xóa sân/quản lý lại ảnh. |
| Chủ sân xử lý đơn/review | Đạt phần lớn | Xem/lọc theo sân, trạng thái, ngày; xác nhận/từ chối/hoàn thành; xem review thuộc sân của mình. Chưa lọc theo khách hàng. |
| Thống kê chủ sân | Đạt phần lớn | Có tổng sân/đơn/doanh thu, số đơn/doanh thu tháng hiện tại, sân nhiều booking nhất. Chưa có báo cáo ngày/tháng tùy chọn. |
| Duyệt chủ sân và sân | Đạt một phần | Admin duyệt/từ chối profile chủ sân và sân. Code chưa chặn chủ sân `pending` tạo sân hoặc cập nhật booking. |
| Quản trị user/sân/booking | Đạt một phần | Admin list/paginate user, sân, booking, profile; khóa/mở user; lọc user theo role/từ khóa và sân/booking theo status. |
| Dashboard admin | Đạt phần lớn | Có tổng customer, owner, sân, booking, doanh thu, sân đặt nhiều nhất. Chưa có thống kê theo ngày/tháng. |
| Schema/quan hệ lõi | Đạt phần lớn | Có users, owner profile, category/type, field/image/slot, booking, review và quan hệ Eloquent/migration tương ứng. |

### Chưa đạt hoặc chỉ là khung

| Hạng mục đề cương | Hiện trạng |
| --- | --- |
| Quên mật khẩu | Có bảng token Laravel mặc định nhưng không có route/controller/form hoặc luồng gửi mail. |
| CRUD đủ cho mọi thực thể | Chưa đủ: hầu hết chỉ có create/read/update từng phần. Không có xóa sân/ảnh/slot/category/type/review/booking; admin không CRUD user/profile đầy đủ. |
| Quản lý khung giờ chủ sân | Chỉ tự sinh 8 slot 90 phút khi tạo sân; không có trang/API thêm, sửa, xóa, bật/tắt slot. |
| Quản lý ảnh sân | Upload nhiều ảnh khi tạo sân, nhưng không sửa/xóa/đặt lại ảnh chính từ giao diện. |
| Giấy tờ xác minh chủ sân | Schema có field văn bản nhưng không có upload tài liệu hay review tài liệu. |
| Filter theo ngày/giờ của sân | Chỉ kiểm tra slot sau khi mở chi tiết; không có bộ lọc kết quả sân theo ngày/giờ. |
| Filter booking đầy đủ | Owner thiếu filter customer; admin chỉ filter status; chưa filter theo owner/customer/field đa tiêu chí như đề cương. |
| Tìm kiếm/lọc AJAX | Chỉ AJAX check slots. Search/filter sân, review, admin duyệt, owner xử lý booking đều dùng form POST/GET render lại trang. |
| Quản lý khiếu nại/nội dung vi phạm | Không có model, migration, route hay UI khiếu nại. Review chỉ có cờ `is_visible`. |
| Quản lý category/type | Admin chỉ thêm mới và xem; chưa sửa/xóa/bật-tắt. |
| Phân trang category/type | List category hiện lấy toàn bộ; các list chính còn lại đã paginate. |
| Customer profile quan hệ 1-1 riêng | Không có `customer_profiles`; dữ liệu cá nhân lưu trực tiếp trong `users`. Đây có thể là lựa chọn thiết kế hợp lệ nếu không cần dữ liệu riêng thêm. |

### Lỗi/rủi ro cần tính khi bắt đầu triển khai

1. Route `admin/reviews/{id}/toggle` gọi method `toggle`, nhưng controller có
   `toggleVisibility`; chức năng ẩn/hiện review hiện sẽ lỗi.
2. Booking chỉ kiểm tra ID slot tồn tại, chưa kiểm tra slot thuộc sân đang đặt;
   đồng thời database chưa unique `(sports_field_id, time_slot_id, booking_date)`
   để chống tranh chấp khi hai request đặt đồng thời.
3. Trạng thái chủ sân approved/rejected hiện chưa được dùng để chặn workflow
   theo bước 2 → 3 trong đề cương.
4. Field owner có thể gửi `cancelled` trong endpoint update status, nhưng không
   có quy tắc nghiệp vụ chi tiết/cancellation reason tương ứng cho chủ sân.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ tài liệu audit này và registry nguồn `Doc/REFERENCES.md`.
- Không sửa mã nguồn, migration, route, cấu hình hay dữ liệu.
- Nếu bắt đầu làm sau này, nên ưu tiên hoàn thiện luồng cốt lõi theo thứ tự:
  quyền/duyệt chủ sân → slot + an toàn booking → CRUD thiếu → filter/report →
  AJAX mở rộng/khiếu nại.

## Kiểm thử / xác minh

- Lệnh: `php artisan route:list --except-vendor`.
- Kết quả: 41 route được đăng ký, đối chiếu với controller và Blade.
- Việc chưa xác minh: end-to-end/database tests; lý do là connection hiện ép
  MySQL và test đang chờ kết nối, theo audit trước.

## Nguồn tham khảo

- Nguồn yêu cầu: `C:/Users/acer/.codex/attachments/12c13550-a542-4c41-a477-1a3e71fa729d/pasted-text.txt` — đề cương người dùng cung cấp, `2026-08-31`.
- Nguồn nội bộ: `routes/web.php`, `app/Http/Controllers/**/*.php`,
  `resources/views/**/*.blade.php` — chức năng, route và giao diện hiện có.
- Nguồn nội bộ: `app/Models/*.php`, `database/migrations/*.php` — dữ liệu và quan hệ.
- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` — audit implementation trước đó.

## Việc tiếp theo

- [ ] Chỉ bắt đầu thay đổi khi người dùng chỉ định ưu tiên/chức năng đầu tiên.
- [ ] Khi bắt đầu, tạo bản ghi kế tiếp và chuyển từng hạng mục từ đề cương thành tiêu chí nghiệm thu cụ thể.
