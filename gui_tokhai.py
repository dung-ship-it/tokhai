"""
gui_tokhai.py
Giao diện Python giống form tờ khai hải quan ECUS — Tờ khai xuất khẩu.

Cách chạy:
    python gui_tokhai.py
"""

import json
import logging
import os
import tkinter as tk
from tkinter import filedialog, messagebox, ttk

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Hằng số
# ---------------------------------------------------------------------------
FONT = ("Arial", 9)
FONT_BOLD = ("Arial", 9, "bold")
MAPPING_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "mapping_config.json")

DEFAULT_MAPPING = {
    "NguoiXuatKhau_Ma": {"xml_field": "BenBanMaSoThue", "default": ""},
    "NguoiXuatKhau_Ten": {"xml_field": "BenBanTenDonVi", "default": ""},
    "NguoiXuatKhau_DiaChi": {"xml_field": "BenBanDiaChi", "default": ""},
    "NguoiXuatKhau_MaBuuChinh": {"xml_field": "BenBanDienThoai", "default": ""},
    "NguoiNhapKhau_Ma": {"xml_field": "BenMuaMaSoThue", "default": ""},
    "NguoiNhapKhau_Ten": {"xml_field": "BenMuaTenDonVi", "default": ""},
    "NguoiNhapKhau_DiaChi": {"xml_field": "BenMuaDiaChi", "default": ""},
    "SoHoaDon": {"xml_field": "SoHoaDon", "default": ""},
    "NgayPhatHanh": {"xml_field": "NgayXuatHoaDon", "default": ""},
    "TongTriGiaHoaDon": {"xml_field": "TongTienThanhToan", "default": ""},
    "MaDongTienCuaHoaDon": {"xml_field": "DongTienThanhToan", "default": "VND"},
    "PhuongThucThanhToan": {"xml_field": "HinhThucThanhToan", "default": ""},
    "SoHopDong": {"xml_field": "SoHopDong", "default": ""},
    "NgayHopDong": {"xml_field": "NgayHopDong", "default": ""},
    "CoQuanHaiQuan": {"xml_field": "", "default": "01B1"},
    "MaBoPhanXuLyToKhai": {"xml_field": "", "default": "00"},
}

# Cột Treeview cho Tab 2
TREE_COLUMNS = [
    ("STT", 40),
    ("MaHang", 80),
    ("TenHang", 200),
    ("MaHS", 80),
    ("XuatXu", 60),
    ("Luong", 70),
    ("DonViTinh", 70),
    ("Luong2", 70),
    ("DonViTinh2", 70),
    ("DonGiaHoaDon", 100),
    ("TriGiaHoaDon", 100),
]


# ---------------------------------------------------------------------------
# Helper: load / save mapping
# ---------------------------------------------------------------------------

def _load_mapping() -> dict:
    if os.path.exists(MAPPING_FILE):
        try:
            with open(MAPPING_FILE, encoding="utf-8") as f:
                return json.load(f)
        except Exception as exc:
            logger.warning("Không đọc được mapping_config.json: %s", exc)
    return dict(DEFAULT_MAPPING)


def _save_mapping(mapping: dict):
    with open(MAPPING_FILE, "w", encoding="utf-8") as f:
        json.dump(mapping, f, ensure_ascii=False, indent=2)


# ---------------------------------------------------------------------------
# Cửa sổ Cấu hình Mapping
# ---------------------------------------------------------------------------

class MappingWindow(tk.Toplevel):
    def __init__(self, parent, mapping: dict, on_save):
        super().__init__(parent)
        self.title("Cấu hình Mapping XML → Tờ khai")
        self.geometry("680x500")
        self.resizable(True, True)
        self.grab_set()
        self._mapping = dict(mapping)
        self._on_save = on_save
        self._rows: list[dict] = []
        self._build()

    def _build(self):
        self.columnconfigure(0, weight=1)
        self.rowconfigure(1, weight=1)

        # Header
        hdr = tk.Frame(self, bg="#f0f0f0", padx=6, pady=4)
        hdr.grid(row=0, column=0, sticky="ew")
        for col, text, width in [
            (0, "Trường tờ khai", 20),
            (1, "Trường XML nguồn", 18),
            (2, "Giá trị mặc định", 18),
        ]:
            tk.Label(hdr, text=text, font=FONT_BOLD, bg="#f0f0f0", width=width, anchor="w").grid(
                row=0, column=col, padx=4
            )

        # Scrollable body
        container = tk.Frame(self)
        container.grid(row=1, column=0, sticky="nsew")
        container.columnconfigure(0, weight=1)
        container.rowconfigure(0, weight=1)

        canvas = tk.Canvas(container, highlightthickness=0)
        canvas.grid(row=0, column=0, sticky="nsew")
        sb = ttk.Scrollbar(container, orient="vertical", command=canvas.yview)
        sb.grid(row=0, column=1, sticky="ns")
        canvas.configure(yscrollcommand=sb.set)

        inner = tk.Frame(canvas)
        canvas_win = canvas.create_window((0, 0), window=inner, anchor="nw")

        def _on_configure(evt):
            canvas.configure(scrollregion=canvas.bbox("all"))
            canvas.itemconfig(canvas_win, width=canvas.winfo_width())

        inner.bind("<Configure>", _on_configure)
        canvas.bind("<Configure>", lambda e: canvas.itemconfig(canvas_win, width=e.width))

        for key, cfg in self._mapping.items():
            row_frame = tk.Frame(inner)
            row_frame.pack(fill="x", padx=4, pady=1)
            tk.Label(row_frame, text=key, font=FONT, width=28, anchor="w").pack(side="left")
            xml_var = tk.StringVar(value=cfg.get("xml_field", ""))
            def_var = tk.StringVar(value=cfg.get("default", ""))
            tk.Entry(row_frame, textvariable=xml_var, font=FONT, width=22).pack(side="left", padx=4)
            tk.Entry(row_frame, textvariable=def_var, font=FONT, width=22).pack(side="left", padx=4)
            self._rows.append({"key": key, "xml_var": xml_var, "def_var": def_var})

        # Buttons
        btn_frame = tk.Frame(self, pady=6)
        btn_frame.grid(row=2, column=0)
        tk.Button(btn_frame, text="💾 Lưu cấu hình", font=FONT, command=self._save).pack(
            side="left", padx=8
        )
        tk.Button(btn_frame, text="↺ Reset về mặc định", font=FONT, command=self._reset).pack(
            side="left", padx=8
        )
        tk.Button(btn_frame, text="Đóng", font=FONT, command=self.destroy).pack(side="left", padx=8)

    def _save(self):
        new_mapping = {}
        for r in self._rows:
            new_mapping[r["key"]] = {
                "xml_field": r["xml_var"].get(),
                "default": r["def_var"].get(),
            }
        _save_mapping(new_mapping)
        self._on_save(new_mapping)
        messagebox.showinfo("Thành công", "Đã lưu cấu hình mapping.", parent=self)

    def _reset(self):
        if messagebox.askyesno("Xác nhận", "Reset về mapping mặc định?", parent=self):
            for r in self._rows:
                key = r["key"]
                if key in DEFAULT_MAPPING:
                    r["xml_var"].set(DEFAULT_MAPPING[key]["xml_field"])
                    r["def_var"].set(DEFAULT_MAPPING[key]["default"])


# ---------------------------------------------------------------------------
# Dialog sửa dòng hàng hóa
# ---------------------------------------------------------------------------

class ItemEditDialog(tk.Toplevel):
    def __init__(self, parent, values: dict, on_ok):
        super().__init__(parent)
        self.title("Sửa dòng hàng hóa")
        self.resizable(False, False)
        self.grab_set()
        self._on_ok = on_ok
        self._vars = {}
        fields = [
            ("MaHang", "Mã hàng"),
            ("TenHang", "Tên hàng"),
            ("MaHS", "Mã HS"),
            ("XuatXu", "Xuất xứ"),
            ("Luong", "Lượng"),
            ("DonViTinh", "Đơn vị tính"),
            ("Luong2", "Lượng 2"),
            ("DonViTinh2", "ĐVT 2"),
            ("DonGiaHoaDon", "Đơn giá HĐ"),
            ("TriGiaHoaDon", "Trị giá HĐ"),
        ]
        for i, (key, label) in enumerate(fields):
            tk.Label(self, text=label, font=FONT, anchor="w", width=14).grid(
                row=i, column=0, padx=6, pady=2, sticky="w"
            )
            var = tk.StringVar(value=str(values.get(key, "")))
            tk.Entry(self, textvariable=var, font=FONT, width=32).grid(
                row=i, column=1, padx=6, pady=2
            )
            self._vars[key] = var

        btn_frame = tk.Frame(self)
        btn_frame.grid(row=len(fields), column=0, columnspan=2, pady=8)
        tk.Button(btn_frame, text="OK", font=FONT, width=10, command=self._ok).pack(
            side="left", padx=8
        )
        tk.Button(btn_frame, text="Huỷ", font=FONT, width=10, command=self.destroy).pack(
            side="left", padx=8
        )

    def _ok(self):
        self._on_ok({k: v.get() for k, v in self._vars.items()})
        self.destroy()


# ---------------------------------------------------------------------------
# Cửa sổ chính
# ---------------------------------------------------------------------------

class ToKhaiApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("ECUS - Tờ Khai Xuất Khẩu")
        self.geometry("1100x900")
        self.resizable(True, True)
        self.option_add("*Font", FONT)

        self._xml_file = ""
        self._parsed = None
        self._mapping = _load_mapping()
        self._fields: dict[str, tk.Variable] = {}  # tên field → StringVar / IntVar
        self._item_rows: list[dict] = []  # danh sách dict mỗi dòng hàng hóa

        self._build_toolbar()
        self._build_notebook()

    # ------------------------------------------------------------------
    # Toolbar
    # ------------------------------------------------------------------

    def _build_toolbar(self):
        bar = tk.Frame(self, bg="#dde6f0", padx=6, pady=4)
        bar.pack(fill="x", side="top")

        tk.Button(
            bar, text="📂 Mở file XML", font=FONT, command=self._open_xml
        ).pack(side="left", padx=4)

        self._lbl_file = tk.Label(bar, text="(chưa chọn file)", font=FONT, bg="#dde6f0", fg="#444")
        self._lbl_file.pack(side="left", padx=8)

        tk.Button(
            bar, text="⚙️ Cấu hình Mapping", font=FONT, command=self._open_mapping
        ).pack(side="left", padx=4)

        tk.Button(
            bar,
            text="💾 Lưu vào ECUS",
            font=FONT,
            bg="#2196f3",
            fg="white",
            activebackground="#1565c0",
            command=self._save_to_ecus,
        ).pack(side="left", padx=4)

        tk.Button(bar, text="🗑️ Xóa", font=FONT, command=self._clear_form).pack(
            side="left", padx=4
        )

    # ------------------------------------------------------------------
    # Notebook
    # ------------------------------------------------------------------

    def _build_notebook(self):
        self._nb = ttk.Notebook(self)
        self._nb.pack(fill="both", expand=True, padx=4, pady=4)

        # Tab 1
        tab1_outer = tk.Frame(self._nb)
        self._nb.add(tab1_outer, text="Thông tin chung")
        self._build_tab1(tab1_outer)

        # Tab 2
        tab2 = tk.Frame(self._nb)
        self._nb.add(tab2, text="Danh sách hàng")
        self._build_tab2(tab2)

        # Tab 3
        tab3 = tk.Frame(self._nb)
        self._nb.add(tab3, text="Thông tin Container")
        tk.Label(tab3, text="(Chưa triển khai)", font=FONT, fg="#999").pack(pady=20)

        # Tab 4
        tab4 = tk.Frame(self._nb)
        self._nb.add(tab4, text="Chỉ thị của Hải quan")
        tk.Label(tab4, text="(Chưa triển khai)", font=FONT, fg="#999").pack(pady=20)

    # ------------------------------------------------------------------
    # Tab 1 — Thông tin chung (scrollable)
    # ------------------------------------------------------------------

    def _build_tab1(self, parent):
        parent.columnconfigure(0, weight=1)
        parent.rowconfigure(0, weight=1)

        canvas = tk.Canvas(parent, highlightthickness=0)
        canvas.grid(row=0, column=0, sticky="nsew")
        sb_y = ttk.Scrollbar(parent, orient="vertical", command=canvas.yview)
        sb_y.grid(row=0, column=1, sticky="ns")
        canvas.configure(yscrollcommand=sb_y.set)

        inner = tk.Frame(canvas)
        win_id = canvas.create_window((0, 0), window=inner, anchor="nw")

        def _resize(evt):
            canvas.configure(scrollregion=canvas.bbox("all"))
            canvas.itemconfig(win_id, width=canvas.winfo_width())

        inner.bind("<Configure>", _resize)
        canvas.bind("<Configure>", lambda e: canvas.itemconfig(win_id, width=e.width))

        def _on_mousewheel(event):
            canvas.yview_scroll(int(-1 * (event.delta / 120)), "units")

        canvas.bind("<Enter>", lambda e: canvas.bind_all("<MouseWheel>", _on_mousewheel))
        canvas.bind("<Leave>", lambda e: canvas.unbind_all("<MouseWheel>"))

        self._populate_tab1(inner)

    def _populate_tab1(self, parent):
        pad = {"padx": 4, "pady": 2}

        # ---- Nhóm loại hình ----
        grp = tk.LabelFrame(parent, text="Loại hình", font=FONT_BOLD, padx=6, pady=4)
        grp.pack(fill="x", padx=6, pady=4)

        # Radio loại hình kinh doanh
        lh_var = tk.StringVar(value="KD")
        self._fields["_LoaiHinh"] = lh_var
        for col, (val, lbl) in enumerate([
            ("KD", "Kinh doanh, đầu tư"),
            ("SX", "Sản xuất xuất khẩu"),
            ("GC", "Gia công"),
            ("CX", "Chế xuất"),
        ]):
            tk.Radiobutton(grp, text=lbl, variable=lh_var, value=val, font=FONT).grid(
                row=0, column=col, sticky="w", padx=6
            )

        self._add_entry_row(grp, 1, "Số tờ khai", "SoToKhai")
        self._add_entry_row(grp, 1, "STT", "STT", col_offset=2)
        self._add_entry_row(grp, 2, "Số tờ khai đầu tiên", "SoToKhaiDauTien")
        self._add_entry_row(grp, 2, "Số nhánh", "SoNhanh", col_offset=2)
        self._add_combo_row(grp, 3, "Mã loại hình", "MaLoaiHinh", [])
        self._add_combo_row(grp, 3, "Mã phân loại hàng hóa", "MaPhanLoaiHangHoa", [], col_offset=2)
        self._add_combo_row(
            grp, 4, "Cơ quan hải quan", "CoQuanHaiQuan",
            ["01B1", "01B2", "01C1"], default="01B1"
        )
        self._add_entry_row(grp, 4, "Mã bộ phận xử lý", "MaBoPhanXuLyToKhai", col_offset=2, default="00")
        self._add_combo_row(grp, 5, "Phương thức vận chuyển", "MaHieuPhuongThucVanChuyen",
                            ["10", "20", "30", "40"])
        self._add_entry_row(grp, 5, "Ngày khai báo", "NgayKhaiBao", col_offset=2)

        # ---- Đơn vị xuất nhập khẩu ----
        grp2 = tk.LabelFrame(parent, text="Đơn vị xuất nhập khẩu", font=FONT_BOLD, padx=6, pady=4)
        grp2.pack(fill="x", padx=6, pady=4)

        tk.Label(grp2, text="Người xuất khẩu:", font=FONT_BOLD).grid(row=0, column=0, sticky="w", columnspan=4)
        self._add_entry_row(grp2, 1, "Mã", "NguoiXuatKhau_Ma")
        self._add_entry_row(grp2, 1, "Tên", "NguoiXuatKhau_Ten", col_offset=2)
        self._add_entry_row(grp2, 2, "Mã bưu chính / ĐT", "NguoiXuatKhau_MaBuuChinh")
        self._add_entry_row(grp2, 2, "Điện thoại", "NguoiXuatKhau_DienThoai", col_offset=2)
        self._add_entry_row(grp2, 3, "Địa chỉ", "NguoiXuatKhau_DiaChi", width=50, colspan=3)

        tk.Label(grp2, text="Người ủy thác xuất khẩu:", font=FONT_BOLD).grid(row=4, column=0, sticky="w", columnspan=4)
        self._add_entry_row(grp2, 5, "Mã", "NguoiUyThac_Ma")
        self._add_entry_row(grp2, 5, "Tên", "NguoiUyThac_Ten", col_offset=2)

        tk.Label(grp2, text="Người nhập khẩu:", font=FONT_BOLD).grid(row=6, column=0, sticky="w", columnspan=4)
        self._add_entry_row(grp2, 7, "Mã", "NguoiNhapKhau_Ma")
        self._add_entry_row(grp2, 7, "Tên", "NguoiNhapKhau_Ten", col_offset=2)
        self._add_entry_row(grp2, 8, "Mã bưu chính", "NguoiNhapKhau_MaBuuChinh")
        self._add_entry_row(grp2, 8, "Mã nước", "NguoiNhapKhau_MaNuoc", col_offset=2)
        self._add_entry_row(grp2, 9, "Địa chỉ", "NguoiNhapKhau_DiaChi", width=50, colspan=3)
        self._add_entry_row(grp2, 10, "Mã người khai HQ", "MaNguoiKhaiHaiQuan")

        # ---- Vận đơn ----
        grp3 = tk.LabelFrame(parent, text="Vận đơn", font=FONT_BOLD, padx=6, pady=4)
        grp3.pack(fill="x", padx=6, pady=4)

        self._add_entry_row(grp3, 0, "Số vận đơn", "SoVanDon")
        self._add_entry_row(grp3, 0, "Số lượng kiện", "SoLuongKien", col_offset=2)
        self._add_entry_row(grp3, 1, "Tổng trọng lượng", "TongTrongLuongHang")
        self._add_combo_row(grp3, 1, "Điểm lưu kho", "MaDiemLuuKhoHangChoThongQuan", [], col_offset=2)
        self._add_combo_row(grp3, 2, "Địa điểm nhận hàng cuối", "DiaDiemNhanHangCuoiCung", [])
        self._add_combo_row(grp3, 2, "Địa điểm xếp hàng", "DiaDiemXepHang", [], col_offset=2)
        self._add_entry_row(grp3, 3, "Phương tiện vận chuyển", "PhuongTienVanChuyen")
        self._add_entry_row(grp3, 3, "Ngày hàng đi dự kiến", "NgayHangDiDuKien", col_offset=2)
        self._add_entry_row(grp3, 4, "Ký hiệu và số hiệu", "KyHieuVaSoHieu")

        # ---- Hợp đồng ----
        grp4 = tk.LabelFrame(parent, text="Thông tin hợp đồng", font=FONT_BOLD, padx=6, pady=4)
        grp4.pack(fill="x", padx=6, pady=4)

        self._add_entry_row(grp4, 0, "Số hợp đồng", "SoHopDong")
        self._add_entry_row(grp4, 0, "Ngày hợp đồng", "NgayHopDong", col_offset=2)
        self._add_entry_row(grp4, 1, "Ngày hết hạn", "NgayHetHan")

        # ---- Hóa đơn ----
        grp5 = tk.LabelFrame(parent, text="Thông tin hóa đơn", font=FONT_BOLD, padx=6, pady=4)
        grp5.pack(fill="x", padx=6, pady=4)

        self._add_combo_row(grp5, 0, "Phân loại hình thức HĐ", "PhanLoaiHinhThucHoaDon", ["1", "2", "3"])
        self._add_entry_row(grp5, 0, "Số tiếp nhận HĐ điện tử", "SoTiepNhanHoaDonDienTu", col_offset=2)
        self._add_entry_row(grp5, 1, "Ngày phát hành", "NgayPhatHanh")
        self._add_entry_row(grp5, 1, "Số hóa đơn", "SoHoaDon", col_offset=2)
        self._add_combo_row(grp5, 2, "Phương thức thanh toán", "PhuongThucThanhToan",
                            ["CK", "TM", "TM/CK", "TTD", "L/C"])
        self._add_combo_row(grp5, 2, "Mã phân loại giá HĐ", "MaPhanLoaiGiaHoaDon", ["CIF", "FOB", "CFR"], col_offset=2)
        self._add_combo_row(grp5, 3, "Điều kiện giá HĐ", "DieuKienGiaHoaDon", ["CIF", "FOB", "CFR", "EXW"])
        self._add_entry_row(grp5, 4, "Tổng trị giá HĐ", "TongTriGiaHoaDon")
        self._add_combo_row(grp5, 4, "Mã đồng tiền HĐ", "MaDongTienCuaHoaDon",
                            ["VND", "USD", "EUR", "JPY", "CNY"], default="VND", col_offset=2)
        self._add_entry_row(grp5, 5, "Trị giá tính thuế", "TriGiaTinhThue")
        self._add_combo_row(grp5, 5, "Đồng tiền trị giá tính thuế", "MaDongTienTriGiaTinhThue",
                            ["VND", "USD", "EUR", "JPY", "CNY"], default="VND", col_offset=2)

        # ---- Ghi chú ----
        grp6 = tk.LabelFrame(parent, text="Ghi chú", font=FONT_BOLD, padx=6, pady=4)
        grp6.pack(fill="x", padx=6, pady=4)
        grp6.columnconfigure(1, weight=1)

        tk.Label(grp6, text="Phần ghi chú:", font=FONT, anchor="w").grid(row=0, column=0, sticky="nw", padx=4, pady=2)
        self._txt_ghichu = tk.Text(grp6, font=FONT, height=3, width=70)
        self._txt_ghichu.grid(row=0, column=1, columnspan=3, sticky="ew", padx=4, pady=2)

        self._add_entry_row(grp6, 1, "Số quản lý nội bộ", "SoQuanLyNoiBo")

    # ------------------------------------------------------------------
    # Helpers tạo widget
    # ------------------------------------------------------------------

    def _add_entry_row(self, parent, row, label, field, col_offset=0, width=20, colspan=1, default=""):
        """Thêm Label + Entry vào lưới, lưu StringVar vào self._fields."""
        tk.Label(parent, text=label + ":", font=FONT, anchor="w").grid(
            row=row, column=col_offset, sticky="w", padx=4, pady=2
        )
        var = tk.StringVar(value=default)
        self._fields[field] = var
        entry = tk.Entry(parent, textvariable=var, font=FONT, width=width)
        entry.grid(row=row, column=col_offset + 1, columnspan=colspan, sticky="ew", padx=4, pady=2)

    def _add_combo_row(self, parent, row, label, field, values, default="", col_offset=0):
        """Thêm Label + Combobox vào lưới, lưu StringVar vào self._fields."""
        tk.Label(parent, text=label + ":", font=FONT, anchor="w").grid(
            row=row, column=col_offset, sticky="w", padx=4, pady=2
        )
        var = tk.StringVar(value=default)
        self._fields[field] = var
        cb = ttk.Combobox(parent, textvariable=var, values=values, font=FONT, width=18)
        cb.grid(row=row, column=col_offset + 1, sticky="ew", padx=4, pady=2)

    # ------------------------------------------------------------------
    # Tab 2 — Danh sách hàng
    # ------------------------------------------------------------------

    def _build_tab2(self, parent):
        parent.columnconfigure(0, weight=1)
        parent.rowconfigure(0, weight=1)

        # Treeview
        tree_frame = tk.Frame(parent)
        tree_frame.grid(row=0, column=0, sticky="nsew")
        tree_frame.columnconfigure(0, weight=1)
        tree_frame.rowconfigure(0, weight=1)

        cols = [c[0] for c in TREE_COLUMNS]
        self._tree = ttk.Treeview(tree_frame, columns=cols, show="headings", selectmode="browse")
        for col_name, width in TREE_COLUMNS:
            self._tree.heading(col_name, text=col_name)
            self._tree.column(col_name, width=width, minwidth=40, anchor="center")

        sb_y = ttk.Scrollbar(tree_frame, orient="vertical", command=self._tree.yview)
        sb_x = ttk.Scrollbar(tree_frame, orient="horizontal", command=self._tree.xview)
        self._tree.configure(yscrollcommand=sb_y.set, xscrollcommand=sb_x.set)

        self._tree.grid(row=0, column=0, sticky="nsew")
        sb_y.grid(row=0, column=1, sticky="ns")
        sb_x.grid(row=1, column=0, sticky="ew")

        self._tree.bind("<Double-1>", self._on_tree_double_click)

        # Button bar
        btn_frame = tk.Frame(parent)
        btn_frame.grid(row=1, column=0, pady=4)
        tk.Button(btn_frame, text="➕ Thêm dòng", font=FONT, command=self._add_item_row).pack(
            side="left", padx=8
        )
        tk.Button(btn_frame, text="➖ Xóa dòng", font=FONT, command=self._delete_item_row).pack(
            side="left", padx=8
        )

    def _tree_values(self, item_dict: dict, stt: int) -> tuple:
        return (
            stt,
            item_dict.get("MaHang", ""),
            item_dict.get("TenHang", ""),
            item_dict.get("MaHS", ""),
            item_dict.get("XuatXu", ""),
            item_dict.get("Luong", ""),
            item_dict.get("DonViTinh", ""),
            item_dict.get("Luong2", ""),
            item_dict.get("DonViTinh2", ""),
            item_dict.get("DonGiaHoaDon", ""),
            item_dict.get("TriGiaHoaDon", ""),
        )

    def _refresh_tree(self):
        for iid in self._tree.get_children():
            self._tree.delete(iid)
        for i, row in enumerate(self._item_rows, start=1):
            self._tree.insert("", "end", values=self._tree_values(row, i))

    def _on_tree_double_click(self, event):
        sel = self._tree.selection()
        if not sel:
            return
        iid = sel[0]
        idx = self._tree.index(iid)
        row_data = dict(self._item_rows[idx])

        def _apply(new_vals):
            self._item_rows[idx] = new_vals
            self._refresh_tree()

        ItemEditDialog(self, row_data, _apply)

    def _add_item_row(self):
        empty = {c[0]: "" for c in TREE_COLUMNS if c[0] != "STT"}
        self._item_rows.append(empty)
        self._refresh_tree()

    def _delete_item_row(self):
        sel = self._tree.selection()
        if not sel:
            messagebox.showwarning("Chưa chọn", "Vui lòng chọn một dòng để xóa.")
            return
        idx = self._tree.index(sel[0])
        self._item_rows.pop(idx)
        self._refresh_tree()

    # ------------------------------------------------------------------
    # Toolbar actions
    # ------------------------------------------------------------------

    def _open_xml(self):
        path = filedialog.askopenfilename(
            title="Chọn file XML hóa đơn",
            filetypes=[("XML files", "*.xml"), ("All files", "*.*")],
        )
        if not path:
            return
        self._xml_file = path
        self._lbl_file.configure(text=os.path.basename(path))
        self._load_xml(path)

    def _load_xml(self, path: str):
        try:
            from xml_parser import parse_invoice
            self._parsed = parse_invoice(path)
        except Exception as exc:
            messagebox.showerror("Lỗi đọc XML", str(exc))
            return

        header = self._parsed.get("header", {})
        items = self._parsed.get("items", [])

        # Điền dữ liệu theo mapping
        for field, cfg in self._mapping.items():
            xml_key = cfg.get("xml_field", "")
            default_val = cfg.get("default", "")
            raw = header.get(xml_key) if xml_key else None
            value = raw if raw is not None else default_val
            if field in self._fields:
                self._fields[field].set(str(value))

        # Điền danh sách hàng hóa
        self._item_rows = []
        for item in items:
            self._item_rows.append({
                "MaHang": item.get("MaHang") or "",
                "TenHang": item.get("TenHang") or "",
                "MaHS": "",
                "XuatXu": "",
                "Luong": "" if item.get("SoLuong") is None else str(item["SoLuong"]),
                "DonViTinh": item.get("DonViTinh") or "",
                "Luong2": "",
                "DonViTinh2": "",
                "DonGiaHoaDon": "" if item.get("DonGia") is None else str(item["DonGia"]),
                "TriGiaHoaDon": "" if item.get("ThanhTien") is None else str(item["ThanhTien"]),
            })
        self._refresh_tree()
        messagebox.showinfo("Thành công", f"Đã tải {len(items)} dòng hàng từ file XML.")

    def _open_mapping(self):
        def _on_save(new_mapping):
            self._mapping = new_mapping

        MappingWindow(self, self._mapping, _on_save)

    def _clear_form(self):
        if not messagebox.askyesno("Xác nhận", "Xóa toàn bộ dữ liệu trên form?"):
            return
        for var in self._fields.values():
            if isinstance(var, tk.StringVar):
                var.set("")
        if hasattr(self, "_txt_ghichu"):
            self._txt_ghichu.delete("1.0", "end")
        self._item_rows.clear()
        self._refresh_tree()
        self._xml_file = ""
        self._parsed = None
        self._lbl_file.configure(text="(chưa chọn file)")

    # ------------------------------------------------------------------
    # Lưu vào ECUS
    # ------------------------------------------------------------------

    def _save_to_ecus(self):
        try:
            from ecus_importer import get_connection
        except ImportError as exc:
            messagebox.showerror("Lỗi import", f"Không import được ecus_importer: {exc}")
            return

        try:
            conn = get_connection()
        except Exception as exc:
            messagebox.showerror("Lỗi kết nối", str(exc))
            return

        try:
            self._do_insert(conn)
            conn.commit()
            messagebox.showinfo("Thành công", "Đã lưu tờ khai vào ECUS thành công!")
        except Exception as exc:
            try:
                conn.rollback()
            except Exception:
                pass
            messagebox.showerror("Lỗi lưu dữ liệu", str(exc))
            logger.error("Lỗi lưu ECUS: %s", exc, exc_info=True)
        finally:
            try:
                conn.close()
            except Exception:
                pass

    def _do_insert(self, conn):
        cursor = conn.cursor()

        # ---- Kiểm tra bảng DTOKHAIMD tồn tại ----
        cursor.execute(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", "DTOKHAIMD"
        )
        if cursor.fetchone()[0] == 0:
            raise RuntimeError("Bảng 'DTOKHAIMD' không tồn tại trong database.")

        # ---- Kiểm tra bảng DHANGMDDK tồn tại ----
        cursor.execute(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?", "DHANGMDDK"
        )
        if cursor.fetchone()[0] == 0:
            raise RuntimeError("Bảng 'DHANGMDDK' không tồn tại trong database.")

        # ---- Lấy danh sách cột thực tế của DTOKHAIMD ----
        cursor.execute(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
            "WHERE TABLE_NAME = 'DTOKHAIMD' ORDER BY ORDINAL_POSITION"
        )
        hdr_cols = [r[0] for r in cursor.fetchall()]

        # ---- Lấy danh sách cột thực tế của DHANGMDDK ----
        cursor.execute(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
            "WHERE TABLE_NAME = 'DHANGMDDK' ORDER BY ORDINAL_POSITION"
        )
        det_cols = [r[0] for r in cursor.fetchall()]

        # ---- Xây dựng dict header từ form ----
        hdr_data = {}
        for field, var in self._fields.items():
            if field.startswith("_"):
                continue
            val = var.get().strip() if isinstance(var, tk.StringVar) else var.get()
            hdr_data[field] = val if val != "" else None

        # Ghi chú không được map vào TTTK (char(1) dùng cho mã trạng thái)
        # và không có cột nào khác trong DTOKHAIMD phù hợp, nên bỏ qua.

        # ---- INSERT vào DTOKHAIMD ----
        # Mapping tên field form → tên cột DTOKHAIMD
        # Không map NguoiXuatKhau_Ten, NguoiNhapKhau_Ten/DiaChi, TTTK
        # vì các cột tương ứng trong DTOKHAIMD không đủ độ dài để chứa dữ liệu form
        HEADER_FIELD_TO_COL = {
            # === 3 cột NOT NULL bắt buộc ===
            "CoQuanHaiQuan":             "MA_HQ",           # max 8 — '01B1' = 4 ký tự ✅
            "NguoiXuatKhau_Ma":          "MA_DV",           # max 14 — MST 10 số ✅
            "NguoiNhapKhau_Ma":          "DV_DT",           # max 500 ✅

            # === Các cột khác ===
            "MaLoaiHinh":                "MA_LH",           # max 8
            "MaDongTienCuaHoaDon":       "MA_NT",           # max 8
            "PhuongThucThanhToan":       "MA_PTTT",         # max 10
            "SoToKhaiDauTien":           "SOTK_DAU_TIEN",   # max 12
            "MaBoPhanXuLyToKhai":        "MA_BC_DV",        # max 50 — chỉ map mã '00', không map tên
            "NguoiUyThac_Ma":            "MA_DVUT",         # max 50
            "MaHieuPhuongThucVanChuyen": "MA_PTVT",         # max 50
            "NguoiXuatKhau_DiaChi":      "DIA_CHI_DV",      # max 300 ✅
            "SoVanDon":                  "VAN_DON",         # max 500
            "SoHoaDon":                  "SO_HD",           # max 500
            "SoHopDong":                 "SO_HDTM",         # max 500
            "KyHieuVaSoHieu":            "KY_HIEU_SO_HIEU", # max 140
            "SoQuanLyNoiBo":             "MA_KHACH_HANG",   # max 100

            # === Numeric/datetime — không cần giới hạn ký tự ===
            "TongTriGiaHoaDon":          "TONGTGKB",        # float
            "TongTrongLuongHang":        "TR_LUONG",        # numeric
            "SoLuongKien":               "SO_KIEN",         # numeric

            # === KHÔNG map các field sau ===
            # NguoiXuatKhau_Ten — không có cột tên đủ dài phù hợp
            # NguoiNhapKhau_Ten — không có cột tên đủ dài phù hợp
            # NguoiNhapKhau_DiaChi — không có cột địa chỉ NK rõ ràng
            # TTTK — char(1), không map
        }

        # Remap tên field form → tên cột DB
        hdr_data_remapped = {}
        for form_field, value in hdr_data.items():
            db_col = HEADER_FIELD_TO_COL.get(form_field, form_field)
            hdr_data_remapped[db_col] = value

        # Identity column của DTOKHAIMD là _DToKhaiMDID
        identity_hdr = "_DToKhaiMDID"
        insertable_hdr = [c for c in hdr_cols if c != identity_hdr]
        row_hdr = {k: v for k, v in hdr_data_remapped.items() if k in insertable_hdr and v is not None}

        if not row_hdr:
            raise ValueError("Không có trường nào khớp với cột trong bảng DTOKHAIMD.")

        # ---- Tự động truncate chuỗi vượt quá giới hạn cột ----
        cursor.execute(
            "SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS "
            "WHERE TABLE_NAME = 'DTOKHAIMD' AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL "
            "AND CHARACTER_MAXIMUM_LENGTH > 0"
        )
        col_limits = {r[0]: r[1] for r in cursor.fetchall()}
        for col, val in row_hdr.items():
            if isinstance(val, str) and col in col_limits:
                max_len = col_limits[col]
                if len(val) > max_len:
                    logger.warning(
                        "Cột %s: cắt ngắn từ %d → %d ký tự (giá trị bị cắt: %r)",
                        col, len(val), max_len, val[max_len - 10 : max_len + 10],
                    )
                    row_hdr[col] = val[:max_len]

        for required_col in ("MA_HQ", "MA_DV", "DV_DT"):
            if not row_hdr.get(required_col):
                raise ValueError(f"Cột bắt buộc '{required_col}' không có giá trị. Vui lòng kiểm tra form.")

        cols_str = ", ".join(f"[{c}]" for c in row_hdr.keys())
        placeholders = ", ".join("?" for _ in row_hdr)
        sql_hdr = f"INSERT INTO DTOKHAIMD ({cols_str}) VALUES ({placeholders})"
        cursor.execute(sql_hdr, list(row_hdr.values()))

        # ---- Lấy _DToKhaiMDID vừa insert ----
        from ecus_importer import _fetch_last_identity
        new_id = _fetch_last_identity(cursor, "DTOKHAIMD")
        logger.info("Đã insert DTOKHAIMD — _DToKhaiMDID = %d", new_id)

        # ---- INSERT từng dòng hàng vào DHANGMDDK ----
        # Identity column của DHANGMDDK là _DHangMDDKID
        identity_det = "_DHangMDDKID"
        fk_det = "_DToKhaiMDID"
        insertable_det = [c for c in det_cols if c != identity_det]

        # Mapping từ tên field trong form → tên cột trong DHANGMDDK
        FIELD_TO_COL = {
            "MaHang":       "MA_HANG",
            "TenHang":      "TEN_HANG",
            "MaHS":         "Ma_HTS",
            "XuatXu":       "NUOC_XX",
            "Luong":        "LUONG",
            "DonViTinh":    "MA_DVT",
            "Luong2":       "LUONG2",
            "DonViTinh2":   "MA_DVT2",
            "DonGiaHoaDon": "DGIA_TT",
            "TriGiaHoaDon": "TRIGIA_TT",
        }

        for idx, item in enumerate(self._item_rows, start=1):
            item_data = {fk_det: new_id, "STTHANG": idx}
            for form_field, db_col in FIELD_TO_COL.items():
                val = item.get(form_field, "")
                # Chuyển số thực cho các cột decimal
                if db_col in ("LUONG", "LUONG2", "DGIA_TT", "TRIGIA_TT", "TRIGIA_KB", "DGIA_KB"):
                    try:
                        val = float(val) if val != "" else None
                    except (ValueError, TypeError):
                        val = None
                else:
                    val = val if val != "" else None
                item_data[db_col] = val

            # Chỉ giữ cột có trong bảng thực tế
            row_det = {k: v for k, v in item_data.items() if k in insertable_det}
            if not row_det:
                continue

            cols_d = ", ".join(f"[{c}]" for c in row_det.keys())
            ph_d = ", ".join("?" for _ in row_det)
            cursor.execute(
                f"INSERT INTO DHANGMDDK ({cols_d}) VALUES ({ph_d})",
                list(row_det.values()),
            )
            logger.debug("Inserted DHANGMDDK dòng %d", idx)

        logger.info("Đã insert %d dòng hàng vào DHANGMDDK.", len(self._item_rows))


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    app = ToKhaiApp()
    app.mainloop()


if __name__ == "__main__":
    main()
