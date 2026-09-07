# Quy ước làm việc của dự án

Trước khi sửa mã, phân tích hoặc đưa ra đề xuất thay đổi có thể thực hiện được:

1. Đọc `Doc/AI_PROJECT_CONTEXT.md` và `Doc/README.md`.
2. Tạo **một tệp Markdown mới** trong `Doc/History/` theo mẫu
   `YYYY-MM-DD_HHMM_slug-ngan-gon.md`. Tệp phải dùng
   `Doc/TEMPLATES/CHANGE_RECORD_TEMPLATE.md` làm khung.
3. Ghi rõ mục tiêu, trạng thái trước/sau, tệp bị ảnh hưởng, quyết định và kiểm
   thử. Kể cả khi chỉ đề xuất mà chưa sửa mã, vẫn tạo bản ghi và đặt trạng thái
   là `Đề xuất`.
4. Mọi nguồn đã dùng phải được liệt kê trong mục `Nguồn tham khảo` của bản ghi;
   đồng thời bổ sung `Doc/REFERENCES.md` nếu đó là nguồn mới. Ghi URL đầy đủ,
   tiêu đề/nhà phát hành và ngày truy cập khi có nguồn bên ngoài. Với nguồn nội
   bộ, ghi đường dẫn tệp và commit liên quan nếu có.
5. Sau thay đổi làm `AI_PROJECT_CONTEXT.md` lỗi thời, cập nhật phần liên quan và
   dòng `Cập nhật lần cuối` của nó.

Không ghi bí mật từ `.env`, token, mật khẩu thật hoặc dữ liệu cá nhân vào tài liệu.
