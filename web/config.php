<?php
/**
 * config.php
 * Cấu hình kết nối SQL Server cho ECUS5VNACCS.
 *
 * Thông tin kết nối có thể ghi đè qua biến môi trường:
 *   ECUS_SERVER   — tên server  (mặc định: (LOCAL)\ECUSSQL2008)
 *   ECUS_DATABASE — tên database (mặc định: ECUS5VNACCS)
 *   ECUS_USERNAME — tên đăng nhập (mặc định: sa)
 *   ECUS_PASSWORD — mật khẩu     (mặc định: rỗng)
 *
 * Cài đặt PHP extension cần thiết:
 *   1. Tải Microsoft PHP Driver for SQL Server:
 *      https://learn.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server
 *   2. Copy file .dll vào thư mục ext của PHP
 *      (ví dụ: C:\xampp\php\ext\php_sqlsrv_83_ts_x64.dll)
 *   3. Thêm vào php.ini:
 *      extension=php_sqlsrv_83_ts_x64
 *      extension=php_pdo_sqlsrv_83_ts_x64
 *   4. Restart Apache / web server
 */

define('DB_SERVER',   getenv('ECUS_SERVER')   ?: '(LOCAL)\\ECUSSQL2008');
define('DB_NAME',     getenv('ECUS_DATABASE') ?: 'ECUS5VNACCS');
define('DB_USER',     getenv('ECUS_USERNAME') ?: 'sa');
define('DB_PASS',     getenv('ECUS_PASSWORD') ?: '123456');

// Tên các bảng cần thao tác
define('TABLE_TOKHAI', 'DTOKHAIMD');
define('TABLE_HANG',   'DHANGMDDK');

// Thư mục lưu file XML tạm (relative so với web/)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
