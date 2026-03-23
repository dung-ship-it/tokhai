"""
test_xml_parser.py
Unit tests cho xml_parser.py — không cần kết nối SQL Server.
"""

import os
import sys
import tempfile
import unittest

# Thêm thư mục gốc vào sys.path để import module
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from xml_parser import parse_invoice, _parse_number, _parse_int, _strip_ns

import xml.etree.ElementTree as ET

SAMPLE_XML = """\
<?xml version="1.0" encoding="UTF-8"?>
<HDon>
  <DLHDon>
    <TTChung>
      <KHMSHDon>1</KHMSHDon>
      <KHHDon>E24TAA</KHHDon>
      <SHDon>000001</SHDon>
      <NLap>2024-01-15</NLap>
      <LoaiTien>VND</LoaiTien>
      <TenHDon>PHIẾU XUẤT KHO KIÊM VẬN CHUYỂN NỘI BỘ</TenHDon>
    </TTChung>
    <NDHDon>
      <NBan>
        <Ten>CÔNG TY TNHH SẢN XUẤT XYZ</Ten>
        <MST>0123456789</MST>
        <DChi>123 Đường ABC, Quận 1, TP.HCM</DChi>
      </NBan>
      <NMua>
        <Ten>CHI NHÁNH CÔNG TY XYZ TẠI HÀ NỘI</Ten>
        <MST>0123456789-001</MST>
        <DChi>456 Đường DEF, Hà Nội</DChi>
      </NMua>
      <DSHHDVu>
        <HHDVu>
          <TChat>1</TChat>
          <STT>1</STT>
          <MHHDVu>SP001</MHHDVu>
          <THHDVu>Sản phẩm A</THHDVu>
          <DVTinh>Cái</DVTinh>
          <SLuong>100</SLuong>
          <DGia>50000</DGia>
          <ThTien>5000000</ThTien>
          <STCKhau>0</STCKhau>
          <TSuat>10</TSuat>
          <TienThue>500000</TienThue>
        </HHDVu>
        <HHDVu>
          <TChat>1</TChat>
          <STT>2</STT>
          <MHHDVu>SP002</MHHDVu>
          <THHDVu>Sản phẩm B</THHDVu>
          <DVTinh>Hộp</DVTinh>
          <SLuong>50</SLuong>
          <DGia>120000</DGia>
          <ThTien>6000000</ThTien>
          <STCKhau>500</STCKhau>
          <TSuat>8</TSuat>
          <TienThue>480000</TienThue>
        </HHDVu>
      </DSHHDVu>
      <TToan>11000000</TToan>
      <TgTThue>980000</TgTThue>
      <TTTBChu>Mười hai triệu đồng</TTTBChu>
      <HTTToan>CK</HTTToan>
      <SoHDVChuyen>HD2024001</SoHDVChuyen>
      <HoTenNVChuyen>Trần Văn B</HoTenNVChuyen>
      <MPTVChuyen>51H-12345</MPTVChuyen>
      <XuatTaiKho>Kho A - TP.HCM</XuatTaiKho>
      <NhapTaiKho>Kho B - Hà Nội</NhapTaiKho>
    </NDHDon>
  </DLHDon>
</HDon>
"""

SAMPLE_XML_WITH_NS = """\
<?xml version="1.0" encoding="UTF-8"?>
<inv:HDon xmlns:inv="http://example.com/inv">
  <inv:DLHDon>
    <inv:TTChung>
      <inv:SHDon>000002</inv:SHDon>
      <inv:NLap>2024-02-01</inv:NLap>
      <inv:LoaiTien>USD</inv:LoaiTien>
    </inv:TTChung>
    <inv:NDHDon>
      <inv:NBan><inv:Ten>Seller Co.</inv:Ten><inv:MST>9999999999</inv:MST><inv:DChi>HCM</inv:DChi></inv:NBan>
      <inv:NMua><inv:Ten>Buyer Co.</inv:Ten><inv:MST>8888888888</inv:MST><inv:DChi>HN</inv:DChi></inv:NMua>
      <inv:DSHHDVu>
        <inv:HHDVu>
          <inv:STT>1</inv:STT>
          <inv:THHDVu>Item X</inv:THHDVu>
          <inv:DVTinh>Kg</inv:DVTinh>
          <inv:SLuong>10</inv:SLuong>
          <inv:DGia>200</inv:DGia>
          <inv:ThTien>2000</inv:ThTien>
        </inv:HHDVu>
      </inv:DSHHDVu>
    </inv:NDHDon>
  </inv:DLHDon>
</inv:HDon>
"""


class TestParseNumber(unittest.TestCase):
    def test_integer_string(self):
        self.assertEqual(_parse_number("5000000"), 5000000.0)

    def test_float_string(self):
        self.assertAlmostEqual(_parse_number("50000.50"), 50000.50)

    def test_comma_decimal(self):
        self.assertAlmostEqual(_parse_number("1,5"), 1.5)

    def test_empty_string(self):
        self.assertIsNone(_parse_number(""))

    def test_none_input(self):
        self.assertIsNone(_parse_number(None))

    def test_invalid(self):
        self.assertIsNone(_parse_number("abc"))


class TestParseInt(unittest.TestCase):
    def test_valid(self):
        self.assertEqual(_parse_int("1"), 1)

    def test_empty(self):
        self.assertIsNone(_parse_int(""))

    def test_invalid(self):
        self.assertIsNone(_parse_int("abc"))


class TestStripNs(unittest.TestCase):
    def test_removes_namespace(self):
        root = ET.fromstring('<ns:root xmlns:ns="http://x.com"><ns:child>val</ns:child></ns:root>')
        _strip_ns(root)
        self.assertEqual(root.tag, "root")
        self.assertEqual(root.find("child").text, "val")


class TestParseInvoice(unittest.TestCase):
    def setUp(self):
        # Ghi XML ra file tạm
        self.tmp = tempfile.NamedTemporaryFile(
            delete=False, suffix=".xml", mode="w", encoding="utf-8"
        )
        self.tmp.write(SAMPLE_XML)
        self.tmp.close()

        self.tmp_ns = tempfile.NamedTemporaryFile(
            delete=False, suffix=".xml", mode="w", encoding="utf-8"
        )
        self.tmp_ns.write(SAMPLE_XML_WITH_NS)
        self.tmp_ns.close()

    def tearDown(self):
        os.unlink(self.tmp.name)
        os.unlink(self.tmp_ns.name)

    # ------ header ------
    def test_header_so_hoadon(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(result["header"]["SoHoaDon"], "000001")

    def test_header_ngay_xuat(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(result["header"]["NgayXuatHoaDon"], "2024-01-15")

    def test_header_loai_tien(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(result["header"]["DongTienThanhToan"], "VND")

    def test_header_ky_hieu(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(result["header"]["KyHieu"], "E24TAA")

    def test_header_mau_so(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(result["header"]["MauSo"], "1")

    def test_header_ben_ban(self):
        h = parse_invoice(self.tmp.name)["header"]
        self.assertEqual(h["BenBanTenDonVi"], "CÔNG TY TNHH SẢN XUẤT XYZ")
        self.assertEqual(h["BenBanMaSoThue"], "0123456789")
        self.assertIn("TP.HCM", h["BenBanDiaChi"])

    def test_header_ben_mua(self):
        h = parse_invoice(self.tmp.name)["header"]
        self.assertIn("XYZ", h["BenMuaTenDonVi"])
        self.assertEqual(h["BenMuaMaSoThue"], "0123456789-001")

    def test_header_van_chuyen(self):
        h = parse_invoice(self.tmp.name)["header"]
        self.assertEqual(h["SoHopDongVanChuyen"], "HD2024001")
        self.assertEqual(h["MaPhuongTienVanChuyen"], "51H-12345")
        self.assertIn("TP.HCM", h["XuatTaiKho"])
        self.assertIn("Hà Nội", h["NhapTaiKho"])

    def test_header_ispxk(self):
        self.assertEqual(parse_invoice(self.tmp.name)["header"]["isPXK"], 1)

    # ------ items ------
    def test_items_count(self):
        result = parse_invoice(self.tmp.name)
        self.assertEqual(len(result["items"]), 2)

    def test_item_first(self):
        item = parse_invoice(self.tmp.name)["items"][0]
        self.assertEqual(item["SoThuTu"], 1)
        self.assertEqual(item["MaHang"], "SP001")
        self.assertEqual(item["TenHang"], "Sản phẩm A")
        self.assertEqual(item["DonViTinh"], "Cái")
        self.assertEqual(item["SoLuong"], 100.0)
        self.assertEqual(item["DonGia"], 50000.0)
        self.assertEqual(item["ThanhTien"], 5000000.0)
        self.assertEqual(item["VAT"], 10.0)
        self.assertEqual(item["TienVat"], 500000.0)

    def test_item_second_chietkhaution(self):
        item = parse_invoice(self.tmp.name)["items"][1]
        self.assertEqual(item["ChietKhauTien"], 500.0)

    # ------ namespace stripping ------
    def test_namespace_xml(self):
        result = parse_invoice(self.tmp_ns.name)
        self.assertEqual(result["header"]["SoHoaDon"], "000002")
        self.assertEqual(result["header"]["DongTienThanhToan"], "USD")
        self.assertEqual(len(result["items"]), 1)
        self.assertEqual(result["items"][0]["TenHang"], "Item X")

    # ------ file không tồn tại ------
    def test_file_not_found(self):
        with self.assertRaises(FileNotFoundError):
            parse_invoice("/nonexistent/path/file.xml")


if __name__ == "__main__":
    unittest.main()
