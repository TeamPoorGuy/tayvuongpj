# Hoàn tất chuyển đổi Laravel API và React

- Ngày giờ: `2026-09-02 15:40` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đã thực hiện`
- Người/AI thực hiện: Codex
- Liên quan đến: vòng kiểm tra và tinh chỉnh cuối của backend API, React SPA và tài liệu

## Mục tiêu

Tiếp tục phần công việc bị dừng hôm trước: làm sạch cảnh báo frontend, rà soát tính nhất quán sau khi xóa MVC/Blade cũ, chạy lại toàn bộ kiểm thử và hoàn thiện tài liệu bàn giao.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: REST API và React SPA đã được triển khai; backend có 40 route, 8 test/19 assertion đã đạt; frontend build đạt nhưng còn cảnh báo lint.
- Ràng buộc/rủi ro: giữ nguyên schema và dữ liệu MySQL hiện có, không đưa bí mật `.env` vào tài liệu, không triển khai hosting vì dự án được yêu cầu chạy local với Laragon.
- Quyết định và lý do: tiếp tục trên kiến trúc hiện có, chỉ tinh chỉnh chất lượng và sửa lỗi xác minh được; không mở rộng tính năng ngoài đề cương.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: React auth context/profile/admin pages; `CatalogService`; test API; `bootstrap/app.php`, `routes/web.php`, `config/app.php`, `.env.example`; package metadata/README; favicon, Open Graph metadata/ảnh; tài liệu `Doc/`.
- Hành vi trước/sau: lint từ còn cảnh báo thành sạch; available-slots từ nhận mọi field ID thành chỉ cho sân approved/active; backend root từ phụ thuộc session database thành route JSON không middleware; timezone mặc định từ UTC sang Asia/Ho_Chi_Minh.
- Điều không thay đổi: API contract, schema nghiệp vụ, dữ liệu Laragon và ba vai trò.

## Kiểm thử / xác minh

- `npm run lint`: đạt, không còn warning.
- `npm run build`: đạt; Vite build 1.994 module.
- `php artisan test`: đạt 13 test/32 assertion.
- `composer validate --no-check-publish`: đạt; lock file đã đồng bộ bằng `composer update --lock`.
- PHP lint: 90 file, 0 lỗi; route API v1: 40; `git check-ignore` xác nhận `Doc/` bị ignore.
- HTTP local: backend `/`, `/up`, frontend và `og.png` trả 200. API phụ thuộc dữ liệu không smoke lại trong ngày vì Laragon/MySQL đang tắt; luồng CSRF/login/customer/owner/admin đã trả 200 trong kiểm tra ngày trước.
- Việc chưa xác minh: browser E2E/visual QA không chạy vì người dùng không yêu cầu browser testing.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md`, `Doc/History/2026-09-01_2032_chuyen-api-react-layered.md`, mã hiện tại trong `frontend/src/`, `app/`, `routes/` và `tests/`.
- Nguồn ngoài: không bổ sung; tiếp tục áp dụng các nguồn Laravel, Sanctum, Vite, React Router và TanStack Query đã đăng ký trong `Doc/REFERENCES.md`.
- Quy trình frontend: skill `sites-building` của môi trường Codex, đọc ngày `2026-09-02`; giữ local theo yêu cầu dự án.
- Asset tạo mới: OpenAI ImageGen, `frontend/public/og.png`, tạo ngày `2026-09-02`, dùng làm social preview.

## Việc tiếp theo

- [x] Xử lý cảnh báo lint còn lại.
- [x] Chạy build/test/API smoke check cuối trong phạm vi database hiện có.
- [x] Cập nhật trạng thái tài liệu.
- [ ] Khi cần kiểm tra thủ công dữ liệu thật, bật Laragon/MySQL rồi chạy `npm run dev`.
