# Sổ nguồn tham khảo

Ngày khởi tạo: 2026-08-31. Mỗi công việc mới phải ghi nguồn cụ thể ngay trong
bản ghi `Doc/History/` và thêm nguồn mới vào đây.

## Nguồn nội bộ (nguồn sự thật của implementation)

| Phạm vi | Nguồn |
| --- | --- |
| Mục tiêu, cài đặt, tài khoản demo | `README.md` |
| Dependencies và script PHP | `composer.json`, `composer.lock` |
| Dependencies/script frontend và Vite | `package.json`, `package-lock.json`, `frontend/package.json`, `frontend/package-lock.json`, `frontend/vite.config.ts` |
| Toàn bộ endpoint và phân quyền route | `routes/api.php`, `bootstrap/app.php`, `app/Http/Middleware/EnsureUser*.php` |
| Luồng nghiệp vụ | `app/Services/*.php`, `app/DTOs/**/*.php`, `app/Http/Controllers/Api/**/*.php` |
| Mô hình/quan hệ dữ liệu | `app/Models/*.php`, `database/migrations/*.php` |
| Dữ liệu mẫu | `database/seeders/*.php` |
| Giao diện và API client | `frontend/src/**/*.{ts,tsx,css}` |
| Kiểm thử hiện có | `tests/**/*.php`, `phpunit.xml` |
| Nhật ký trạng thái và cách chạy cục bộ | `ProjectLog/PROJECT_LOG.md` |
| Lịch sử Git đã xem | commits `c34940a`, `ee45bc4`, `4099eae` |

## Yêu cầu nghiệp vụ do người dùng cung cấp

| Nguồn | Vai trò |
| --- | --- |
| `C:/Users/acer/.codex/attachments/12c13550-a542-4c41-a477-1a3e71fa729d/pasted-text.txt` | Đề cương dự án: nguồn yêu cầu nghiệp vụ/đích triển khai để đối chiếu với code hiện tại. Được người dùng cung cấp ngày `2026-08-31`. |

## Nguồn ngoài đang được dự án sử dụng

| Nguồn | Mục đích | Vị trí dùng |
| --- | --- | --- |
| [Laravel 13 documentation](https://laravel.com/docs/13.x) | Framework backend, routing, migrations, Eloquent, testing; truy cập `2026-09-01` | Dependency chính trong `composer.json` |
| [Laravel 13 Directory Structure](https://laravel.com/docs/13.x/structure) | Convention thư mục Laravel; truy cập `2026-09-01` | Ánh xạ N-Layer sang cấu trúc Laravel |
| [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) | SPA cookie authentication, CSRF và stateful domains; Laravel, truy cập `2026-09-01` | `bootstrap/app.php`, `config/sanctum.php`, frontend Axios |
| [Laravel 13 RateLimiter API](https://api.laravel.com/docs/13.x/Illuminate/Cache/RateLimiter.html) | Cơ chế giới hạn số lần thử đăng nhập/API; Laravel, truy cập `2026-09-07` | Đề xuất bổ sung rate limit cho endpoint auth |
| [Vite — Getting Started](https://vite.dev/guide/) | Scaffold/build React TypeScript; Vite, truy cập `2026-09-01` | `frontend/`, `frontend/vite.config.ts` |
| [React Router — Declarative Installation](https://reactrouter.com/start/declarative/installation) | Router React declarative; Remix Software, truy cập `2026-09-01` | `frontend/src/App.tsx`, layouts và pages |
| [React Router — Routing](https://reactrouter.com/start/declarative/routing) | Cấu hình route lồng nhau; Remix Software, truy cập `2026-09-01` | `frontend/src/App.tsx` |
| [TanStack Query — Installation](https://tanstack.com/query/latest/docs/framework/react/installation) | Quản lý server state/cache; TanStack, truy cập `2026-09-01` | query/mutation trong React pages |
| [Unsplash](https://unsplash.com) | Ảnh demo sân | URL trong `database/seeders/SportsFieldSeeder.php` |
| Ảnh tạo bởi OpenAI ImageGen ngày `2026-09-02` | Social preview SportHub, không chứa dữ liệu riêng tư | `frontend/public/og.png` |
| [Laravel Framework license](https://opensource.org/licenses/MIT) | License được README nêu | `README.md` |

## Cách bổ sung nguồn

- Tài liệu kỹ thuật: ghi tên, URL trực tiếp, đơn vị phát hành, ngày truy cập và nội dung áp dụng.
- Hình ảnh/dataset/API: ghi URL gốc, giấy phép/điều khoản nếu có và tệp mã sử dụng.
- Trao đổi/yêu cầu người dùng: ghi là `Yêu cầu người dùng, YYYY-MM-DD`, không tạo URL.
- Không đưa token, URL có khóa bí mật, thông tin `.env` hoặc dữ liệu riêng tư vào sổ nguồn.
