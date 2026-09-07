# Chuyển SportHub sang Laravel API và React theo kiến trúc phân lớp

- Ngày giờ: `2026-09-01 20:32` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: toàn bộ backend, frontend, authentication, test và cấu trúc dự án

## Mục tiêu

Chuyển ứng dụng Laravel MVC/Blade thành Laravel REST API và React TypeScript SPA tách riêng trong cùng repository. Backend tổ chức theo DTO, Request, Resource/Response, Service, Controller, Config, Model/Entity, Enum, Exception và Security tương tự N-Layer của Spring Boot/.NET Core.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: 41 route web/AJAX, 9 Eloquent model, 19 controller MVC và 31 Blade view; chưa có `routes/api.php`, Sanctum hoặc React.
- Ràng buộc/rủi ro: giữ schema và dữ liệu MySQL hiện có; giữ ba vai trò và luồng booking/kiểm duyệt/upload/review; không ghi bí mật `.env`; `Doc/` tiếp tục bị ignore khỏi Git.
- Quyết định và lý do: dùng Laravel Sanctum cho SPA first-party; React + TypeScript + Vite làm frontend riêng; giữ Model làm Entity/Persistence theo convention Laravel, tránh tạo lớp trùng nghĩa chỉ để giống Java/C# về tên.

## Thay đổi hoặc đề xuất

- Backend mới: `routes/api.php`, `app/DTOs/`, `app/Enums/`, `app/Exceptions/`, `app/Services/`, `app/Http/Controllers/Api/V1/`, `app/Http/Requests/Api/`, `app/Http/Resources/Api/`, middleware role/active, cấu hình Sanctum/CORS/frontend và global JSON exception handler.
- Frontend mới: toàn bộ ứng dụng `frontend/` gồm API client, auth context, protected routes, public/customer/owner/admin layouts và pages, responsive CSS.
- Security/nghiệp vụ: xác minh slot thuộc đúng sân, chặn sân không active/approved, transaction + lock khi booking, ownership cho owner, state transition booking rõ ràng, sửa luồng toggle review admin trong API.
- Tooling: root `npm run dev` chạy Laravel port 8011 + React port 5173; build/lint ủy quyền vào `frontend`; PHPUnit bị cô lập bằng SQLite in-memory ngay cả khi máy có biến môi trường database toàn cục.
- Dọn cũ: xóa controller MVC, Blade view, Laravel Vite CSS/JS, role middleware cũ và route HTML; `routes/web.php` chỉ còn metadata JSON của API.
- Tài liệu: cập nhật `README.md`, `Doc/AI_PROJECT_CONTEXT.md`, `PROJECT_SUMMARY.md`, `REFERENCES.md`, thêm `API_CONTRACT.md`.
- Điều không thay đổi: 9 model/schema nghiệp vụ, seed data và dữ liệu MySQL hiện hữu; bổ sung riêng migration `personal_access_tokens` của Sanctum.

## Kiểm thử / xác minh

- `php artisan test`: đạt `8 tests`, `19 assertions`.
- `npm run build`: TypeScript compile và Vite production build thành công (1.993 module).
- `npm run lint`: chạy thành công; còn warning không chặn build về Fast Refresh, state initialization và key JSX.
- `php artisan route:list --path=api/v1`: 40 route API v1.
- HTTP smoke test public: `/api/v1/home` trả 200.
- HTTP smoke test thật qua Vite proxy: CSRF cookie 204; đăng nhập và endpoint protected của customer, owner, admin đều trả 200.
- PHP syntax: toàn bộ file PHP mới đã qua `php -l` trong vòng kiểm tra triển khai.
- Chưa chạy browser E2E tự động hoặc visual QA vì người dùng không yêu cầu; build và HTTP flow đã xác minh phần tích hợp.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md` trước chuyển đổi, `routes/web.php`, controller MVC, model, migration, seeder và Blade cũ — nguồn nghiệp vụ để chuyển đổi.
- Nguồn nội bộ sau chuyển đổi: `routes/api.php`, `app/Services/`, `app/Http/Controllers/Api/V1/`, `frontend/src/`, `tests/Feature/` — nguồn sự thật của implementation mới.
- Nguồn yêu cầu: đề cương tại `C:/Users/acer/.codex/attachments/12c13550-a542-4c41-a477-1a3e71fa729d/pasted-text.txt` và yêu cầu người dùng ngày `2026-09-01`.
- [Laravel 13 Directory Structure](https://laravel.com/docs/13.x/structure) — Laravel, truy cập `2026-09-01`.
- [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum) — Laravel, truy cập `2026-09-01`.
- [Vite Getting Started](https://vite.dev/guide/) — Vite, truy cập `2026-09-01`.
- [React Router Declarative Installation](https://reactrouter.com/start/declarative/installation) và [Routing](https://reactrouter.com/start/declarative/routing) — React Router, truy cập `2026-09-01`.
- [TanStack Query Installation](https://tanstack.com/query/latest/docs/framework/react/installation) — TanStack, truy cập `2026-09-01`.
- Quy trình frontend: skill `sites-building` của môi trường Codex; dự án giữ local vì không có `.openai/hosting.json` và người dùng chạy Laragon.

## Việc tiếp theo

- [ ] Bổ sung browser E2E cho các hành trình customer/owner/admin.
- [ ] Quyết định có bắt buộc owner profile được duyệt trước khi quản lý sân/booking hay không.
- [ ] Thiết kế payment/notification và UI tùy chỉnh time slot khi vào giai đoạn tiếp theo.
