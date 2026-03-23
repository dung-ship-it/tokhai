"""
ecus_importer.py
Kết nối SQL Server ECUS5VNACCS và import dữ liệu hóa đơn vào DHOADON / DHOADON_CHITIET.
"""

import logging

import pyodbc

from config import DB_CONFIG, TABLE_HOADON, TABLE_HOADON_CHITIET

logger = logging.getLogger(__name__)


# ---------------------------------------------------------------------------
# Kết nối
# ---------------------------------------------------------------------------

def get_connection() -> pyodbc.Connection:
    """Tạo và trả về kết nối pyodbc đến SQL Server ECUS5VNACCS."""
    conn_str = (
        f"DRIVER={{{DB_CONFIG['driver']}}};"
        f"SERVER={DB_CONFIG['server']};"
        f"DATABASE={DB_CONFIG['database']};"
        f"UID={DB_CONFIG['username']};"
        f"PWD={DB_CONFIG['password']};"
    )
    logger.info("Đang kết nối SQL Server: %s / %s", DB_CONFIG["server"], DB_CONFIG["database"])
    conn = pyodbc.connect(conn_str, timeout=10)
    logger.info("Kết nối thành công.")
    return conn


# ---------------------------------------------------------------------------
# Kiểm tra bảng
# ---------------------------------------------------------------------------

def check_tables(conn: pyodbc.Connection) -> bool:
    """
    Kiểm tra xem các bảng DHOADON và DHOADON_CHITIET có tồn tại không.

    Returns:
        True nếu cả hai bảng đều tồn tại, False nếu thiếu bảng.
    """
    cursor = conn.cursor()
    missing = []
    for table in (TABLE_HOADON, TABLE_HOADON_CHITIET):
        cursor.execute(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES "
            "WHERE TABLE_NAME = ?",
            table,
        )
        if cursor.fetchone()[0] == 0:
            missing.append(table)

    if missing:
        logger.error("Không tìm thấy bảng: %s", ", ".join(missing))
        return False

    logger.info("Đã xác nhận các bảng: %s, %s", TABLE_HOADON, TABLE_HOADON_CHITIET)
    return True


# ---------------------------------------------------------------------------
# Lấy danh sách cột thực tế
# ---------------------------------------------------------------------------

def get_dhoadon_columns(conn: pyodbc.Connection) -> list[str]:
    """Trả về danh sách tên cột thực tế của bảng DHOADON."""
    return _get_columns(conn, TABLE_HOADON)


def get_dhoadon_chitiet_columns(conn: pyodbc.Connection) -> list[str]:
    """Trả về danh sách tên cột thực tế của bảng DHOADON_CHITIET."""
    return _get_columns(conn, TABLE_HOADON_CHITIET)


def _get_columns(conn: pyodbc.Connection, table: str) -> list[str]:
    cursor = conn.cursor()
    cursor.execute(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
        "WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
        table,
    )
    cols = [row[0] for row in cursor.fetchall()]
    logger.info("Bảng %s — %d cột: %s", table, len(cols), cols)
    return cols


# ---------------------------------------------------------------------------
# Insert hóa đơn
# ---------------------------------------------------------------------------

def insert_hoadon(conn: pyodbc.Connection, header_data: dict) -> int:
    """
    Insert một bản ghi vào bảng DHOADON.

    Args:
        conn: Kết nối SQL Server đang mở.
        header_data: dict các trường header từ xml_parser.

    Returns:
        DHOADONID vừa được tạo.
    """
    db_cols = get_dhoadon_columns(conn)
    # Bỏ cột identity (khóa chính tự tăng)
    identity_col = "DHOADONID"
    insertable = [c for c in db_cols if c != identity_col]

    # Chỉ giữ lại các trường có trong DB
    row = {k: v for k, v in header_data.items() if k in insertable}
    if not row:
        raise ValueError("Không có trường nào từ header_data khớp với cột trong bảng DHOADON.")

    cols_str = ", ".join(row.keys())
    placeholders = ", ".join("?" for _ in row)
    sql = f"INSERT INTO {TABLE_HOADON} ({cols_str}) VALUES ({placeholders}); SELECT SCOPE_IDENTITY();"

    logger.info("INSERT DHOADON — %d trường: %s", len(row), list(row.keys()))
    cursor = conn.cursor()
    cursor.execute(sql, list(row.values()))
    new_id = int(cursor.fetchone()[0])
    logger.info("Đã insert DHOADON — DHOADONID = %d", new_id)
    return new_id


# ---------------------------------------------------------------------------
# Insert chi tiết hóa đơn
# ---------------------------------------------------------------------------

def insert_hoadon_chitiet(conn: pyodbc.Connection, hoadon_id: int, items: list[dict]) -> int:
    """
    Insert các dòng hàng hóa vào bảng DHOADON_CHITIET.

    Args:
        conn: Kết nối SQL Server đang mở.
        hoadon_id: DHOADONID vừa insert.
        items: list các dict từng dòng hàng hóa.

    Returns:
        Số dòng đã insert thành công.
    """
    if not items:
        logger.warning("Không có dòng hàng hóa nào để insert.")
        return 0

    db_cols = get_dhoadon_chitiet_columns(conn)
    identity_col = "DHOADON_CHITIETID"
    fk_col = "DHOADONID"
    insertable = [c for c in db_cols if c != identity_col]

    inserted = 0
    cursor = conn.cursor()
    for idx, item in enumerate(items, start=1):
        # Gắn khóa ngoại
        item_with_fk = {fk_col: hoadon_id, **item}
        row = {k: v for k, v in item_with_fk.items() if k in insertable}

        cols_str = ", ".join(row.keys())
        placeholders = ", ".join("?" for _ in row)
        sql = f"INSERT INTO {TABLE_HOADON_CHITIET} ({cols_str}) VALUES ({placeholders})"

        logger.debug("INSERT DHOADON_CHITIET dòng %d — %s", idx, row)
        cursor.execute(sql, list(row.values()))
        inserted += 1

    logger.info("Đã insert %d dòng vào DHOADON_CHITIET.", inserted)
    return inserted


# ---------------------------------------------------------------------------
# Hàm tổng hợp: import toàn bộ hóa đơn
# ---------------------------------------------------------------------------

def import_invoice(conn: pyodbc.Connection, parsed: dict) -> dict:
    """
    Import một hóa đơn (header + items) vào database trong một transaction.

    Args:
        conn: Kết nối SQL Server đang mở.
        parsed: dict trả về từ xml_parser.parse_invoice().

    Returns:
        dict {'hoadon_id': int, 'inserted_items': int}

    Raises:
        Exception: rollback nếu có lỗi.
    """
    if not check_tables(conn):
        raise RuntimeError("Một hoặc nhiều bảng cần thiết không tồn tại trong database.")

    conn.autocommit = False
    try:
        hoadon_id = insert_hoadon(conn, parsed["header"])
        inserted_items = insert_hoadon_chitiet(conn, hoadon_id, parsed["items"])
        conn.commit()
        logger.info("Transaction commit thành công.")
        return {"hoadon_id": hoadon_id, "inserted_items": inserted_items}
    except Exception:
        conn.rollback()
        logger.error("Lỗi — đã rollback transaction.")
        raise
