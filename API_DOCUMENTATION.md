# API Documentation - Website Bán Thực Phẩm Hữu Cơ

## Tổng quan

API này được xây dựng bằng Laravel để phục vụ website bán thực phẩm hữu cơ. API hỗ trợ các chức năng quản lý sản phẩm, danh mục, đơn hàng và xác thực người dùng.

## Base URL

```
http://localhost:8000/api
```

## Authentication

API sử dụng Laravel Sanctum để xác thực. Sau khi đăng nhập thành công, bạn sẽ nhận được một token. Thêm token này vào header của các request cần xác thực:

```
Authorization: Bearer {your-token}
```

## Endpoints

### 1. Authentication

#### Đăng ký tài khoản
```http
POST /api/auth/register
```

**Request Body:**
```json
{
    "name": "Nguyễn Văn A",
    "email": "user@example.com",
    "phone": "0123456789",
    "address": "123 Đường ABC, Quận 1, TP.HCM",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Đăng ký thành công.",
    "data": {
        "user": {
            "id": 1,
            "name": "Nguyễn Văn A",
            "email": "user@example.com",
            "phone": "0123456789",
            "address": "123 Đường ABC, Quận 1, TP.HCM",
            "is_admin": false
        },
        "token": "1|abc123..."
    }
}
```

#### Đăng nhập
```http
POST /api/auth/login
```

**Request Body:**
```json
{
    "email": "user@example.com",
    "password": "password123",
    "remember_me": true
}
```

**Request Parameters:**
- `email` (required|string|email): Email đăng nhập
- `password` (required|string): Mật khẩu
- `remember_me` (optional|boolean): Nếu `true`, token sẽ có thời gian hết hạn là 30 ngày. Nếu `false` hoặc không có, token sẽ hết hạn sau 7 ngày.

**Response:**
```json
{
    "success": true,
    "message": "Đăng nhập thành công.",
    "data": {
        "user": {
            "id": 1,
            "name": "Nguyễn Văn A",
            "email": "user@example.com",
            "phone": "0123456789",
            "address": "123 Đường ABC, Quận 1, TP.HCM",
            "is_admin": false
        },
        "token": "1|abc123...",
        "expires_at": "2024-11-01T00:00:00.000000Z"
    }
}
```

#### Đăng xuất
```http
POST /api/auth/logout
```
*Yêu cầu: Bearer Token*

#### Lấy thông tin user hiện tại
```http
GET /api/auth/me
```
*Yêu cầu: Bearer Token*

#### Cập nhật profile
```http
PUT /api/auth/profile
```
*Yêu cầu: Bearer Token*

**Request Body:**
```json
{
    "name": "Nguyễn Văn B",
    "phone": "0987654321",
    "address": "456 Đường XYZ, Quận 2, TP.HCM"
}
```

#### Đổi mật khẩu
```http
PUT /api/auth/change-password
```
*Yêu cầu: Bearer Token*

**Request Body:**
```json
{
    "current_password": "password123",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

### 2. Categories (Danh mục)

#### Lấy danh sách danh mục
```http
GET /api/categories
```

**Query Parameters:**
- `active` (boolean): Lọc theo trạng thái hoạt động
- `search` (string): Tìm kiếm theo tên
- `page` (integer): Trang hiện tại (default: 1)
- `per_page` (integer): Số items per page (default: 15, min: 1, max: 100)

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Rau củ quả hữu cơ",
            "slug": "rau-cu-qua-huu-co",
            "description": "Các loại rau củ quả được trồng theo phương pháp hữu cơ...",
            "is_active": true,
            "products_count": 5,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 6
    }
}
```

#### Lấy chi tiết danh mục
```http
GET /api/categories/{id}
```

#### Tạo danh mục mới (Admin only)
```http
POST /api/categories
```
*Yêu cầu: Bearer Token + Admin*

**Request Body:**
```json
{
    "name": "Danh mục mới",
    "description": "Mô tả danh mục",
    "is_active": true
}
```

#### Cập nhật danh mục (Admin only)
```http
PUT /api/categories/{id}
```
*Yêu cầu: Bearer Token + Admin*

#### Xóa danh mục (Admin only)
```http
DELETE /api/categories/{id}
```
*Yêu cầu: Bearer Token + Admin*

### 3. Products (Sản phẩm)

#### Lấy danh sách sản phẩm
```http
GET /api/products
```

**Query Parameters:**
- `active` (boolean): Lọc theo trạng thái hoạt động
- `category_id` (integer): Lọc theo danh mục
- `search` (string): Tìm kiếm theo tên
- `min_price` (number): Giá tối thiểu
- `max_price` (number): Giá tối đa
- `sort_price` (string): Sắp xếp theo giá (asc/desc)
- `page` (integer): Trang hiện tại (default: 1)
- `per_page` (integer): Số items per page (default: 15, min: 1, max: 100)

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Cà chua hữu cơ",
            "slug": "ca-chua-huu-co",
            "price": 45000,
            "description": "Cà chua hữu cơ tươi ngon...",
            "is_active": true,
            "category": {
                "id": 1,
                "name": "Rau củ quả hữu cơ",
                "slug": "rau-cu-qua-huu-co"
            },
            "images": [
                {
                    "id": 1,
                    "url": "https://example.com/images/ca-chua-1.jpg",
                    "is_primary": true
                }
            ],
            "primary_image": "https://example.com/images/ca-chua-1.jpg",
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 8
    }
}
```

#### Lấy chi tiết sản phẩm
```http
GET /api/products/{id}
```

#### Tạo sản phẩm mới (Admin only)
```http
POST /api/products
```
*Yêu cầu: Bearer Token + Admin*

**Request Body:**
```json
{
    "category_id": 1,
    "name": "Sản phẩm mới",
    "price": 100000,
    "description": "Mô tả sản phẩm",
    "is_active": true,
    "images": [
        "https://example.com/image1.jpg",
        "https://example.com/image2.jpg"
    ],
    "primary_image_index": 0
}
```

#### Cập nhật sản phẩm (Admin only)
```http
PUT /api/products/{id}
```
*Yêu cầu: Bearer Token + Admin*

#### Xóa sản phẩm (Admin only)
```http
DELETE /api/products/{id}
```
*Yêu cầu: Bearer Token + Admin*

### 4. Orders (Đơn hàng)

#### Lấy danh sách đơn hàng
```http
GET /api/orders
```
*Yêu cầu: Bearer Token*

**Query Parameters:**
- `status` (string): Lọc theo trạng thái (pending, paid, shipped, delivered, cancelled)
- `from_date` (date): Từ ngày
- `to_date` (date): Đến ngày
- `user_id` (integer): ID người dùng (Admin only)
- `page` (integer): Trang hiện tại (default: 1)
- `per_page` (integer): Số items per page (default: 15, min: 1, max: 100)

#### Tạo đơn hàng mới
```http
POST /api/orders
```
*Yêu cầu: Bearer Token*

**Request Body:**
```json
{
    "items": [
        {
            "product_id": 1,
            "qty": 2
        },
        {
            "product_id": 2,
            "qty": 1
        }
    ],
    "payment_method": "COD",
    "shipping_name": "Nguyễn Văn A",
    "shipping_phone": "0123456789",
    "shipping_address": "123 Đường ABC, Quận 1, TP.HCM",
    "notes": "Giao hàng vào buổi chiều",
    "shipping_provider": "GHN"
}
```

**Validation Rules:**
- `items` (required|array|min:1): Mảng các sản phẩm trong đơn hàng
  - `items.*.product_id` (required|exists:products,id): ID sản phẩm phải tồn tại
  - `items.*.qty` (required|integer|min:1): Số lượng phải là số nguyên dương
- `payment_method` (required|string|in:COD,Momo,VNPAY): Phương thức thanh toán
- `shipping_name` (required|string|max:120): Tên người nhận
- `shipping_phone` (required|string|max:30): Số điện thoại người nhận
- `shipping_address` (required|string|max:255): Địa chỉ giao hàng
- `notes` (nullable|string): Ghi chú đơn hàng
- `shipping_provider` (nullable|string|in:GHN,GHTK): Nhà vận chuyển (không bắt buộc)

**Lưu ý:**
- API sẽ tự động tính tổng tiền dựa trên giá sản phẩm và số lượng
- Đơn hàng sẽ được tạo với trạng thái "pending"
- Tự động tạo bản ghi payment và shipment với trạng thái "pending"
- `shipping_provider` có thể bỏ trống, admin sẽ chọn nhà vận chuyển phù hợp sau
- Phí vận chuyển sẽ được tính và cập nhật sau khi chọn provider

**Response:**
```json
{
    "success": true,
    "message": "Đơn hàng đã được tạo thành công.",
    "data": {
        "id": 1,
        "status": "pending",
        "total": 115000,
        "user": {
            "id": 1,
            "name": "Nguyễn Văn A",
            "email": "user@example.com"
        },
        "items": [
            {
                "id": 1,
                "product": {
                    "id": 1,
                    "name": "Cà chua hữu cơ",
                    "price": 45000
                },
                "qty": 2,
                "unit_price": 45000,
                "line_total": 90000
            }
        ],
        "payments": [
            {
                "id": 1,
                "method": "COD",
                "amount": 115000,
                "status": "pending"
            }
        ],
        "shipments": [
            {
                "id": 1,
                "provider": "GHN",
                "status": "pending"
            }
        ],
        "created_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

#### Lấy chi tiết đơn hàng
```http
GET /api/orders/{id}
```
*Yêu cầu: Bearer Token*

#### Cập nhật trạng thái đơn hàng
```http
PUT /api/orders/{id}/status
```
*Yêu cầu: Bearer Token*

**Request Body:**
```json
{
    "status": "paid"
}
```

#### Xóa đơn hàng
```http
DELETE /api/orders/{id}
```
*Yêu cầu: Bearer Token*

## Error Responses

API trả về các mã lỗi HTTP chuẩn:

- `200` - Thành công
- `201` - Tạo thành công
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

**Error Response Format:**
```json
{
    "success": false,
    "message": "Thông báo lỗi",
    "errors": {
        "field": ["Lỗi validation"]
    }
}
```

## Cài đặt và Chạy

### 1. Cài đặt dependencies
```bash
composer install
```

### 2. Cấu hình môi trường
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Cấu hình database trong file .env
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=organic_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Chạy migrations và seeders
```bash
php artisan migrate
php artisan db:seed
```

### 5. Chạy server
```bash
php artisan serve
```

## Tài khoản mặc định

Sau khi chạy seeders, bạn có thể sử dụng các tài khoản sau:

**Admin:**
- Email: admin@organic.com
- Password: password

**User thường:**
- Email: user1@example.com
- Password: password

## Ghi chú

- Tất cả các endpoint cần xác thực đều yêu cầu Bearer Token
- Các endpoint chỉ dành cho Admin có ghi chú "Admin only"
- API hỗ trợ pagination cho các danh sách
- Tất cả response đều có format JSON với trường `success` để xác định trạng thái
