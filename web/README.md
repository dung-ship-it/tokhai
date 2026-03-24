# ECUS Tờ khai — Web PHP

Bộ khung PHP cho phép upload file XML hóa đơn điện tử và truyền tờ khai thẳng vào SQL Server ECUS5VNACCS từ bất kỳ máy tính nào trên mạng LAN.

## Kiến trúc

```
[Máy tính bất kỳ - Browser]
        ↓ upload XML qua HTTP
[Máy chủ - Apache/Nginx + PHP]   ← cài trên cùng máy server với ECUS
        ↓ sqlsrv / PDO_SQLSRV
[SQL Server ECUS5VNACCS]          ← instance: (LOCAL)\ECUSSQL2008
```

## Cấu trúc thư mục

```
web/
├── index.php          # Trang chủ — upload XML
├── upload.php         # Parse XML, hiển thị form tờ khai
├── submit.php         # INSERT vào ECUS (DTOKHAIMD + DHANGMDDK)
├── config.php         # Cấu hình kết nối DB
├── XmlParser.php      # Parse XML hóa đơn điện tử VN
├── EcusImporter.php   # Kết nối + INSERT vào ECUS
├── assets/
│   └── style.css      # CSS giao diện
└── uploads/           # Thư mục lưu file XML tạm (tự tạo)
```

## Hướng dẫn cài đặt

### Bước 1 — Cài XAMPP (hoặc WAMP / PHP standalone)

Tải và cài XAMPP tại: https://www.apachefriends.org

### Bước 2 — Cài Microsoft PHP Driver for SQL Server

1. Vào trang tải driver: https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
2. Tải file `.dll` phù hợp với phiên bản PHP và kiến trúc (x64/x86):
   - Ví dụ PHP 8.3, thread-safe, 64-bit → `php_sqlsrv_83_ts_x64.dll` và `php_pdo_sqlsrv_83_ts_x64.dll`
3. Copy 2 file `.dll` vào thư mục `ext` của PHP:
   ```
   C:\xampp\php\ext\
   ```
4. Mở `C:\xampp\php\php.ini`, thêm 2 dòng sau (điều chỉnh tên file cho đúng phiên bản):
   ```ini
   extension=php_sqlsrv_83_ts_x64
   extension=php_pdo_sqlsrv_83_ts_x64
   ```
5. Restart Apache trong XAMPP Control Panel.
6. Kiểm tra: mở `http://localhost/phpinfo.php`, tìm mục `sqlsrv` — nếu có là thành công.

### Bước 3 — Copy thư mục web vào htdocs

```
Copy thư mục  web/  vào  C:\xampp\htdocs\tokhai\
```

Hoặc tạo Virtual Host trỏ vào thư mục `web/`.

### Bước 4 — Cấu hình kết nối DB

Mở file `config.php` và sửa thông tin kết nối nếu cần:

```php
define('DB_SERVER', '(LOCAL)\\ECUSSQL2008');  // Tên SQL Server instance
define('DB_NAME',   'ECUS5VNACCS');            // Tên database
define('DB_USER',   'sa');                     // Tên đăng nhập
define('DB_PASS',   'your_password_here');     // Mật khẩu
```

Hoặc đặt biến môi trường (an toàn hơn):
```
ECUS_SERVER=192.168.1.100\ECUSSQL2008
ECUS_DATABASE=ECUS5VNACCS
ECUS_USERNAME=sa
ECUS_PASSWORD=your_password
```

### Bước 5 — Mở SQL Server cho kết nối TCP/IP

1. Mở **SQL Server Configuration Manager**
2. Vào `SQL Server Network Configuration → Protocols for ECUSSQL2008`
3. Bật **TCP/IP** (double-click → Enable)
4. Restart service SQL Server

### Bước 6 — Mở firewall (nếu máy khác truy cập qua mạng LAN)

```
Windows Defender Firewall → Advanced Settings → Inbound Rules → New Rule
→ Port → TCP → 80 → Allow → (đặt tên, ví dụ: "Apache HTTP")
```

Nếu SQL Server nằm trên máy khác, mở thêm port 1433 cho SQL Server.

## Truy cập từ máy khác trong mạng LAN

```
http://[IP_CỦA_MÁY_SERVER]/tokhai/
```

Ví dụ: `http://192.168.1.100/tokhai/`

Lấy IP máy server bằng lệnh: `ipconfig` (Windows) hoặc `ip addr` (Linux).

## Luồng sử dụng

1. Mở trình duyệt, vào địa chỉ web
2. Chọn file XML hóa đơn điện tử, nhấn **"Upload & Xem trước"**
3. Kiểm tra và chỉnh sửa thông tin trên form tờ khai
4. Nhấn **"Lưu vào ECUS"** → hệ thống INSERT vào `DTOKHAIMD` và `DHANGMDDK`
5. Xem kết quả (ID tờ khai và số dòng hàng đã lưu)

## Yêu cầu hệ thống

| Thành phần | Yêu cầu |
|---|---|
| PHP | >= 7.4 (khuyến nghị 8.x) |
| Extension | `php_sqlsrv` hoặc `php_pdo_sqlsrv` |
| Web server | Apache, Nginx, hoặc PHP built-in server |
| SQL Server | 2008 hoặc mới hơn |
| OS | Windows (ECUS thường chạy trên Windows) |

## Bảo mật

- Mật khẩu DB **không nên** để trống trong môi trường production.
- Chỉ chấp nhận file `.xml` khi upload.
- Sử dụng prepared statements cho tất cả câu lệnh SQL.
- Stack trace không bao giờ hiển thị ra browser; ghi vào error log.
- Thư mục `uploads/` chứa file tạm — có thể thêm file `.htaccess` để chặn truy cập trực tiếp:
  ```apache
  Deny from all
  ```

## Troubleshooting

| Vấn đề | Hướng xử lý |
|---|---|
| `sqlsrv_connect` không tồn tại | Kiểm tra đã cài extension và restart Apache chưa |
| Lỗi kết nối DB | Kiểm tra TCP/IP bật trên SQL Server, thông tin trong `config.php` |
| Lỗi "Bảng DTOKHAIMD không tồn tại" | Kiểm tra tên database và quyền tài khoản `sa` |
| File upload thất bại | Kiểm tra quyền ghi thư mục `uploads/` và `upload_max_filesize` trong `php.ini` |
| Lỗi truncate | Đã được xử lý tự động; xem error log nếu vẫn lỗi |
