"""
gui.py
Giao diện đồ họa Tkinter để import hóa đơn XML vào SQL Server ECUS5VNACCS.

Cách dùng:
    python gui.py
"""

import logging
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

from xml_parser import parse_invoice

# ---------------------------------------------------------------------------
# Logger ghi ra TextHandler (sẽ gắn trong app)
# ---------------------------------------------------------------------------
logger = logging.getLogger(__name__)


class TextHandler(logging.Handler):
    """Logging handler ghi log ra tkinter Text widget."""

    def __init__(self, text_widget: tk.Text):
        super().__init__()
        self._widget = text_widget

    def emit(self, record):
        msg = self.format(record)
        level = record.levelname

        def _append():
            self._widget.configure(state="normal")
            tag = "INFO" if level == "INFO" else "ERROR"
            self._widget.insert(tk.END, msg + "\n", tag)
            self._widget.see(tk.END)
            self._widget.configure(state="disabled")

        self._widget.after(0, _append)


# ---------------------------------------------------------------------------
# Inline editor cho Treeview
# ---------------------------------------------------------------------------

class TreeviewEditor:
    """Cho phép double-click để sửa ô trong ttk.Treeview."""

    def __init__(self, tree: ttk.Treeview):
        self._tree = tree
        self._entry = None
        self._iid = None
        self._col = None
        tree.bind("<Double-1>", self._on_double_click)

    def _on_double_click(self, event):
        tree = self._tree
        region = tree.identify("region", event.x, event.y)
        if region != "cell":
            return
        col = tree.identify_column(event.x)
        iid = tree.identify_row(event.y)
        if not iid or not col:
            return

        col_idx = int(col.lstrip("#")) - 1
        columns = tree["columns"]
        if col_idx < 0 or col_idx >= len(columns):
            return

        # STT column (idx=0) không cho sửa
        if col_idx == 0:
            return

        self._iid = iid
        self._col = col_idx

        # Lấy bbox của ô
        bbox = tree.bbox(iid, col)
        if not bbox:
            return
        x, y, w, h = bbox

        # Lấy giá trị hiện tại
        values = list(tree.item(iid, "values"))
        cur_val = values[col_idx] if col_idx < len(values) else ""

        # Tạo Entry nổi
        if self._entry:
            self._entry.destroy()
        entry = tk.Entry(tree, font=("Arial", 10))
        entry.place(x=x, y=y, width=w, height=h)
        entry.insert(0, cur_val)
        entry.select_range(0, tk.END)
        entry.focus_set()
        self._entry = entry
        entry.bind("<Return>", self._save)
        entry.bind("<Escape>", self._cancel)
        entry.bind("<FocusOut>", self._save)

    def _save(self, event=None):
        if self._entry is None:
            return
        new_val = self._entry.get()
        self._entry.destroy()
        self._entry = None
        if self._iid is None:
            return
        values = list(self._tree.item(self._iid, "values"))
        while len(values) <= self._col:
            values.append("")
        values[self._col] = new_val
        self._tree.item(self._iid, values=values)

    def _cancel(self, event=None):
        if self._entry:
            self._entry.destroy()
            self._entry = None


# ---------------------------------------------------------------------------
# Ứng dụng chính
# ---------------------------------------------------------------------------

HEADER_FIELDS = [
    ("SoHoaDon", "Số hóa đơn"),
    ("NgayXuatHoaDon", "Ngày xuất hóa đơn"),
    ("MauSo", "Mẫu số"),
    ("KyHieu", "Ký hiệu"),
    ("BenBanTenDonVi", "Bên bán — Tên đơn vị"),
    ("BenBanMaSoThue", "Bên bán — Mã số thuế"),
    ("BenBanDiaChi", "Bên bán — Địa chỉ"),
    ("BenMuaTenDonVi", "Bên mua — Tên đơn vị"),
    ("BenMuaMaSoThue", "Bên mua — Mã số thuế"),
    ("BenMuaDiaChi", "Bên mua — Địa chỉ"),
    ("TongTienHang", "Tổng tiền hàng"),
    ("TienThueVat", "Tiền thuế VAT"),
    ("TongTienThanhToan", "Tổng tiền thanh toán"),
    ("DongTienThanhToan", "Đồng tiền thanh toán"),
    ("HinhThucThanhToan", "Hình thức thanh toán"),
]

ITEM_COLUMNS = [
    ("STT", "STT", 40),
    ("MaHang", "Mã hàng", 80),
    ("TenHang", "Tên hàng", 200),
    ("SoLuong", "Số lượng", 70),
    ("DonViTinh", "ĐVT", 60),
    ("DonGia", "Đơn giá", 90),
    ("ThanhTien", "Thành tiền", 100),
    ("VAT", "VAT%", 55),
    ("TienVat", "Tiền VAT", 90),
    ("GhiChu", "Ghi chú", 120),
]

FONT_MAIN = ("Arial", 10)
FONT_BOLD = ("Arial", 10, "bold")
FONT_TITLE = ("Arial", 11, "bold")

# Default value for isPXK (phiếu xuất kho)
_IS_PXK = 1

# Item fields that should be converted to float
_NUMERIC_ITEM_FIELDS = frozenset({"SoLuong", "DonGia", "ThanhTien", "VAT", "TienVat"})


class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("ECUS Invoice Importer")
        self.minsize(1200, 700)
        self.resizable(True, True)

        self._parsed = None  # Kết quả parse XML
        self._header_vars: dict[str, tk.StringVar] = {}

        self._build_ui()
        self._setup_logging()

    # ------------------------------------------------------------------
    # Xây dựng giao diện
    # ------------------------------------------------------------------

    def _build_ui(self):
        # Vùng 1 — Toolbar
        self._build_toolbar()

        # Pane chứa header + items
        main_pane = tk.PanedWindow(self, orient=tk.VERTICAL, sashrelief=tk.RAISED, sashwidth=5)
        main_pane.pack(fill=tk.BOTH, expand=True, padx=6, pady=4)

        # Vùng 2 — Header
        header_outer = ttk.LabelFrame(main_pane, text="Thông tin hóa đơn", padding=8)
        self._build_header_form(header_outer)
        main_pane.add(header_outer, minsize=180)

        # Vùng 3 — Items
        items_outer = ttk.LabelFrame(
            main_pane, text="Danh sách hàng hóa (có thể chỉnh sửa trực tiếp)", padding=8
        )
        self._build_items_table(items_outer)
        main_pane.add(items_outer, minsize=200)

        # Vùng 4 — Log
        self._build_log_area()

    def _build_toolbar(self):
        bar = tk.Frame(self, bg="#f0f0f0", relief=tk.RAISED, bd=1)
        bar.pack(fill=tk.X, padx=0, pady=0)

        btn_open = tk.Button(
            bar, text="📂 Chọn file XML", font=FONT_BOLD,
            command=self._browse_file, relief=tk.GROOVE, padx=8, pady=4
        )
        btn_open.pack(side=tk.LEFT, padx=6, pady=4)

        self._file_label = tk.Label(bar, text="(chưa chọn file)", font=FONT_MAIN,
                                    fg="#555555", bg="#f0f0f0", anchor="w")
        self._file_label.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=4)

        btn_clear = tk.Button(
            bar, text="🗑️ Xóa dữ liệu", font=FONT_MAIN,
            command=self._clear_all, relief=tk.GROOVE, padx=8, pady=4
        )
        btn_clear.pack(side=tk.RIGHT, padx=6, pady=4)

        btn_import = tk.Button(
            bar, text="💾 Import vào ECUS", font=FONT_BOLD,
            command=self._import_to_ecus,
            bg="#28a745", fg="white", activebackground="#218838",
            relief=tk.GROOVE, padx=10, pady=4
        )
        btn_import.pack(side=tk.RIGHT, padx=4, pady=4)

    def _build_header_form(self, parent):
        frame = tk.Frame(parent)
        frame.pack(fill=tk.BOTH, expand=True)

        half = len(HEADER_FIELDS) // 2 + len(HEADER_FIELDS) % 2
        for col_offset in range(2):
            frame.columnconfigure(col_offset * 2, weight=0, minsize=160)
            frame.columnconfigure(col_offset * 2 + 1, weight=1)

        for idx, (key, label) in enumerate(HEADER_FIELDS):
            col_grp = idx // half  # 0 = trái, 1 = phải
            row = idx % half
            col_label = col_grp * 2
            col_entry = col_grp * 2 + 1

            tk.Label(frame, text=label + ":", font=FONT_MAIN, anchor="e").grid(
                row=row, column=col_label, sticky="e", padx=(6, 2), pady=2
            )
            var = tk.StringVar()
            self._header_vars[key] = var
            entry = tk.Entry(frame, textvariable=var, font=FONT_MAIN)
            entry.grid(row=row, column=col_entry, sticky="ew", padx=(2, 12), pady=2)

    def _build_items_table(self, parent):
        cols = [c[0] for c in ITEM_COLUMNS]
        self._tree = ttk.Treeview(parent, columns=cols, show="headings", selectmode="browse")

        for key, heading, width in ITEM_COLUMNS:
            self._tree.heading(key, text=heading)
            self._tree.column(key, width=width, minwidth=40, anchor="center")

        # Scrollbars
        vsb = ttk.Scrollbar(parent, orient=tk.VERTICAL, command=self._tree.yview)
        hsb = ttk.Scrollbar(parent, orient=tk.HORIZONTAL, command=self._tree.xview)
        self._tree.configure(yscrollcommand=vsb.set, xscrollcommand=hsb.set)

        self._tree.grid(row=0, column=0, sticky="nsew")
        vsb.grid(row=0, column=1, sticky="ns")
        hsb.grid(row=1, column=0, sticky="ew")
        parent.rowconfigure(0, weight=1)
        parent.columnconfigure(0, weight=1)

        # Dòng đếm tổng
        self._items_count_label = tk.Label(parent, text="Tổng số dòng: 0", font=FONT_MAIN, anchor="w")
        self._items_count_label.grid(row=2, column=0, columnspan=2, sticky="w", pady=(4, 0))

        # Inline editing
        self._tree_editor = TreeviewEditor(self._tree)

    def _build_log_area(self):
        log_frame = ttk.LabelFrame(self, text="Log / Trạng thái", padding=4)
        log_frame.pack(fill=tk.X, padx=6, pady=(0, 6))

        self._log_text = tk.Text(
            log_frame, height=6, font=("Courier New", 9),
            state="disabled", bg="#1e1e1e", fg="#d4d4d4", relief=tk.FLAT
        )
        self._log_text.tag_configure("INFO", foreground="#4ec9b0")
        self._log_text.tag_configure("ERROR", foreground="#f48771")

        log_vsb = ttk.Scrollbar(log_frame, orient=tk.VERTICAL, command=self._log_text.yview)
        self._log_text.configure(yscrollcommand=log_vsb.set)
        self._log_text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        log_vsb.pack(side=tk.RIGHT, fill=tk.Y)

        # Status bar
        self._status_var = tk.StringVar(value="Sẵn sàng.")
        self._status_bar = tk.Label(
            self, textvariable=self._status_var, font=FONT_MAIN,
            anchor="w", relief=tk.SUNKEN, bg="#e0e0e0", padx=6
        )
        self._status_bar.pack(fill=tk.X, side=tk.BOTTOM)

    # ------------------------------------------------------------------
    # Logging setup
    # ------------------------------------------------------------------

    def _setup_logging(self):
        root_logger = logging.getLogger()
        root_logger.setLevel(logging.INFO)
        handler = TextHandler(self._log_text)
        handler.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(message)s", datefmt="%H:%M:%S"))
        root_logger.addHandler(handler)

    # ------------------------------------------------------------------
    # Các hành động
    # ------------------------------------------------------------------

    def _browse_file(self):
        path = filedialog.askopenfilename(
            title="Chọn file XML hóa đơn",
            filetypes=[("XML files", "*.xml"), ("All files", "*.*")],
        )
        if not path:
            return
        self._file_label.configure(text=path)
        self._load_xml(path)

    def _load_xml(self, path: str):
        try:
            parsed = parse_invoice(path)
        except FileNotFoundError:
            messagebox.showerror("Lỗi", f"Không tìm thấy file:\n{path}")
            return
        except Exception as exc:
            messagebox.showerror("Lỗi parse XML", str(exc))
            return

        self._parsed = parsed
        self._populate_header(parsed["header"])
        self._populate_items(parsed["items"])
        self._set_status(f"Đã tải file: {path}", ok=True)
        logger.info("Đã parse file XML: %s — %d dòng hàng hóa.", path, len(parsed["items"]))

    def _populate_header(self, header: dict):
        for key, var in self._header_vars.items():
            var.set(str(header.get(key, "") or ""))

    def _populate_items(self, items: list):
        # Xoá dữ liệu cũ
        for iid in self._tree.get_children():
            self._tree.delete(iid)

        col_keys = [c[0] for c in ITEM_COLUMNS]
        for idx, item in enumerate(items, start=1):
            row_vals = []
            for key in col_keys:
                if key == "STT":
                    row_vals.append(str(idx))
                else:
                    val = item.get(key, "")
                    row_vals.append("" if val is None else str(val))
            self._tree.insert("", tk.END, values=row_vals)

        self._items_count_label.configure(text=f"Tổng số dòng: {len(items)}")

    def _get_header_from_form(self) -> dict:
        """Đọc dữ liệu header từ form (đã chỉnh sửa)."""
        result = {}
        for key, var in self._header_vars.items():
            val = var.get().strip()
            result[key] = val if val else None
        # Giữ lại isPXK từ dữ liệu gốc
        if self._parsed:
            result["isPXK"] = self._parsed["header"].get("isPXK", _IS_PXK)
        return result

    def _get_items_from_table(self) -> list:
        """Đọc danh sách hàng hóa từ Treeview (đã chỉnh sửa)."""
        col_keys = [c[0] for c in ITEM_COLUMNS]
        items = []
        for iid in self._tree.get_children():
            values = self._tree.item(iid, "values")
            item = {}
            for i, key in enumerate(col_keys):
                if key == "STT":
                    continue
                raw = values[i] if i < len(values) else ""
                # Chuyển số
                if key in _NUMERIC_ITEM_FIELDS:
                    try:
                        item[key] = float(raw) if raw else None
                    except ValueError:
                        item[key] = None
                else:
                    item[key] = raw if raw else None
            items.append(item)
        return items

    def _import_to_ecus(self):
        if self._parsed is None:
            messagebox.showwarning("Chưa có dữ liệu", "Vui lòng chọn file XML trước.")
            return

        # Lấy dữ liệu đã chỉnh sửa từ form
        header = self._get_header_from_form()
        items = self._get_items_from_table()
        edited_parsed = {"header": header, "items": items}

        logger.info("--- Đang kết nối SQL Server ---")
        self._set_status("Đang kết nối SQL Server...", ok=True)
        self.update_idletasks()

        try:
            from ecus_importer import get_connection, import_invoice
            conn = get_connection()
        except Exception as exc:
            logger.error("Lỗi kết nối SQL Server: %s", exc)
            self._set_status(f"Lỗi kết nối: {exc}", ok=False)
            messagebox.showerror(
                "Lỗi kết nối",
                f"Không thể kết nối SQL Server:\n{exc}\n\n"
                "Kiểm tra:\n"
                "  - SQL Server (LOCAL)\\ECUSSQL2008 đang chạy\n"
                "  - Thông tin đăng nhập trong config.py\n"
                "  - Driver ODBC đã cài",
            )
            return

        logger.info("--- Đang import dữ liệu ---")
        self._set_status("Đang import dữ liệu...", ok=True)
        self.update_idletasks()

        try:
            result = import_invoice(conn, edited_parsed)
        except Exception as exc:
            logger.error("Lỗi khi import: %s", exc)
            self._set_status(f"Lỗi import: {exc}", ok=False)
            messagebox.showerror("Lỗi import", str(exc))
            return
        finally:
            conn.close()

        msg = (
            f"Import thành công!\n"
            f"DHOADONID: {result['hoadon_id']}\n"
            f"Số dòng hàng: {result['inserted_items']}"
        )
        logger.info("=== %s ===", msg.replace("\n", " | "))
        self._set_status(
            f"✅ Import thành công — DHOADONID={result['hoadon_id']}, dòng hàng={result['inserted_items']}",
            ok=True,
        )
        messagebox.showinfo("Thành công", msg)

    def _clear_all(self):
        self._parsed = None
        self._file_label.configure(text="(chưa chọn file)")
        for var in self._header_vars.values():
            var.set("")
        for iid in self._tree.get_children():
            self._tree.delete(iid)
        self._items_count_label.configure(text="Tổng số dòng: 0")
        self._log_text.configure(state="normal")
        self._log_text.delete("1.0", tk.END)
        self._log_text.configure(state="disabled")
        self._set_status("Đã xóa dữ liệu.", ok=True)

    def _set_status(self, msg: str, ok: bool = True):
        self._status_var.set(msg)
        self._status_bar.configure(bg="#c8e6c9" if ok else "#ffcdd2")


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    app = App()
    app.mainloop()


if __name__ == "__main__":
    main()
