# Hợp đồng API SportHub v1

- Base URL local: `http://127.0.0.1:8011/api/v1`
- Content type: `application/json`, trừ upload ảnh dùng `multipart/form-data`.
- Xác thực SPA: gọi `GET /sanctum/csrf-cookie`, sau đó gửi cookie session và XSRF token.

## Quy ước response

```json
{
  "message": "Thông báo tùy chọn",
  "data": {}
}
```

Collection phân trang có `data`, `links`, `meta`. Lỗi validation trả HTTP 422:

```json
{
  "message": "Dữ liệu gửi lên chưa hợp lệ.",
  "errors": {
    "field": ["Thông báo lỗi"]
  }
}
```

Mã thường dùng: 200 thành công, 201 đã tạo, 401 chưa đăng nhập, 403 sai quyền, 404 không tồn tại, 409 xung đột nghiệp vụ, 422 validation.

## Auth

| Method | Path | Request chính |
| --- | --- | --- |
| POST | `/auth/login` | `email`, `password`, `remember?` |
| POST | `/auth/register` | `name`, `email`, `password`, `password_confirmation`, `role`, thông tin liên hệ; owner thêm thông tin cơ sở |
| GET | `/auth/me` | auth |
| POST | `/auth/logout` | auth |

## Public catalog

| Method | Path | Query |
| --- | --- | --- |
| GET | `/home` | — |
| GET | `/categories` | — |
| GET | `/field-types` | — |
| GET | `/fields` | `keyword?`, `category?`, `type_id?`, `min_price?`, `max_price?`, `page?` |
| GET | `/fields/{slug}` | — |
| GET | `/fields/{field}/available-slots` | `date=YYYY-MM-DD` |

## Customer

Yêu cầu `auth:sanctum`, tài khoản active và role `customer`.

| Method | Path | Request chính |
| --- | --- | --- |
| GET | `/customer/bookings` | `page?` |
| POST | `/customer/bookings` | `sports_field_id`, `time_slot_id`, `booking_date`, `notes?` |
| PATCH | `/customer/bookings/{booking}/cancel` | `cancel_reason?` |
| POST | `/customer/reviews` | `booking_id`, `rating`, `comment` |
| GET | `/customer/profile` | — |
| PUT | `/customer/profile` | `name`, `email`, `phone?`, `address?`, `avatar?` |
| PUT | `/customer/profile/password` | `current_password`, `password`, `password_confirmation` |

## Field owner

Yêu cầu `auth:sanctum`, tài khoản active và role `field_owner`.

| Method | Path | Request chính |
| --- | --- | --- |
| GET | `/field-owner/dashboard` | — |
| GET | `/field-owner/fields` | `page?` |
| POST | `/field-owner/fields` | `field_type_id`, `name`, `address`, `description?`, `price_per_hour`, `latitude?`, `longitude?`, `images[]?` |
| PUT | `/field-owner/fields/{field}` | các trường cập nhật sân |
| PATCH | `/field-owner/fields/{field}/toggle` | — |
| GET | `/field-owner/bookings` | `status?`, `field_id?`, `date?`, `page?` |
| PATCH | `/field-owner/bookings/{booking}/status` | `status` |
| GET | `/field-owner/reviews` | `page?` |
| GET | `/field-owner/profile` | — |
| PUT | `/field-owner/profile` | thông tin cơ sở |

## Admin

Yêu cầu `auth:sanctum`, tài khoản active và role `admin`.

| Method | Path | Request chính |
| --- | --- | --- |
| GET | `/admin/dashboard` | — |
| GET | `/admin/users` | `keyword?`, `role?`, `status?`, `page?` |
| PATCH | `/admin/users/{user}/toggle` | — |
| GET | `/admin/field-owners` | `status?`, `page?` |
| PATCH | `/admin/field-owners/{profile}/verify` | `status=approved|rejected` |
| GET | `/admin/fields` | `status?`, `page?` |
| PATCH | `/admin/fields/{field}/verify` | `status=approved|rejected` |
| GET | `/admin/bookings` | bộ lọc booking |
| GET | `/admin/reviews` | `page?` |
| PATCH | `/admin/reviews/{review}/toggle` | — |
| GET | `/admin/categories` | — |
| POST | `/admin/categories` | `name`, `icon?`, `description?` |
| POST | `/admin/field-types` | `sport_category_id`, `name`, `description?` |

Nguồn sự thật cuối cùng cho method/path là `routes/api.php`; validation chi tiết là các lớp trong `app/Http/Requests/Api/`.
