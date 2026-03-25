<?php
declare(strict_types=1);

/**
 * EcusImporter — ghi tờ khai xuất khẩu vào ECUS5VNACCS
 * Tương thích với submit.php (dùng $result['success'], $result['tokhai_id'], $result['inserted_items'])
 */
class EcusImporter
{
    private PDO $pdo;

    // ================================================================
    // CONSTRUCTOR: Nhận PDO hoặc tự tạo kết nối từ config.php
    // ================================================================
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } else {
            $this->pdo = $this->createConnection();
        }
    }

    private function createConnection(): PDO
    {
        $server = defined('DB_SERVER') ? DB_SERVER : 'localhost';
        $dbName = defined('DB_NAME')   ? DB_NAME   : 'ECUS5VNACCS';
        $user   = defined('DB_USER')   ? DB_USER   : 'sa';
        $pass   = defined('DB_PASS')   ? DB_PASS   : '';

        $drivers = PDO::getAvailableDrivers();

        if (in_array('sqlsrv', $drivers, true)) {
            $dsn = "sqlsrv:Server=$server;Database=$dbName;TrustServerCertificate=yes";
        } elseif (in_array('odbc', $drivers, true)) {
            $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server=$server;Database=$dbName;TrustServerCertificate=yes";
        } else {
            throw new RuntimeException(
                'Không tìm thấy PHP extension cho SQL Server (sqlsrv hoặc odbc). '
                . 'Drivers có sẵn: ' . implode(', ', $drivers)
            );
        }

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Kết nối SQL Server thất bại: ' . $e->getMessage());
        }
    }

    // ================================================================
    // HELPERS SỐ — tránh lỗi nvarchar to numeric
    // ================================================================

    /**
     * Chuyển giá trị bất kỳ thành float an toàn.
     * Xử lý định dạng số kiểu Việt Nam: "1.500.000" hoặc "1,500,000"
     */
    private function toFloat(mixed $val): float
    {
        if ($val === null || $val === '') {
            return 0.0;
        }
        $s = trim((string)$val);
        $s = preg_replace('/[^
\d,.]/', '', $s);
        if ($s === '' || $s === null) {
            return 0.0;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        $parts = explode('.', $s);
        if (count($parts) > 2) {
            $decimal = array_pop($parts);
            if (strlen($decimal) > 2) {
                $s = implode('', $parts) . $decimal;
            } else {
                $s = implode('', $parts) . '.' . $decimal;
            }
        }
        return (float)$s;
    }

    /**
     * Chuyển giá trị bất kỳ thành int an toàn.
     */
    private function toInt(mixed $val): int
    {
        if ($val === null || $val === '') {
            return 0;
        }
        $s = preg_replace('/[^
\d]/', '', (string)$val);
        return ($s !== '' && $s !== null) ? (int)$s : 0;
    }

    // ================================================================
    // IMPORT TỜ KHAI CHÍNH — DTOKHAIMD
    // ================================================================
    private function importHeader(array $data): int
    {
        $maHq    = strtoupper(trim((string)($data['CoQuanHaiQuan']      ?? '')));
        $maDv    = trim((string)($data['NguoiXuatKhau_Ma']              ?? ''));
        $ngayDen = $this->formatDate((string)($data['NgayHangDiDuKien'] ?? date('Y-m-d')));
        $ddkh    = $maHq ? $maHq . 'OZZ' : (string)($data['MaDiemXepHangLenXe'] ?? '');

        $sql = "
        INSERT INTO DTOKHAIMD (
            _XorN, MA_HQ, MA_LH, MA_BC_DV, MA_DV,
            _Ten_DV_L1, DIA_CHI_DV,
            DV_DT, _DV_DT_L2, _DV_DT_L3,
            NUOC_NK, MA_CANGNN, CANGNN, MA_CK, TEN_CK,
            MA_PTVT, TEN_PTVT,
            NGAYKH, NGAYDEN,
            VAN_DON, SO_KIEN, DVT_KIEN, TR_LUONG, DVT_TR_LUONG,
            MA_HDTM, SO_HDTM, NGAY_HDTM, MA_PL_GIA_HDTM,
            TONGTG_HDTM, TONGTGKB, TONGTGTT,
            MA_NT, MA_NT_TGTT,
            THUE, MA_THOI_HAN_NOP_THUE,
            GhiChuDuyet, SoHSTK,
            DDIEM_KH, DIA_DIEM_GIAO_HANG, Noi_Dung_Chuyen_Cua_Khau,
            NUOC_XK, MA_GH, XUAT_NPL_SP, MA_NGHIEP_VU, THONG_TU,
            IsVNACCS, IsTQDT, PhienBan_TK,
            APP_NAME, NGUOINHAP,
            TYGIA_VND, MA_NT_THUE, KieuPhanBo, IsToKhaiCT
        ) VALUES (
            :_XorN, :MA_HQ, :MA_LH, :MA_BC_DV, :MA_DV,
            :_Ten_DV_L1, :DIA_CHI_DV,
            :DV_DT, :_DV_DT_L2, :_DV_DT_L3,
            :NUOC_NK, :MA_CANGNN, :CANGNN, :MA_CK, :TEN_CK,
            :MA_PTVT, :TEN_PTVT,
            :NGAYKH, :NGAYDEN,
            :VAN_DON, :SO_KIEN, :DVT_KIEN, :TR_LUONG, :DVT_TR_LUONG,
            :MA_HDTM, :SO_HDTM, :NGAY_HDTM, :MA_PL_GIA_HDTM,
            :TONGTG_HDTM, :TONGTGKB, :TONGTGTT,
            :MA_NT, :MA_NT_TGTT,
            :THUE, :MA_THOI_HAN_NOP_THUE,
            :GhiChuDuyet, :SoHSTK,
            :DDIEM_KH, :DIA_DIEM_GIAO_HANG, :Noi_Dung_Chuyen_Cua_Khau,
            :NUOC_XK, :MA_GH, :XUAT_NPL_SP, :MA_NGHIEP_VU, :THONG_TU,
            :IsVNACCS, :IsTQDT, :PhienBan_TK,
            :APP_NAME, :NGUOINHAP,
            :TYGIA_VND, :MA_NT_THUE, :KieuPhanBo, :IsToKhaiCT
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':_XorN'                    => 'X',
            ':MA_HQ'                    => $maHq,
            ':MA_LH'                    => 'B11',
            ':MA_BC_DV'                 => mb_substr((string)($data['MaBoPhanXuLyToKhai']          ?? ''), 0, 50),
            ':MA_DV'                    => $maDv,
            ':_Ten_DV_L1'               => mb_substr((string)($data['NguoiXuatKhau_Ten']           ?? ''), 0, 500),
            ':DIA_CHI_DV'               => mb_substr((string)($data['NguoiXuatKhau_DiaChi']        ?? ''), 0, 500),
            ':DV_DT'                    => mb_substr((string)($data['NguoiNhapKhau_Ten']           ?? ''), 0, 500),
            ':_DV_DT_L2'                => mb_substr((string)($data['NguoiNhapKhau_DiaChi1']       ?? ''), 0, 50),
            ':_DV_DT_L3'                => mb_substr((string)($data['NguoiNhapKhau_DiaChi2']       ?? ''), 0, 50),
            ':NUOC_NK'                  => 'VN',
            ':MA_CANGNN'                => 'VNZZZ',
            ':CANGNN'                   => mb_substr((string)($data['DiaDiemNhanHangCuoiCung_Ten'] ?? ''), 0, 255),
            ':MA_CK'                    => 'VNZZZ',
            ':TEN_CK'                   => mb_substr((string)($data['DiaDiemXepHang_Ten']          ?? ''), 0, 255),
            ':MA_PTVT'                  => mb_substr((string)($data['MaPhuongTienVanChuyen']       ?? '9'), 0, 10),
            ':TEN_PTVT'                 => 'TRUCK',
            ':NGAYKH'                   => $ngayDen,
            ':NGAYDEN'                  => $ngayDen,
            ':VAN_DON'                  => mb_substr((string)($data['SoVanDon']                    ?? ''), 0, 255),
            ':SO_KIEN'                  => $this->toInt($data['SoLuongKien']                       ?? 0),
            ':DVT_KIEN'                 => 'PK',
            ':TR_LUONG'                 => $this->toFloat($data['TongTrongLuongHang']              ?? 0),
            ':DVT_TR_LUONG'             => 'KGM',
            ':MA_HDTM'                  => 'B',
            ':SO_HDTM'                  => mb_substr((string)($data['SoHoaDon']                    ?? ''), 0, 255),
            ':NGAY_HDTM'                => $this->formatDate((string)($data['NgayPhatHanh']        ?? date('Y-m-d'))),
            ':MA_PL_GIA_HDTM'           => 'A',
            ':TONGTG_HDTM'              => $this->toFloat($data['TongTriGiaHoaDon']                ?? 0),
            ':TONGTGKB'                 => $this->toFloat($data['TongTriGiaHoaDon']                ?? 0),
            ':TONGTGTT'                 => $this->toFloat($data['TriGiaTinhThue']                  ?? 0),
            ':MA_NT'                    => 'VND',
            ':MA_NT_TGTT'               => 'VND',
            ':THUE'                     => 1,
            ':MA_THOI_HAN_NOP_THUE'     => 'D',
            ':GhiChuDuyet'              => mb_substr((string)($data['PhanGhiChu']                  ?? ''), 0, 500),
            ':SoHSTK'                   => '#&XKTC',
            ':DDIEM_KH'                 => mb_substr($ddkh, 0, 50),
            ':DIA_DIEM_GIAO_HANG'       => mb_substr((string)($data['TenDiemXepHangLenXe']        ?? ''), 0, 255),
            ':Noi_Dung_Chuyen_Cua_Khau' => mb_substr((string)($data['DiaChiDiemXepHangLenXe']     ?? ''), 0, 2000),
            ':NUOC_XK'                  => 'VN',
            ':MA_GH'                    => 'DAP',
            ':XUAT_NPL_SP'              => 'S',
            ':MA_NGHIEP_VU'             => 'EDC',
            ':THONG_TU'                 => 2,
            ':IsVNACCS'                 => 1,
            ':IsTQDT'                   => 1,
            ':PhienBan_TK'              => 'CT',
            ':APP_NAME'                 => 'ECUSK5NET',
            ':NGUOINHAP'                => mb_substr((string)($data['NguoiNhap']                   ?? 'Root'), 0, 50),
            ':TYGIA_VND'                => 1,
            ':MA_NT_THUE'               => 'VND',
            ':KieuPhanBo'               => 0,
            ':IsToKhaiCT'               => 0,
        ]);

        // Lấy ID vừa insert — sqlsrv PDO driver không hỗ trợ lastInsertId() đáng tin cậy
        $newId = 0;
        try {
            $row   = $this->pdo->query("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS id")->fetch(PDO::FETCH_ASSOC);
            $newId = (int)($row['id'] ?? 0);
        } catch (Throwable $ignored) {}

        if ($newId === 0) {
            try {
                $newId = (int)$this->pdo->lastInsertId();
            } catch (Throwable $ignored) {}
        }

        if ($newId === 0) {
            $row   = $this->pdo->query("SELECT MAX(_DToKhaiMDID) AS id FROM DTOKHAIMD")->fetch(PDO::FETCH_ASSOC);
            $newId = (int)($row['id'] ?? 0);
        }

        if ($newId <= 0) {
            throw new RuntimeException('Không lấy được ID tờ khai sau khi INSERT.');
        }

        return $newId;
    }

    // ================================================================
    // IMPORT HÀNG HÓA — DHANGMDDK
    // ================================================================
    private function importItems(int $tkId, string $maHq, array $items): int
    {
        $sql = "
        INSERT INTO DHANGMDDK (
            _DToKhaiMDID, SOTK, MA_LH, MA_HQ, STTHANG,
            MA_HANGKB, MA_PHU, TEN_HANG, NUOC_XX,
            MA_DVT, LUONG, LUONG2, MA_DVT2,
            DGIA_KB, DGIA_TT, TRIGIA_KB, TRIGIA_TT,
            TGKB_VND, TRIGIA_HDTM, DGIA_HDTM,
            MA_NT_DGIA_HDTM, DVT_DGIA_HDTM,
            MA_NT_TRIGIA_TT, MA_NT_DGIA_TT, DVT_DGIA_TT,
            MA_NT_THUE_XNK, THUE_XNK, LOAI_HANG,
            MA_NT_TRIGIA_TT_S, TRIGIA_TT_S
        ) VALUES (
            :_DToKhaiMDID, :SOTK, :MA_LH, :MA_HQ, :STTHANG,
            :MA_HANGKB, :MA_PHU, :TEN_HANG, :NUOC_XX,
            :MA_DVT, :LUONG, :LUONG2, :MA_DVT2,
            :DGIA_KB, :DGIA_TT, :TRIGIA_KB, :TRIGIA_TT,
            :TGKB_VND, :TRIGIA_HDTM, :DGIA_HDTM,
            :MA_NT_DGIA_HDTM, :DVT_DGIA_HDTM,
            :MA_NT_TRIGIA_TT, :MA_NT_DGIA_TT, :DVT_DGIA_TT,
            :MA_NT_THUE_XNK, :THUE_XNK, :LOAI_HANG,
            :MA_NT_TRIGIA_TT_S, :TRIGIA_TT_S
        )";

        $stmt  = $this->pdo->prepare($sql);
        $count = 0;

        foreach ($items as $i => $item) {
            $stt     = $i + 1;
            $tenHang = $this->buildTenHang((string)($item['TenHang']   ?? ''));
            $dvt     = strtoupper(trim((string)($item['DonViTinh']     ?? 'PCE')));
            $dvt2    = strtoupper(trim((string)($item['DonViTinh2']    ?? $dvt)));
            $luong   = $this->toFloat($item['Luong']                   ?? 0);
            $luong2  = $this->toFloat($item['Luong2']                  ?? $luong);
            $donGia  = $this->toFloat($item['DonGiaHoaDon']            ?? 0);
            $triGia  = $this->toFloat($item['TriGiaHoaDon']            ?? ($donGia * $luong));

            $stmt->execute([
                ':_DToKhaiMDID'      => $tkId,
                ':SOTK'              => '',
                ':MA_LH'             => 'B11',
                ':MA_HQ'             => $maHq,
                ':STTHANG'           => $stt,
                ':MA_HANGKB'         => mb_substr(strtoupper(trim((string)($item['MaHang'] ?? ''))), 0, 50),
                ':MA_PHU'            => $stt,
                ':TEN_HANG'          => mb_substr($tenHang, 0, 500),
                ':NUOC_XX'           => strtoupper(trim((string)($item['XuatXu']           ?? 'VN'))),
                ':MA_DVT'            => $dvt,
                ':LUONG'             => $luong,
                ':LUONG2'            => $luong2,
                ':MA_DVT2'           => $dvt2,
                ':DGIA_KB'           => $donGia,
                ':DGIA_TT'           => $donGia,
                ':TRIGIA_KB'         => $triGia,
                ':TRIGIA_TT'         => $triGia,
                ':TGKB_VND'          => $triGia,
                ':TRIGIA_HDTM'       => $triGia,
                ':DGIA_HDTM'         => $donGia,
                ':MA_NT_DGIA_HDTM'   => 'VND',
                ':DVT_DGIA_HDTM'     => $dvt,
                ':MA_NT_TRIGIA_TT'   => 'VND',
                ':MA_NT_DGIA_TT'     => 'VND',
                ':DVT_DGIA_TT'       => $dvt,
                ':MA_NT_THUE_XNK'    => 'VND',
                ':THUE_XNK'          => 0,
                ':LOAI_HANG'         => 2,
                ':MA_NT_TRIGIA_TT_S' => 'VND',
                ':TRIGIA_TT_S'       => $triGia,
            ]);
            $count++;
        }

        return $count;
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /** Tên hàng luôn kết thúc bằng " hàng mới 100%#&VN" */
    private function buildTenHang(string $ten): string
    {
        $ten       = trim($ten);
        $suffix    = ' hàng mới 100%#&VN';
        $suffixAlt = 'hàng mới 100%';

        if (str_contains($ten, $suffixAlt)) {
            $ten = trim(str_replace($suffix,    '', $ten));
            $ten = trim(str_replace($suffixAlt, '', $ten));
            $ten = rtrim($ten, ' ,#&VN');
            $ten = trim($ten);
        }

        return $ten . $suffix;
    }

    /** Format ngày — YYYY-MM-DD 00:00:00; hỗ trợ dd/mm/yyyy và yyyy-mm-dd */
    private function formatDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return date('Y-m-d') . ' 00:00:00';
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
            return sprintf('%04d-%02d-%02d 00:00:00', (int)$m[3], (int)$m[2], (int)$m[1]);
        }
        $ts = strtotime($date);
        return ($ts !== false ? date('Y-m-d', $ts) : date('Y-m-d')) . ' 00:00:00';
    }

    // ================================================================
    // PUBLIC: TRANSACTION WRAPPER
    // Trả về key 'tokhai_id' và 'inserted_items' để tương thích submit.php
    // ================================================================
    public function import(array $headerData, array $items): array
    {
        try {
            $this->pdo->beginTransaction();

            $tkId          = $this->importHeader($headerData);
            $maHq          = strtoupper(trim((string)($headerData['CoQuanHaiQuan'] ?? '')));
            $insertedItems = $this->importItems($tkId, $maHq, $items);

            $this->pdo->commit();

            return [
                'success'        => true,
                'tokhai_id'      => $tkId,
                'inserted_items' => $insertedItems,
                'message'        => "Đã lưu tờ khai ID: $tkId với $insertedItems mặt hàng.",
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EcusImporter] Lỗi: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'success'        => false,
                'tokhai_id'      => 0,
                'inserted_items' => 0,
                'error'          => $e->getMessage(),
                'message'        => 'Lỗi: ' . $e->getMessage(),
            ];
        }
    }
}