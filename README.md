# SportHub — Website quản lý và đặt sân thể thao

SportHub là hệ thống kết nối khách hàng với chủ sân. Dự án được tách thành hai ứng dụng độc lập:

- `backend`: Laravel REST API tại thư mục gốc.
- `frontend`: React + TypeScript + Vite trong `frontend/`.

Backend không render giao diện Blade. Mọi giao tiếp giữa hai ứng dụng dùng JSON qua `/api/v1`, còn xác thực SPA dùng Laravel Sanctum.

## Chức năng

- Khách hàng: đăng ký/đăng nhập, tìm và lọc sân, xem lịch trống, đặt/hủy sân, đánh giá, cập nhật hồ sơ và mật khẩu.
- Chủ sân: dashboard, quản lý sân/hình ảnh/khung giờ, xử lý booking, xem đánh giá và cập nhật hồ sơ kinh doanh.
- Quản trị viên: dashboard toàn hệ thống, khóa tài khoản, duyệt chủ sân/sân, quản lý booking, đánh giá, danh mục và loại sân.

## Kiến trúc backend

Kiến trúc được tổ chức theo vai trò tương đương N-Layer trong Spring Boot hoặc ASP.NET Core:

| Vai trò N-Layer | Laravel |
|---|---|
| Controller | `app/Http/Controllers/Api/V1/` |
| Request DTO / validation | `app/Http/Requests/Api/` |
| DTO | `app/DTOs/` |
| Response DTO | `app/Http/Resources/Api/` |
| Service / business logic | `app/Services/` |
| Entity / ORM | `app/Models/` |
| Enum | `app/Enums/` |
| Exception handler | `app/Exceptions/` và `bootstrap/app.php` |
| Security | Sanctum và `app/Http/Middleware/` |
| Configuration | `config/` |

Luồng chính:

```text
React -> API Route -> Security Middleware -> Form Request -> Controller
      -> DTO -> Service -> Eloquent Model -> API Resource -> JSON
```

## Công nghệ

- Backend: PHP 8.3+, Laravel 13, Laravel Sanctum.
- Database: MySQL (Laragon); SQLite in-memory dành riêng cho test.
- Frontend: React 19, TypeScript, Vite, React Router, TanStack Query, Axios.

## Cài đặt

```powershell
composer install
npm install
npm --prefix frontend install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Cấu hình kết nối MySQL của Laragon trong `.env`. Không đưa `.env` hoặc thông tin đăng nhập thật lên Git.

## Chạy dự án

Cách ngắn nhất, chạy cả API và React trong một terminal:

```powershell
npm run dev
```

Hoặc chạy riêng hai terminal:

```powershell
# Terminal 1 — Laravel API
php artisan serve --port=8011

# Terminal 2 — React
npm --prefix frontend run dev
```

Truy cập giao diện tại `http://127.0.0.1:5173`. Metadata backend ở `http://127.0.0.1:8011/api`, API nghiệp vụ ở `http://127.0.0.1:8011/api/v1`, trạng thái dịch vụ ở `http://127.0.0.1:8011/up`.

## Build và kiểm thử

```powershell
npm run build
npm run lint
php artisan test
php artisan route:list --path=api/v1
```

## Dữ liệu mẫu

Seeder hiện có tài khoản mẫu cho ba vai trò. Chạy `php artisan migrate:fresh --seed` khi muốn tạo lại toàn bộ dữ liệu phát triển. Thao tác này xóa dữ liệu hiện tại, vì vậy không chạy trên cơ sở dữ liệu cần giữ lại.

## Tài liệu nội bộ cho AI

Các tài liệu ngữ cảnh, lịch sử thay đổi và nguồn tham khảo nằm trong `Doc/`. Thư mục này được cố ý loại khỏi Git để chỉ dùng cục bộ theo yêu cầu của chủ dự án.
