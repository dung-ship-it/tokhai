<?php
/**
 * XmlParser.php
 * Parse file XML hóa đơn điện tử Việt Nam — port từ xml_parser.py.
 */

class InvoiceXmlParser
{
    /**
     * Parse file XML hóa đơn điện tử.
     *
     * @param  string $xmlFilePath  Đường dẫn đến file XML.
     * @return array  ['header' => [...], 'items' => [...]]
     * @throws RuntimeException nếu không đọc / parse được file.
     */
    public static function parse(string $xmlFilePath): array
    {
        if (!file_exists($xmlFilePath)) {
            throw new RuntimeException("File XML không tồn tại: $xmlFilePath");
        }

        $content = file_get_contents($xmlFilePath);
        if ($content === false) {
            throw new RuntimeException("Không thể đọc file XML: $xmlFilePath");
        }

        // Tắt lỗi libxml để xử lý thủ công
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = implode('; ', array_map(fn($e) => trim($e->message), $errors));
            throw new RuntimeException("Lỗi parse XML: $msg");
        }
        libxml_clear_errors();

        // Xoá namespace — convert sang DOM rồi trở về SimpleXML
        $dom = dom_import_simplexml($xml)->ownerDocument;
        self::stripNamespaces($dom->documentElement);
        $root = simplexml_import_dom($dom->documentElement);

        // Tìm node chứa dữ liệu hóa đơn (hỗ trợ cả cấu trúc có/không có DLHDon)
        $dlHdon = $root->xpath('.//DLHDon');
        $dlHdon = !empty($dlHdon) ? $dlHdon[0] : $root;

        $ttChung = $dlHdon->xpath('.//TTChung');
        $ttChung = !empty($ttChung) ? $ttChung[0] : new SimpleXMLElement('<TTChung/>');

        $ndHdon = $dlHdon->xpath('.//NDHDon');
        $ndHdon = !empty($ndHdon) ? $ndHdon[0] : $dlHdon;

        $nBan = $ndHdon->NBan ?? new SimpleXMLElement('<NBan/>');
        $nMua = $ndHdon->NMua ?? new SimpleXMLElement('<NMua/>');

        // ---- Header ----
        $soHoaDon = self::text($ttChung, 'SHDon') ?: self::text($ttChung, 'SoHDon');
        $header = [
            // Thông tin chung
            'SoHoaDon'                  => $soHoaDon,
            'NgayXuatHoaDon'            => self::text($ttChung, 'NLap'),
            'MauSo'                     => self::text($ttChung, 'KHMSHDon'),
            'KyHieu'                    => self::text($ttChung, 'KHHDon'),
            'DongTienThanhToan'         => self::text($ttChung, 'LoaiTien'),
            'MaLoaiHoaDon'              => self::text($ttChung, 'MaLoaiHD') ?: self::text($ttChung, 'MaLoai'),
            'GhiChu'                    => self::text($ttChung, 'TenHDon') ?: self::text($ttChung, 'THHD'),
            // Người bán
            'BenBanTenDonVi'            => self::text($nBan, 'Ten'),
            'BenBanMaSoThue'            => self::text($nBan, 'MST'),
            'BenBanDiaChi'              => self::text($nBan, 'DChi'),
            'BenBanDienThoai'           => self::text($nBan, 'SDThoai') ?: self::text($nBan, 'SoDienThoai'),
            // Người mua
            'BenMuaTenDonVi'            => self::text($nMua, 'Ten'),
            'BenMuaMaSoThue'            => self::text($nMua, 'MST'),
            'BenMuaDiaChi'              => self::text($nMua, 'DChi'),
            // Tổng tiền
            'TongTienHang'              => self::parseNumber(
                self::text($ndHdon, 'TToan') ?: self::text($ndHdon, 'TgTCThue')
            ),
            'TienThueVat'               => self::parseNumber(self::text($ndHdon, 'TgTThue')),
            'TongTienThanhToan'         => self::parseNumber(
                self::text($ndHdon, 'TgTTToan') ?: self::text($ndHdon, 'TTToan')
            ),
            // Thông tin vận chuyển
            'SoHopDong'                 => self::text($ndHdon, 'SoHDVChuyen') ?: self::text($ndHdon, 'SoHopDong'),
            'HinhThucThanhToan'         => self::text($ndHdon, 'HTTToan') ?: self::text($ndHdon, 'HinhThucTT'),
        ];

        // ---- Items ----
        $items = [];
        $dsHHDVu = $ndHdon->DSHHDVu ?? $ndHdon;
        $hhdvuList = $dsHHDVu->HHDVu ?? [];
        foreach ($hhdvuList as $hh) {
            $items[] = [
                'TinhChat'   => self::parseInt(self::text($hh, 'TChat')),
                'SoThuTu'   => self::parseInt(self::text($hh, 'STT')),
                'MaHang'    => self::text($hh, 'MHHDVu') ?: self::text($hh, 'MaHang'),
                'TenHang'   => self::text($hh, 'THHDVu') ?: self::text($hh, 'TenHang'),
                'DonViTinh' => self::text($hh, 'DVTinh'),
                'SoLuong'   => self::parseNumber(self::text($hh, 'SLuong')),
                'DonGia'    => self::parseNumber(self::text($hh, 'DGia')),
                'ThanhTien' => self::parseNumber(
                    self::text($hh, 'ThTien') ?: self::text($hh, 'TgTKhau')
                ),
                'VAT'       => self::parseNumber(self::text($hh, 'TSuat')),
                'TienVat'   => self::parseNumber(
                    self::text($hh, 'TienThue') ?: self::text($hh, 'TTSuat')
                ),
                'GhiChu'    => self::text($hh, 'GhiChu'),
            ];
        }

        return ['header' => $header, 'items' => $items];
    }

    // -----------------------------------------------------------------------
    // Helper: lấy text của node con
    // -----------------------------------------------------------------------

    private static function text(SimpleXMLElement $node, string $tag, string $default = ''): string
    {
        $child = $node->{$tag} ?? null;
        if ($child === null) {
            // Thử xpath để tìm bất kể cấp nào
            $found = $node->xpath(".//$tag");
            if (!empty($found)) {
                $child = $found[0];
            }
        }
        if ($child !== null) {
            $val = trim((string) $child);
            if ($val !== '') {
                return $val;
            }
        }
        return $default;
    }

    // -----------------------------------------------------------------------
    // Helper: parse số thực
    // -----------------------------------------------------------------------

    public static function parseNumber(?string $value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace(',', '.', $value);
        return is_numeric($clean) ? (float) $clean : null;
    }

    // -----------------------------------------------------------------------
    // Helper: parse số nguyên
    // -----------------------------------------------------------------------

    private static function parseInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (int) $value : null;
    }

    // -----------------------------------------------------------------------
    // Helper: xoá namespace khỏi tất cả node trong DOM
    // -----------------------------------------------------------------------

    private static function stripNamespaces(DOMElement $element): void
    {
        // Đổi tên tag bỏ namespace prefix
        if (strpos($element->tagName, ':') !== false) {
            [, $localName] = explode(':', $element->tagName, 2);
            $newElement = $element->ownerDocument->createElement($localName);
            // Copy attributes (bỏ xmlns:*)
            foreach ($element->attributes as $attr) {
                if (strpos($attr->name, 'xmlns') !== 0) {
                    $newElement->setAttribute($attr->name, $attr->value);
                }
            }
            // Move children
            while ($element->firstChild) {
                $newElement->appendChild($element->firstChild);
            }
            $element->parentNode->replaceChild($newElement, $element);
            $element = $newElement;
        } else {
            // Remove xmlns attributes
            $attrsToRemove = [];
            foreach ($element->attributes as $attr) {
                if (strpos($attr->name, 'xmlns') === 0) {
                    $attrsToRemove[] = $attr->name;
                }
            }
            foreach ($attrsToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }
        }
        // Recurse vào children
        $children = iterator_to_array($element->childNodes);
        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                self::stripNamespaces($child);
            }
        }
    }
}
