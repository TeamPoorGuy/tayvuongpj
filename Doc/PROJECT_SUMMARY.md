# Tóm tắt dự án Tây Vương / SportHub

> Bản ngắn dành cho chủ dự án. Chi tiết kỹ thuật ở `AI_PROJECT_CONTEXT.md` và hợp đồng API ở `API_CONTRACT.md`.

## Dự án là gì?

SportHub là website đặt sân thể thao trực tuyến. Khách hàng tìm sân, xem lịch trống, đặt và đánh giá sân; chủ sân quản lý sân/đơn đặt; admin kiểm duyệt và giám sát hệ thống.

## Kiến trúc hiện tại

Dự án đã được chuyển từ Laravel Blade MVC sang hai phần tách riêng:

- Backend Laravel chỉ cung cấp REST API JSON ở `/api/v1`.
- Frontend React + TypeScript nằm trong `frontend/` và gọi API qua Axios.
- Đăng nhập dùng cookie session an toàn của Laravel Sanctum và CSRF.
- Không còn source Blade, thư mục `resources/`, controller MVC, `routes/web.php` hoặc bundle giao diện Laravel cũ.

Cách tổ chức backend tương đương N-Layer trong Spring Boot/.NET Core: Controller → Request/DTO → Service → Model/Entity → Resource/Response; kèm Config, Enum, Exception và Security middleware.

## Ba nhóm người dùng

| Vai trò | Chức năng đã có |
| --- | --- |
| Customer | Đăng ký/đăng nhập, tìm/lọc/xem sân, lịch trống, đặt/hủy sân, review, hồ sơ và mật khẩu. |
| Field owner | Dashboard, tạo/sửa/bật tắt sân, xử lý booking, xem review, hồ sơ cơ sở. |
| Admin | Dashboard, khóa tài khoản, duyệt chủ sân/sân, xem booking, ẩn review, thêm danh mục/loại sân. |

## Luồng chính

```text
Khách tìm sân -> chọn ngày/slot -> gửi booking pending
-> chủ sân xác nhận hoặc từ chối -> hoàn thành
-> khách được đánh giá một lần -> admin có thể ẩn/hiện đánh giá
```

API mới đã sửa một điểm an toàn quan trọng: slot gửi khi booking phải active và thực sự thuộc sân đã chọn; transaction/lock chặn hai booking hợp lệ cùng chiếm một slot.

## Chạy dự án

Sau khi Laragon MySQL và `.env` đã sẵn sàng:

```powershell
npm run dev
```

Lệnh này chạy Laravel API tại `http://127.0.0.1:8011` và React tại `http://127.0.0.1:5173`.

Chạy riêng nếu cần:

```powershell
php artisan serve --port=8011
npm --prefix frontend run dev
```

## Tình trạng xác minh

- 40 endpoint API v1.
- 13 test / 32 assertion backend đều đạt.
- React TypeScript build thành công.
- Oxlint chạy sạch; favicon và ảnh chia sẻ mang nhận diện SportHub.
- Đã thử luồng thật CSRF + đăng nhập + endpoint riêng cho cả customer, owner và admin; đều trả 200.

## Chưa có

- Thanh toán online thực tế, email/SMS notification và bản đồ.
- UI tùy chỉnh slot, quản lý/xóa từng ảnh.
- Sửa/xóa category và field type.
- Điều kiện bắt buộc hồ sơ chủ sân đã được admin duyệt trước mọi thao tác.
- Bộ kiểm thử trình duyệt end-to-end tự động.

## Vị trí quan trọng

| Vị trí | Nội dung |
| --- | --- |
| `frontend/src/` | Toàn bộ giao diện React. |
| `routes/api.php` | Danh sách endpoint. |
| `app/Http/Controllers/Api/V1/` | Controller JSON theo vai trò. |
| `app/Services/` | Nghiệp vụ chính. |
| `app/DTOs/`, `app/Http/Requests/Api/` | DTO và validation. |
| `app/Http/Resources/Api/` | Chuẩn hóa response. |
| `app/Models/`, `database/migrations/` | Entity và schema. |
| `tests/Feature/` | Kiểm thử API. |
| `Doc/History/` | Lịch sử công việc local. |
