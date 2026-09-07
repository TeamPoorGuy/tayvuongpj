# SportHub Frontend

React + TypeScript SPA của SportHub. Ứng dụng gọi Laravel REST API ở `/api/v1`, dùng Laravel Sanctum cookie/CSRF cho đăng nhập và phân quyền ba vai trò.

## Chạy riêng frontend

```powershell
npm install
npm run dev
```

Vite mở tại `http://127.0.0.1:5173` và proxy `/api`, `/sanctum`, `/storage` sang Laravel tại `http://127.0.0.1:8011`.

## Kiểm tra

```powershell
npm run lint
npm run build
```

Các thư mục chính:

- `src/api/`: Axios client và chuẩn hóa lỗi.
- `src/context/`: trạng thái đăng nhập.
- `src/components/`: component dùng chung và bảo vệ route.
- `src/layouts/`: layout public/dashboard.
- `src/pages/`: trang public, customer, field owner và admin.
- `src/types/`: kiểu dữ liệu response API.
