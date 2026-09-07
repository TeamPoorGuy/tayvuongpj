# Project Log — SportHub / Tây Vương

- Cập nhật lần cuối: **2026-09-07 15:59**
- Nhánh hiện tại: `main`
- Commit hiện tại: `cd1da60` — `fix auth`
- Mục đích: ghi lại trạng thái dự án, phần đã sửa, phần đang làm dở và cách chạy project.

> Tệp này chỉ dùng trên máy cá nhân. Quy tắc `*.md` trong `.gitignore` khiến tệp không được đưa lên GitHub.

## 1. Dự án hiện tại là gì?

Project gồm hai ứng dụng trong cùng repository:

```text
Trình duyệt
  -> React + TypeScript + Vite (frontend, port 5173)
  -> gọi Laravel REST API (backend, port 8011)
  -> Laravel xử lý đăng nhập, phân quyền và nghiệp vụ
  -> MySQL của Laragon lưu dữ liệu
```

- Backend: PHP 8.3+, Laravel 13, Laravel Sanctum, Eloquent ORM.
- Frontend: React 19, TypeScript, Vite, React Router, TanStack Query, Axios.
- Database khi chạy: MySQL trong Laragon.
- Database khi test: SQLite in-memory.
- Auth: Sanctum SPA cookie/session + CSRF; không dùng JWT cho web hiện tại.

## 2. Các phần lớn đã hoàn thành

### Chuyển Laravel MVC sang API + React

- Đã bỏ toàn bộ Blade view và controller MVC cũ.
- Đã bỏ `routes/web.php`, `resources/`, Laravel Vite config cũ và bundle giao diện cũ.
- Backend hiện trả JSON qua `/api/v1`.
- Frontend React nằm trong `frontend/`.
- Backend tổ chức theo Controller -> Request -> DTO -> Service -> Model -> Resource.

### Chức năng hiện có

- Customer: đăng ký/đăng nhập, tìm sân, xem lịch trống, đặt/hủy sân, đánh giá và cập nhật hồ sơ/mật khẩu.
- Field owner: dashboard, quản lý sân, booking, review và hồ sơ kinh doanh.
- Admin: dashboard, quản lý tài khoản, duyệt chủ sân/sân, booking, review, category và field type.
- Phân quyền theo ba role: `customer`, `field_owner`, `admin`.

### Git và tài liệu

- `.env`, certificate, `Doc/` và Markdown nội bộ đã bị loại khỏi Git.
- Riêng `/README.md` được phép đưa lên GitHub.
- README trên GitHub hiện là bản tiếng Việt có hướng dẫn FlyEnv từ commit lịch sử `4099eae`.

## 3. Auth vừa sửa — commit `cd1da60`

### Đã thêm

- Tạo middleware `app/Http/Middleware/EnsureFieldOwnerIsApproved.php`.
- Đăng ký alias `owner.approved` trong `bootstrap/app.php`.
- Đưa dashboard/sân/booking/review của owner vào nhóm `owner.approved`.
- Giữ route xem/sửa hồ sơ owner ở ngoài nhóm approved để owner pending vẫn cập nhật hồ sơ được.
- Frontend logout dùng `finally` để luôn xóa trạng thái đăng nhập cục bộ.

### Còn lỗi cần sửa

1. Middleware đang đọc `$profile->verified`, nhưng tên cột thật là `$profile->verification_status`.
   Hậu quả: cả owner đã được duyệt cũng có thể bị chặn 403.
2. Route `/logout` vẫn nằm trong nhóm `['auth:sanctum', 'active']`.
   Hậu quả: tài khoản bị admin khóa vẫn không đăng xuất sạch được.
3. Chưa có test cho owner pending/approved và logout của tài khoản bị khóa.
4. Login/register chưa có rate limit chống thử mật khẩu liên tục.

Vì vậy commit auth hiện mới là **đang làm dở**, chưa nên coi là hoàn tất dù test cũ vẫn đạt.

## 4. Cách chạy project trên máy hiện tại

Máy hiện tại đã có `.env`, Composer vendor và node_modules cho cả root/frontend.

### Cách đơn giản nhất

1. Mở Laragon.
2. Bấm **Start All**, hoặc ít nhất đảm bảo MySQL đang chạy.
3. Mở terminal tại:

```text
C:\Users\acer\Documents\OpenSource\tayvuongpj
```

4. Chạy:

```bash
npm run dev
```

5. Giữ terminal mở và truy cập:

```text
http://127.0.0.1:5173
```

- `5173`: giao diện React để người dùng truy cập.
- `8011`: Laravel API; không phải trang giao diện.
- Dừng cả hai server bằng `Ctrl + C`.

### Chạy bằng hai terminal để dễ xem lỗi

Terminal 1 — Laravel API:

```bash
php artisan serve --port=8011
```

Terminal 2 — React/Vite:

```bash
npm --prefix frontend run dev
```

Sau đó vẫn mở `http://127.0.0.1:5173`.

### Port được cấu hình ở đâu?

- Backend `8011`: script `dev` và `dev:api` trong `package.json`.
- Frontend `5173`: `server.port` trong `frontend/vite.config.ts`.
- Vite proxy `/api`, `/sanctum`, `/storage` sang `http://127.0.0.1:8011`.

## 5. Cài đặt lần đầu trên máy mới

Chỉ làm phần này khi vừa clone project hoặc đã xóa dependencies:

```powershell
composer install
npm install
npm --prefix frontend install
Copy-Item .env.example .env
php artisan key:generate
```

Sau đó mở `.env` và cấu hình MySQL Laragon, rồi chạy:

```powershell
php artisan migrate --seed
php artisan storage:link
npm run dev
```

Không đưa `.env` lên GitHub. Không ghi mật khẩu thật vào ProjectLog.

## 6. Database

Cấu hình mẫu:

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sporthub
```

Nếu chưa có bảng:

```bash
php artisan migrate --seed
```

Không chạy lệnh sau trên database cần giữ dữ liệu:

```bash
php artisan migrate:fresh --seed
```

Lệnh `migrate:fresh` xóa toàn bộ bảng rồi tạo lại.

## 7. Kiểm tra code

Backend:

```bash
php artisan test
```

Frontend:

```bash
npm run lint
npm run build
```

Kết quả gần nhất ngày 2026-09-07:

- PHP syntax: đạt.
- Backend: 13 test, 32 assertion đều đạt.
- Frontend lint: đạt.
- React/TypeScript production build: đạt.

Lưu ý: test hiện tại chưa bao phủ hai lỗi logic auth ghi tại mục 3.

## 8. Trình tự công việc tiếp theo

1. Sửa `$profile->verified` thành `$profile->verification_status`.
2. Đưa `/logout` ra khỏi middleware `active` nhưng vẫn giữ `auth:sanctum`.
3. Thêm test owner pending/approved và logout tài khoản bị khóa.
4. Thêm rate limit cho login/register.
5. Chạy lại test, lint và build.
6. Chỉ commit/push sau khi các kiểm tra đạt.

## 9. Xử lý lỗi chạy thường gặp

### Không kết nối được MySQL

- Kiểm tra MySQL trong Laragon đã chạy chưa.
- Kiểm tra tên database trong `.env` đã được tạo chưa.
- Không gửi nội dung `.env` có mật khẩu lên GitHub hoặc chat công khai.

### Port đã được sử dụng

- Dừng terminal/server cũ bằng `Ctrl + C`.
- Nếu đổi port backend, phải sửa cả `package.json`, proxy trong `frontend/vite.config.ts`, `APP_URL` và `SANCTUM_STATEFUL_DOMAINS`.

### Thiếu thư viện

```bash
composer install
npm install
npm --prefix frontend install
```

### Ảnh upload không hiển thị

```bash
php artisan storage:link
```
