"""
main.py
Công cụ import file XML hóa đơn điện tử Việt Nam vào SQL Server ECUS5VNACCS.

Cách dùng:
    python main.py <ten_file.xml>
    python main.py                  # sẽ hỏi tên file
"""

import logging
import sys

from xml_parser import parse_invoice
from ecus_importer import get_connection, import_invoice

# Cấu hình logging ra console
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
logger = logging.getLogger(__name__)


def main():
    # Xác định tên file XML
    if len(sys.argv) >= 2:
        xml_file = sys.argv[1]
    else:
        xml_file = input("Nhập tên file XML cần import: ").strip()

    if not xml_file:
        print("Lỗi: Vui lòng cung cấp tên file XML.")
        sys.exit(1)

    # Bước 1: Parse XML
    print(f"\n--- Đang đọc file XML: {xml_file} ---")
    try:
        parsed = parse_invoice(xml_file)
    except FileNotFoundError:
        print(f"Lỗi: Không tìm thấy file '{xml_file}'.")
        sys.exit(1)
    except Exception as exc:
        print(f"Lỗi khi đọc/parse file XML: {exc}")
        sys.exit(1)

    header = parsed["header"]
    items = parsed["items"]
    print(f"  Số hóa đơn  : {header.get('SoHoaDon', '')}")
    print(f"  Ngày lập    : {header.get('NgayXuatHoaDon', '')}")
    print(f"  Người bán   : {header.get('BenBanTenDonVi', '')}")
    print(f"  Người mua   : {header.get('BenMuaTenDonVi', '')}")
    print(f"  Số dòng hàng: {len(items)}")

    # Bước 2: Kết nối SQL Server
    print("\n--- Đang kết nối SQL Server ---")
    try:
        conn = get_connection()
    except Exception as exc:
        print(f"Lỗi kết nối SQL Server: {exc}")
        print(
            "Vui lòng kiểm tra:\n"
            "  - SQL Server (LOCAL)\\ECUSSQL2008 đang chạy\n"
            "  - Tên đăng nhập 'sa' và mật khẩu đúng\n"
            "  - Đã cài driver 'SQL Server' (pyodbc)"
        )
        sys.exit(1)

    # Bước 3: Import vào database
    print("\n--- Đang import dữ liệu ---")
    try:
        result = import_invoice(conn, parsed)
    except RuntimeError as exc:
        print(f"Lỗi cấu hình database: {exc}")
        sys.exit(1)
    except Exception as exc:
        print(f"Lỗi khi import dữ liệu: {exc}")
        sys.exit(1)
    finally:
        conn.close()

    # Kết quả
    print("\n=== Import thành công ===")
    print(f"  DHOADONID       : {result['hoadon_id']}")
    print(f"  Dòng hàng import: {result['inserted_items']}")


if __name__ == "__main__":
    main()
