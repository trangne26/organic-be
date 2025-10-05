# Hướng dẫn Upload Ảnh Sản Phẩm

## 🚀 Cài đặt

### 1. Tạo symbolic link cho storage
```bash
php artisan storage:link
```

### 2. Kiểm tra cấu trúc thư mục
```
storage/
├── app/
│   └── public/
│       └── products/  # Thư mục lưu ảnh sản phẩm
└── logs/
```

## 📝 Cách sử dụng

### 1. Test với Postman/Insomnia

**Request Type:** `POST`
**URL:** `http://localhost:8000/api/products`
**Headers:**
```
Authorization: Bearer YOUR_TOKEN
Content-Type: multipart/form-data
```

**Body (form-data):**
```
category_id: 1
name: Cà rốt hữu cơ
price: 35000
description: Cà rốt hữu cơ tươi ngon, giàu vitamin A
is_active: 1
images[]: [File 1 - carrot1.jpg]
images[]: [File 2 - carrot2.jpg]
primary_image_index: 0
```

### 2. Test với cURL

```bash
curl -X POST http://localhost:8000/api/products \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "category_id=1" \
  -F "name=Cà rốt hữu cơ" \
  -F "price=35000" \
  -F "description=Cà rốt hữu cơ tươi ngon" \
  -F "is_active=1" \
  -F "images[]=@/path/to/carrot1.jpg" \
  -F "images[]=@/path/to/carrot2.jpg" \
  -F "primary_image_index=0"
```

### 3. Frontend Integration

**HTML Form:**
```html
<form id="productForm" enctype="multipart/form-data">
    <div>
        <label>Tên sản phẩm:</label>
        <input type="text" name="name" required>
    </div>
    
    <div>
        <label>Giá:</label>
        <input type="number" name="price" required>
    </div>
    
    <div>
        <label>Mô tả:</label>
        <textarea name="description"></textarea>
    </div>
    
    <div>
        <label>Danh mục:</label>
        <select name="category_id">
            <option value="1">Rau củ quả hữu cơ</option>
            <option value="2">Trái cây hữu cơ</option>
        </select>
    </div>
    
    <div>
        <label>Ảnh sản phẩm:</label>
        <input type="file" name="images[]" multiple accept="image/*">
    </div>
    
    <div>
        <label>Ảnh chính (index):</label>
        <input type="number" name="primary_image_index" value="0" min="0">
    </div>
    
    <button type="submit">Tạo sản phẩm</button>
</form>
```

**JavaScript:**
```javascript
const form = document.getElementById('productForm');
const formData = new FormData(form);

fetch('/api/products', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
})
.then(response => response.json())
.then(data => {
    console.log('Success:', data);
    // Hiển thị thông báo thành công
})
.catch(error => {
    console.error('Error:', error);
    // Hiển thị thông báo lỗi
});
```

## ✅ Validation Rules

- **File type:** jpeg, png, jpg, gif, webp
- **File size:** Tối đa 2MB
- **Số lượng:** Tối đa 5 ảnh
- **Required:** Không bắt buộc

## 📁 Cấu trúc File

Sau khi upload, ảnh sẽ được lưu:
```
storage/app/public/products/
├── 1703123456_0.jpg
├── 1703123456_1.jpg
└── 1703123457_0.jpg
```

## 🔗 URL Ảnh

Ảnh sẽ có URL:
```
http://localhost:8000/storage/products/1703123456_0.jpg
```

## 📊 Response Format

**Success Response:**
```json
{
    "success": true,
    "message": "Sản phẩm đã được tạo thành công.",
    "data": {
        "id": 1,
        "name": "Cà rốt hữu cơ",
        "price": 35000,
        "description": "Cà rốt hữu cơ tươi ngon, giàu vitamin A",
        "is_active": true,
        "images": [
            {
                "id": 1,
                "url": "http://localhost:8000/storage/products/1703123456_0.jpg",
                "is_primary": true
            },
            {
                "id": 2,
                "url": "http://localhost:8000/storage/products/1703123456_1.jpg",
                "is_primary": false
            }
        ],
        "primary_image": "http://localhost:8000/storage/products/1703123456_0.jpg",
        "created_at": "2024-01-01T00:00:00.000000Z"
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "images.0": [
            "File phải là ảnh."
        ],
        "images.1": [
            "Kích thước ảnh không được vượt quá 2MB."
        ]
    }
}
```

## 🛡️ Security Features

1. **File Type Validation:** Chỉ cho phép file ảnh
2. **File Size Limit:** Tối đa 2MB
3. **Unique Filename:** Tên file được tạo tự động
4. **Secure Storage:** Lưu trong thư mục protected
5. **Input Sanitization:** Validate tất cả input

## 🔧 Troubleshooting

### Lỗi 404 khi truy cập ảnh
```bash
# Tạo lại symbolic link
php artisan storage:link
```

### Lỗi permission
```bash
# Cấp quyền cho thư mục storage
chmod -R 755 storage/
```

### Lỗi file không upload được
- Kiểm tra kích thước file (max 2MB)
- Kiểm tra định dạng file (jpeg, png, jpg, gif, webp)
- Kiểm tra quyền ghi thư mục storage

## 📈 Performance Tips

1. **Image Optimization:** Nén ảnh trước khi upload
2. **CDN:** Sử dụng CDN cho production
3. **Caching:** Cache ảnh đã xử lý
4. **Lazy Loading:** Load ảnh khi cần thiết

## 🧪 Testing

### Unit Test
```php
public function test_can_upload_product_images()
{
    $user = User::factory()->create(['is_admin' => true]);
    $category = Category::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/products', [
            'name' => 'Test Product',
            'price' => 100000,
            'category_id' => $category->id,
            'images' => [
                UploadedFile::fake()->image('test1.jpg'),
                UploadedFile::fake()->image('test2.jpg'),
            ],
            'primary_image_index' => 0,
        ]);
    
    $response->assertStatus(201);
    $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    $this->assertDatabaseHas('product_images', ['is_primary' => 1]);
}
```

## 🚀 Production Deployment

### Environment Variables
```env
FILESYSTEM_DISK=public
APP_URL=https://yourdomain.com
```

### Server Configuration
```nginx
location /storage {
    alias /path/to/storage/app/public;
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## 📋 Checklist

- [ ] Chạy `php artisan storage:link`
- [ ] Test upload với Postman
- [ ] Test với frontend
- [ ] Kiểm tra validation
- [ ] Test error cases
- [ ] Cấu hình production
- [ ] Setup CDN (optional)
- [ ] Monitor performance
