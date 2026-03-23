"""
config.py
Cấu hình kết nối SQL Server cho ECUS5VNACCS.

Thông tin kết nối có thể ghi đè qua biến môi trường:
    ECUS_SERVER   — tên server  (mặc định: (LOCAL)\\ECUSSQL2008)
    ECUS_DATABASE — tên database (mặc định: ECUS5VNACCS)
    ECUS_USERNAME — tên đăng nhập (mặc định: sa)
    ECUS_PASSWORD — mật khẩu     (mặc định: rỗng — chỉ dùng cho môi trường dev/test)

Cảnh báo bảo mật: Tài khoản 'sa' với mật khẩu rỗng chỉ phù hợp với môi trường
phát triển cục bộ. Trong môi trường sản xuất, hãy đặt mật khẩu mạnh cho 'sa'
hoặc sử dụng tài khoản SQL riêng có quyền hạn tối thiểu, và cung cấp thông tin
xác thực qua biến môi trường ECUS_USERNAME / ECUS_PASSWORD.
"""

import os

DB_CONFIG = {
    "driver": "SQL Server",
    "server": os.environ.get("ECUS_SERVER", r"(LOCAL)\ECUSSQL2008"),
    "database": os.environ.get("ECUS_DATABASE", "ECUS5VNACCS"),
    "username": os.environ.get("ECUS_USERNAME", "sa"),
    # Mặc định rỗng theo đặc tả; ghi đè bằng biến môi trường ECUS_PASSWORD
    "password": os.environ.get("ECUS_PASSWORD", ""),
}

# Tên các bảng cần thao tác
TABLE_HOADON = "DHOADON"
TABLE_HOADON_CHITIET = "DHOADON_CHITIET"
