# ⚽ Website Quản Lý và Đặt Sân Thể Thao Trực Tuyến (SportHub)

Hệ thống trung gian kết nối khách hàng có nhu cầu thể thao với các chủ sân bóng đá, cầu lông, tennis, bóng chuyền, bóng rổ. Hỗ trợ tìm kiếm, kiểm tra khung giờ còn trống realtime không cần tải lại trang (AJAX), đặt sân, theo dõi đơn hàng và quản lý doanh thu.

---

## 📌 Các Nhóm Người Dùng & Chức Năng Chính

### 1. ⚽ Khách Hàng (Customer / User)
- **Tài khoản**: Đăng ký, đăng nhập, đổi mật khẩu, xem và cập nhật hồ sơ cá nhân, đổi ảnh đại diện.
- **Tìm kiếm & Lọc**: Tìm sân theo tên/địa chỉ, lọc theo danh mục thể thao (Bóng đá, Cầu lông, Tennis, Bóng rổ, Bóng chuyền), lọc theo loại sân, khoảng giá thuê.
- **Kiểm tra khung giờ realtime (AJAX)**: Chọn ngày để xem các khung giờ còn trống trực tiếp mà không cần reload trang.
- **Đặt sân & Thanh toán**: Xác nhận thông tin, xem tổng số tiền và gửi yêu cầu đặt sân.
- **Lịch sử & Đánh giá**: Theo dõi trạng thái đơn hàng (Chờ xác nhận, Đã xác nhận, Đã hoàn thành, Đã hủy/Từ chối), hủy đơn khi đủ điều kiện, đánh giá số sao (1-5⭐) và viết bình luận sau khi sử dụng sân.

### 2. 🏢 Chủ Sân (Field Owner)
- **Quản lý sân bóng**: Đăng bài sân mới, cập nhật thông tin sân, ngừng/bật hoạt động sân, upload nhiều hình ảnh sân bóng.
- **Quản lý giá & Khung giờ**: Cấu hình giá thuê theo giờ và các khung giờ hoạt động.
- **Quản lý đơn đặt**: Xem lịch đặt sân, xác nhận hoặc từ chối đơn đặt, đánh dấu hoàn thành sau khi khách sử dụng.
- **Thống kê doanh thu**: Theo dõi tổng số sân, tổng lượt đặt, lượt đặt theo ngày/tháng, doanh thu theo ngày/tháng, thống kê sân hot nhất.

### 3. 👑 Quản Trị Viên (System Admin)
- **Quản lý tài khoản**: Khóa / mở khóa tài khoản Khách hàng và Chủ sân.
- **Kiểm duyệt chủ sân**: Duyệt hoặc từ chối hồ sơ đăng ký tài khoản chủ sân.
- **Kiểm duyệt sân bóng**: Duyệt sân bóng do chủ sân đăng trước khi hiển thị công khai trên hệ thống.
- **Quản lý danh mục**: Thêm/sửa danh mục thể thao và loại sân.
- **Báo cáo & Giám sát**: Thống kê toàn bộ số liệu hệ thống (doanh thu, đơn hàng, người dùng), giám sát và ẩn các đánh giá/bình luận vi phạm.

---

## 🛠️ Công Nghệ Sử Dụng

- **Backend**: Laravel 13.x (API-only, `/api/v1`), PHP 8.3+, Sanctum (session cookie SPA)
- **Database**: MySQL (mặc định trong `.env.example`) / SQLite
- **Frontend**: React 19 + Vite + TypeScript (thư mục `frontend/`), TanStack Query, Leaflet
- **Phân quyền**: `admin` và `customer`. Chủ sân là `customer` có hồ sơ chủ sân đã duyệt (`field_owner_profiles.verification_status = approved`)
- **Hàng đợi & lịch chạy**: queue `database` (gửi mail) và scheduler (đóng đơn quá hạn, phạt no-show, hết hạn lời mời waitlist)

---

## 🗄️ Sơ Đồ Quan Hệ Dữ Liệu (ERD)

```mermaid
erDiagram
    User ||--o| FieldOwnerProfile : "has one"
    User ||--o{ Booking : "makes"
    User ||--o{ Review : "writes"
    User ||--o{ SportsField : "owns"
    
    SportCategory ||--o{ FieldType : "has many"
    FieldType ||--o{ SportsField : "has many"
    
    SportsField ||--o{ FieldImage : "has many"
    SportsField ||--o{ TimeSlot : "has many"
    SportsField ||--o{ Booking : "has many"
    SportsField ||--o{ Review : "has many"
    
    TimeSlot ||--o{ Booking : "has many"
    Booking ||--o| Review : "has one"
    User ||--o{ BookingWaitlist : "queues"
    SportsField ||--o{ BookingWaitlist : "has many"
    User ||--o{ MatchRequest : "hosts"
    MatchRequest ||--o{ MatchParticipant : "has many"
    User ||--o{ MatchParticipant : "joins"
    User ||--o{ TrustScoreLog : "has many"
    FieldOwnerProfile ||--o{ OwnerDocument : "has many"
```

---

## ⚙️ Hướng Dẫn Cài Đặt Dự Án (Installation Guide)

### 1. Yêu cầu hệ thống
- PHP >= 8.3, Composer 2.x
- Node.js 20+ và NPM
- MySQL 8 (hoặc SQLite nếu đổi `DB_CONNECTION` trong `.env`)

### 2. Cài đặt (chạy local)

```bash
cp .env.example .env            # chỉnh DB_* cho khớp MySQL local (mặc định database `sporthub`)
composer install
php artisan key:generate
php artisan migrate:fresh --seed   # tạo bảng + dữ liệu demo
php artisan storage:link           # để xem ảnh upload

npm install                      # công cụ chạy song song (concurrently)
npm --prefix frontend install
```

### 3. Chạy hệ thống

```bash
npm run dev
```

Lệnh này chạy cùng lúc 4 tiến trình:

| Tên | Lệnh | Vai trò |
|---|---|---|
| api | `php artisan serve --port=8011` | Backend: http://127.0.0.1:8011 |
| queue | `php artisan queue:work --tries=1` | Gửi mail (xác nhận đơn, lời mời waitlist, huỷ trận…) |
| schedule | `php artisan schedule:work` | `bookings:sync` (15 phút), `waitlist:expire` (mỗi phút), `matches:close-past` (mỗi giờ) |
| frontend | `npm --prefix frontend run dev` | Giao diện: http://127.0.0.1:5173 |

Thiếu `queue` thì mail không được gửi; thiếu `schedule` thì đơn quá hạn không tự đóng, no-show không bị phạt, lời mời waitlist không hết hạn.

### 4. Cấu hình tuỳ chọn
- **Mail**: mặc định `MAIL_MAILER=log` (xem `storage/logs`). Dùng Mailpit/ServBay bằng cách đổi `MAIL_*` trong `.env`.
- **Đăng nhập Google**: điền `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` trong `.env`. Không commit file `client_secret*.json`.
- **Chạy test**: `php artisan test` (dùng SQLite in-memory, không đụng DB local).

---

## 🔑 Tài Khoản Mẫu Để Kiểm Thử (Dữ Liệu Seeded)

Tất cả tài khoản mẫu có mật khẩu chung là: **`password`**

| Vai trò | Email đăng nhập | Mật khẩu | Kịch bản kiểm thử & Mô tả |
|---|---|---|---|
| **System Admin** | `admin@sportfield.com` | `password` | Quản trị viên tối cao: Kiểm duyệt sân, duyệt chủ sân, khóa user, xem thống kê |
| **Admin 2 (Mod)** | `admin2@sportfield.com` | `password` | Quản trị viên kiểm duyệt phụ |
| **Chủ sân 1 (Approved)** | `owner1@sportfield.com` | `password` | CLB Tây Vương: 6 sân (bóng đá 5/7/11, tennis, cầu lông), nhiều đơn confirmed/pending |
| **Chủ sân 2 (Approved)** | `owner2@sportfield.com` | `password` | KPH Sao Mai: sân bóng rổ, bóng chuyền, 1 sân bóng đá bị từ chối |
| **Chủ sân 3 (Pending)** | `owner3@sportfield.com` | `password` | Sân Cầu Lông & Pickleball Cầu Giấy: Chờ Admin duyệt hồ sơ & duyệt sân |
| **Chủ sân 4 (Rejected)** | `owner4@sportfield.com` | `password` | Tổ hợp Hà Đông: Bị từ chối duyệt (xem lý do từ chối & nộp lại hồ sơ) |
| **Chủ sân 5 (Bị khóa)** | `owner5@sportfield.com` | `password` | Tài khoản chủ sân bị Admin khóa (`is_active = false`) |
| **Khách hàng 1** | `customer@sportfield.com` | `password` | Lê Văn Khách: Đặt sân nhiều, có đơn hoàn thành & review |
| **Khách hàng 2** | `customer2@sportfield.com` | `password` | Phạm Hoàng Nam: Điểm uy tín 110, host phòng ghép đội & nhận slot waitlist |
| **Khách hàng 3** | `customer3@sportfield.com` | `password` | Vũ Thị Hương: Điểm uy tín 95, đang chờ hàng đợi waitlist slot sân |
| **Khách hàng 4** | `customer4@sportfield.com` | `password` | Đặng Anh Khoa: Điểm uy tín 65 (có lịch sử bị phạt no-show & huỷ sát giờ) |
| **Khách hàng 5** | `customer5@sportfield.com` | `password` | Ngô Bảo Long: Khách hàng thường |
| **Khách hàng 6 (Bị khóa)** | `customer6@sportfield.com` | `password` | Đỗ Mỹ Linh: Tài khoản khách hàng bị Admin khóa |
| **Khách hàng 7-10** | `customer7@sportfield.com` -> `10` | `password` | Tham gia giao lưu các phòng ghép đội tìm đối thủ |

---

## 🧪 Chạy Kiểm Thử Tự Động (Automated Testing)

Dự án tích hợp sẵn bộ kiểm thử tính năng (Feature Tests) với PHPUnit / Laravel Test:

```bash
php artisan test
```

---

## 📜 Giấy Phép (License)

Dự án đồ án học thuật mở rộng phát triển trên nền tảng Laravel Framework ([MIT License](https://opensource.org/licenses/MIT)).
