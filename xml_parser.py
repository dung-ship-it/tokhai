"""
xml_parser.py
Parse file XML phiếu xuất kho kiêm vận chuyển nội bộ (hóa đơn điện tử Việt Nam).
"""

import xml.etree.ElementTree as ET


def _text(element, tag, default=""):
    """Lấy text của thẻ con, trả về default nếu không tìm thấy."""
    child = element.find(tag)
    if child is not None and child.text:
        return child.text.strip()
    return default


def parse_invoice(xml_file: str) -> dict:
    """
    Parse file XML hóa đơn điện tử Việt Nam.

    Args:
        xml_file: Đường dẫn đến file XML.

    Returns:
        dict với 2 key:
            - 'header': dict thông tin header hóa đơn
            - 'items': list các dict thông tin từng dòng hàng hóa
    """
    tree = ET.parse(xml_file)
    root = tree.getroot()

    # Loại bỏ namespace nếu có (ví dụ: {http://...}HDon → HDon)
    _strip_ns(root)

    # Tìm node chứa dữ liệu hóa đơn — hỗ trợ cả cấu trúc có DLHDon hoặc không
    _dl = root.find(".//DLHDon")
    dl_hdon = _dl if _dl is not None else root

    _ttc = dl_hdon.find(".//TTChung")
    if _ttc is None:
        _ttc = dl_hdon.find("TTChung")
    tt_chung = _ttc if _ttc is not None else ET.Element("TTChung")

    _nd = dl_hdon.find(".//NDHDon")
    if _nd is None:
        _nd = dl_hdon.find("NDHDon")
    nd_hdon = _nd if _nd is not None else dl_hdon

    _nb = nd_hdon.find("NBan")
    n_ban = _nb if _nb is not None else ET.Element("NBan")

    _nm = nd_hdon.find("NMua")
    n_mua = _nm if _nm is not None else ET.Element("NMua")

    header = {
        # Thông tin chung
        "SoHoaDon": _text(tt_chung, "SHDon") or _text(tt_chung, "SoHDon"),
        "NgayXuatHoaDon": _text(tt_chung, "NLap"),
        "MauSo": _text(tt_chung, "KHMSHDon"),
        "KyHieu": _text(tt_chung, "KHHDon"),
        "DongTienThanhToan": _text(tt_chung, "LoaiTien"),
        "MaLoaiHoaDon": _text(tt_chung, "MaLoaiHD") or _text(tt_chung, "MaLoai"),
        # Tên hóa đơn lấy từ TenHDon hoặc THHD (tên hóa đơn)
        "GhiChu": _text(tt_chung, "TenHDon") or _text(tt_chung, "THHD"),
        # Người bán
        "BenBanTenDonVi": _text(n_ban, "Ten"),
        "BenBanMaSoThue": _text(n_ban, "MST"),
        "BenBanDiaChi": _text(n_ban, "DChi"),
        "BenBanDienThoai": _text(n_ban, "SDThoai") or _text(n_ban, "SoDienThoai"),
        "BenBanFax": _text(n_ban, "Fax"),
        "BenBanTaiKhoanNganHang": _text(n_ban, "STKNHang") or _text(n_ban, "SoTK"),
        "BenBanTenNganHang": _text(n_ban, "TNHang") or _text(n_ban, "TenNganHang"),
        # Người mua
        "BenMuaTenDonVi": _text(n_mua, "Ten"),
        "BenMuaMaSoThue": _text(n_mua, "MST"),
        "BenMuaDiaChi": _text(n_mua, "DChi"),
        "BenMuaHoTen": _text(n_mua, "HoTen") or _text(n_mua, "NMHHoa"),
        "BenMuaDienThoai": _text(n_mua, "SDThoai") or _text(n_mua, "SoDienThoai"),
        "BenMuaEmail": _text(n_mua, "DCTDTu") or _text(n_mua, "Email"),
        "BenMuaTaiKhoanNganHang": _text(n_mua, "STKNHang") or _text(n_mua, "SoTK"),
        "BenMuaTenNganHang": _text(n_mua, "TNHang") or _text(n_mua, "TenNganHang"),
        "BenMuaMaDonVi": _text(n_mua, "MDVQHKhach") or _text(n_mua, "MaDonVi"),
        # Tổng tiền
        "TongTienHang": _parse_number(_text(nd_hdon, "TToan") or _text(nd_hdon, "TgTCThue")),
        "TienThueVat": _parse_number(_text(nd_hdon, "TgTThue")),
        "TongTienThanhToan": _parse_number(
            _text(nd_hdon, "TgTTToan") or _text(nd_hdon, "TTToan")
        ),
        "TongTienThanhToanBangChu": _text(nd_hdon, "TTTBChu") or _text(nd_hdon, "TienBangChu"),
        # Thông tin vận chuyển (phiếu xuất kho)
        "SoHopDongVanChuyen": _text(nd_hdon, "SoHDVChuyen") or _text(nd_hdon, "SoHopDong"),
        "HoTenNguoiVanChuyen": _text(nd_hdon, "HoTenNVChuyen"),
        "MaPhuongTienVanChuyen": _text(nd_hdon, "MPTVChuyen") or _text(nd_hdon, "BienSoXe"),
        "XuatTaiKho": _text(nd_hdon, "XuatTaiKho") or _text(nd_hdon, "DiemXuat"),
        "NhapTaiKho": _text(nd_hdon, "NhapTaiKho") or _text(nd_hdon, "DiemNhap"),
        "LenhDieuDongSo": _text(nd_hdon, "SoLDD") or _text(nd_hdon, "LenhDieuDong"),
        "HinhThucThanhToan": _text(nd_hdon, "HTTToan") or _text(nd_hdon, "HinhThucTT"),
        "isPXK": 1,  # Đây là phiếu xuất kho
    }

    # Parse danh sách hàng hóa dịch vụ
    items = []
    _ds = nd_hdon.find("DSHHDVu")
    ds_hhdvu = _ds if _ds is not None else nd_hdon
    for hhdvu in ds_hhdvu.findall("HHDVu"):
        item = {
            "TinhChat": _parse_int(_text(hhdvu, "TChat")),
            "SoThuTu": _parse_int(_text(hhdvu, "STT")),
            "MaHang": _text(hhdvu, "MHHDVu") or _text(hhdvu, "MaHang"),
            "TenHang": _text(hhdvu, "THHDVu") or _text(hhdvu, "TenHang"),
            "DonViTinh": _text(hhdvu, "DVTinh"),
            "SoLuong": _parse_number(_text(hhdvu, "SLuong")),
            "DonGia": _parse_number(_text(hhdvu, "DGia")),
            "ThanhTien": _parse_number(_text(hhdvu, "ThTien") or _text(hhdvu, "TgTKhau")),
            "ChietKhauTien": _parse_number(_text(hhdvu, "STCKhau")),
            "VAT": _parse_number(_text(hhdvu, "TSuat")),
            "TienVat": _parse_number(_text(hhdvu, "TienThue") or _text(hhdvu, "TTSuat")),
            "GhiChu": _text(hhdvu, "GhiChu"),
        }
        items.append(item)

    return {"header": header, "items": items}


def _strip_ns(element):
    """Đệ quy loại bỏ namespace khỏi tất cả tag trong cây XML."""
    if element.tag and element.tag.startswith("{"):
        element.tag = element.tag.split("}", 1)[1]
    for child in element:
        _strip_ns(child)


def _parse_number(value: str):
    """Chuyển chuỗi thành số thực; trả về None nếu rỗng hoặc không hợp lệ."""
    if not value:
        return None
    try:
        return float(value.replace(",", "."))
    except ValueError:
        return None


def _parse_int(value: str):
    """Chuyển chuỗi thành số nguyên; trả về None nếu rỗng hoặc không hợp lệ."""
    if not value:
        return None
    try:
        return int(value)
    except ValueError:
        return None
