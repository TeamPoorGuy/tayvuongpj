# Hướng dẫn chạy dự án cho người mới

- Ngày giờ: `2026-09-07 14:32` (Asia/Ho_Chi_Minh)
- Trạng thái: `Đề xuất`
- Người/AI thực hiện: Codex
- Liên quan đến: Laragon MySQL, Laravel API và React Vite

## Mục tiêu

Hướng dẫn người mới chạy project hiện tại, phân biệt thiết lập một lần và thao tác hằng ngày.

## Bối cảnh và đánh giá trước khi làm

- Hiện trạng: `.env`, Composer vendor, root node_modules và frontend node_modules đều đã tồn tại; root `npm run dev` được cấu hình để chạy đồng thời Laravel port 8011 và React port 5173.
- Ràng buộc/rủi ro: không đọc hoặc ghi bí mật `.env`; Laragon/MySQL phải được bật trước khi API truy cập database.
- Quyết định và lý do: với máy hiện tại chỉ cần bật Laragon, mở terminal tại root và chạy một lệnh `npm run dev`; cung cấp cách hai terminal để dễ hiểu/xử lý lỗi.

## Thay đổi hoặc đề xuất

- Tệp đã sửa: chỉ bản ghi hướng dẫn cục bộ này.
- Hành vi trước/sau: không thay đổi mã hoặc dữ liệu.
- Điều không thay đổi: cấu hình môi trường, database và source code.

## Kiểm thử / xác minh

- Lệnh hoặc thao tác: đọc scripts trong `package.json`/`frontend/package.json`; dùng `Test-Path` để kiểm tra `.env`, vendor và dependencies.
- Kết quả: máy đã cài đủ thành phần của project; có thể chạy bằng `npm run dev` tại thư mục gốc.
- Việc chưa xác minh và lý do: chưa khởi động server vì người dùng yêu cầu hướng dẫn, không yêu cầu chạy thay.

## Nguồn tham khảo

- Nguồn nội bộ: `Doc/AI_PROJECT_CONTEXT.md`, `Doc/README.md` — kiến trúc và quy trình dự án.
- Nguồn nội bộ: `package.json`, `frontend/package.json` — lệnh dev và port frontend/backend.
- Nguồn nội bộ: trạng thái filesystem của `.env`, `vendor/`, `node_modules/`, `frontend/node_modules/` — mức độ cài đặt hiện tại, không đọc nội dung bí mật.

## Việc tiếp theo

- [ ] Bật Apache/MySQL trong Laragon.
- [ ] Chạy `npm run dev` ở thư mục gốc.
- [ ] Mở `http://127.0.0.1:5173` và xử lý lỗi nếu có.
