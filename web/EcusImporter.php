<?php
declare(strict_types=1);

/**
 * EcusImporter — ghi tờ khai xuất khẩu vào ECUS5VNACCS
 * Mapping đã xác nhận từ DB thực tế TK 4099
 */
class EcusImporter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================================================
    // IMPORT TỜ KHAI CHÍNH → DTOKHAIMD
    // ================================================================
    public function importHeader(array $data): int
    {
        $sql = "
        INSERT INTO DTOKHAIMD (
            _XorN,
            MA_HQ,
            MA_LH,
            MA_BC_DV,
            MA_DV,
            _Ten_DV_L1,
            DIA_CHI_DV,
            DV_DT,
            _DV_DT_L2,
            _DV_DT_L3,
            _DV_DT_L4,
            NUOC_NK,
            MA_CANGNN,
            CANGNN,
            MA_CK,
            TEN_CK,
            MA_PTVT,
            TEN_PTVT,
            NGAYKH,
            NGAYDEN,
            VAN_DON,
            SO_KIEN,
            DVT_KIEN,
            TR_LUONG,
            DVT_TR_LUONG,
            MA_HDTM,
            SO_HDTM,
            NGAY_HDTM,
            MA_PL_GIA_HDTM,
            TONGTG_HDTM,
            TONGTGKB,
            TONGTGTT,
            MA_NT,
            MA_NT_TGTT,
            THUE,
            MA_THOI_HAN_NOP_THUE,
            GhiChuDuyet,
            SoHSTK,
            DDIEM_KH,
            DIA_DIEM_GIAO_HANG,
            Noi_Dung_Chuyen_Cua_Khau,
            NUOC_XK,
            MA_GH,
            XUAT_NPL_SP,
            MA_NGHIEP_VU,
            THONG_TU,
            IsVNACCS,
            IsTQDT,
            PhienBan_TK,
            APP_NAME,
            NGUOINHAP,
            TYGIA_VND,
            MA_NT_THUE,
            KieuPhanBo,
            IsToKhaiCT
        ) VALUES (
            :_XorN,
            :MA_HQ,
            :MA_LH,
            :MA_BC_DV,
            :MA_DV,
            :_Ten_DV_L1,
            :DIA_CHI_DV,
            :DV_DT,
            :_DV_DT_L2,
            :_DV_DT_L3,
            :_DV_DT_L4,
            :NUOC_NK,
            :MA_CANGNN,
            :CANGNN,
            :MA_CK,
            :TEN_CK,
            :MA_PTVT,
            :TEN_PTVT,
            :NGAYKH,
            :NGAYDEN,
            :VAN_DON,
            :SO_KIEN,
            :DVT_KIEN,
            :TR_LUONG,
            :DVT_TR_LUONG,
            :MA_HDTM,
            :SO_HDTM,
            :NGAY_HDTM,
            :MA_PL_GIA_HDTM,
            :TONGTG_HDTM,
            :TONGTGKB,
            :TONGTGTT,
            :MA_NT,
            :MA_NT_TGTT,
            :THUE,
            :MA_THOI_HAN_NOP_THUE,
            :GhiChuDuyet,
            :SoHSTK,
            :DDIEM_KH,
            :DIA_DIEM_GIAO_HANG,
            :Noi_Dung_Chuyen_Cua_Khau,
            :NUOC_XK,
            :MA_GH,
            :XUAT_NPL_SP,
            :MA_NGHIEP_VU,
            :THONG_TU,
            :IsVNACCS,
            :IsTQDT,
            :PhienBan_TK,
            :APP_NAME,
            :NGUOINHAP,
            :TYGIA_VND,
            :MA_NT_THUE,
            :KieuPhanBo,
            :IsToKhaiCT
        )";

        $maHq    = strtoupper(trim((string)($data['CoQuanHaiQuan']   ?? '')));
        $maDv    = trim((string)($data['NguoiXuatKhau_Ma']           ?? ''));
        $ngayDen = $this->formatDate($data['NgayHangDiDuKien']       ?? date('Y-m-d'));
        $ddkh    = $maHq ? $maHq . 'OZZ' : ($data['MaDiemXepHangLenXe'] ?? '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':_XorN'                    => 'X',
            ':MA_HQ'                    => $maHq,
            ':MA_LH'                    => 'B11',
            ':MA_BC_DV'                 => $data['MaBoPhanXuLyToKhai']        ?? '',
            ':MA_DV'                    => $maDv,
            ':_Ten_DV_L1'               => $data['NguoiXuatKhau_Ten']         ?? '',
            ':DIA_CHI_DV'               => $data['NguoiXuatKhau_DiaChi']      ?? '',
            ':DV_DT'                    => $data['NguoiNhapKhau_Ten']         ?? '',
            ':_DV_DT_L2'                => mb_substr((string)($data['NguoiNhapKhau_DiaChi1'] ?? ''), 0, 20),
            ':_DV_DT_L3'                => mb_substr((string)($data['NguoiNhapKhau_DiaChi2'] ?? ''), 0, 20),
            ':_DV_DT_L4'                => mb_substr((string)($data['NguoiNhapKhau_DiaChi3'] ?? ''), 0, 20),
            ':NUOC_NK'                  => 'VN',
            ':MA_CANGNN'                => 'VNZZZ',
            ':CANGNN'                   => $data['DiaDiemNhanHangCuoiCung_Ten'] ?? '',
            ':MA_CK'                    => 'VNZZZ',
            ':TEN_CK'                   => $data['DiaDiemXepHang_Ten']        ?? '',
            ':MA_PTVT'                  => $data['MaPhuongTienVanChuyen']     ?? '9',
            ':TEN_PTVT'                 => 'TRUCK',
            ':NGAYKH'                   => $ngayDen,
            ':NGAYDEN'                  => $ngayDen,
            ':VAN_DON'                  => $data['SoVanDon']                  ?? '',
            ':SO_KIEN'                  => $data['SoLuongKien']               ?? 0,
            ':DVT_KIEN'                 => 'PK',
            ':TR_LUONG'                 => $data['TongTrongLuongHang']        ?? 0,
            ':DVT_TR_LUONG'             => 'KGM',
            ':MA_HDTM'                  => 'B',
            ':SO_HDTM'                  => $data['SoHoaDon']                  ?? '',
            ':NGAY_HDTM'                => $this->formatDate($data['NgayPhatHanh'] ?? date('Y-m-d')),
            ':MA_PL_GIA_HDTM'           => 'A',
            ':TONGTG_HDTM'              => $data['TongTriGiaHoaDon']          ?? 0,
            ':TONGTGKB'                 => $data['TongTriGiaHoaDon']          ?? 0,
            ':TONGTGTT'                 => $data['TriGiaTinhThue']            ?? 0,
            ':MA_NT'                    => 'VND',
            ':MA_NT_TGTT'               => 'VND',
            ':THUE'                     => 1,
            ':MA_THOI_HAN_NOP_THUE'     => 'D',
            ':GhiChuDuyet'              => $data['PhanGhiChu']                ?? '',
            ':SoHSTK'                   => '#&XKTC',
            ':DDIEM_KH'                 => mb_substr($ddkh, 0, 50),
            ':DIA_DIEM_GIAO_HANG'       => mb_substr((string)($data['TenDiemXepHangLenXe']  ?? ''), 0, 255),
            ':Noi_Dung_Chuyen_Cua_Khau' => mb_substr((string)($data['DiaChiDiemXepHangLenXe'] ?? ''), 0, 2000),
            ':NUOC_XK'                  => 'VN',
            ':MA_GH'                    => 'DAP',
            ':XUAT_NPL_SP'              => 'S',
            ':MA_NGHIEP_VU'             => 'EDC',
            ':THONG_TU'                 => 2,
            ':IsVNACCS'                 => 1,
            ':IsTQDT'                   => 1,
            ':PhienBan_TK'              => 'CT',
            ':APP_NAME'                 => 'ECUSK5NET',
            ':NGUOINHAP'                => $data['NguoiNhap'] ?? 'Root',
            ':TYGIA_VND'                => 1,
            ':MA_NT_THUE'               => 'VND',
            ':KieuPhanBo'               => 0,
            ':IsToKhaiCT'               => 0,
        ]);

        // Lấy ID vừa insert
        $newId = (int)$this->pdo->lastInsertId();
        if ($newId === 0) {
            $row   = $this->pdo->query("SELECT MAX(_DToKhaiMDID) AS id FROM DTOKHAIMD")->fetch(PDO::FETCH_ASSOC);
            $newId = (int)($row['id'] ?? 0);
        }
        return $newId;
    }

    // ================================================================
    // IMPORT HÀNG HÓA → DHANGMDDK
    // ================================================================
    public function importItems(int $tkId, string $maHq, string $maLh, string $soTk, array $items): void
    {
        $sql = "
        INSERT INTO DHANGMDDK (
            _DToKhaiMDID,
            SOTK,
            MA_LH,
            MA_HQ,
            STTHANG,
            MA_HANGKB,
            MA_PHU,
            TEN_HANG,
            NUOC_XX,
            MA_DVT,
            LUONG,
            LUONG2,
            MA_DVT2,
            DGIA_KB,
            DGIA_TT,
            TRIGIA_KB,
            TRIGIA_TT,
            TGKB_VND,
            TRIGIA_HDTM,
            DGIA_HDTM,
            MA_NT_DGIA_HDTM,
            DVT_DGIA_HDTM,
            MA_NT_TRIGIA_TT,
            MA_NT_DGIA_TT,
            DVT_DGIA_TT,
            MA_NT_THUE_XNK,
            THUE_XNK,
            LOAI_HANG,
            MA_NT_TRIGIA_TT_S,
            TRIGIA_TT_S
        ) VALUES (
            :_DToKhaiMDID,
            :SOTK,
            :MA_LH,
            :MA_HQ,
            :STTHANG,
            :MA_HANGKB,
            :MA_PHU,
            :TEN_HANG,
            :NUOC_XX,
            :MA_DVT,
            :LUONG,
            :LUONG2,
            :MA_DVT2,
            :DGIA_KB,
            :DGIA_TT,
            :TRIGIA_KB,
            :TRIGIA_TT,
            :TGKB_VND,
            :TRIGIA_HDTM,
            :DGIA_HDTM,
            :MA_NT_DGIA_HDTM,
            :DVT_DGIA_HDTM,
            :MA_NT_TRIGIA_TT,
            :MA_NT_DGIA_TT,
            :DVT_DGIA_TT,
            :MA_NT_THUE_XNK,
            :THUE_XNK,
            :LOAI_HANG,
            :MA_NT_TRIGIA_TT_S,
            :TRIGIA_TT_S
        )";

        $stmt = $this->pdo->prepare($sql);

        foreach ($items as $i => $item) {
            $stt      = $i + 1;
            $tenHang  = $this->buildTenHang((string)($item['TenHang'] ?? ''));
            $dvt      = strtoupper(trim((string)($item['DonViTinh']  ?? 'PCE')));
            $dvt2     = strtoupper(trim((string)($item['DonViTinh2'] ?? $dvt)));
            $luong    = (float)($item['Luong']  ?? 0);
            $luong2   = (float)($item['Luong2'] ?? $luong);
            $donGia   = (float)($item['DonGiaHoaDon'] ?? 0);
            $triGia   = (float)($item['TriGiaHoaDon'] ?? ($donGia * $luong));

            $stmt->execute([
                ':_DToKhaiMDID'        => $tkId,
                ':SOTK'                => $soTk,
                ':MA_LH'               => $maLh,
                ':MA_HQ'               => $maHq,
                ':STTHANG'             => $stt,
                ':MA_HANGKB'           => strtoupper(trim((string)($item['MaHang'] ?? ''))),
                ':MA_PHU'              => $stt,
                ':TEN_HANG'            => $tenHang,
                ':NUOC_XX'             => strtoupper(trim((string)($item['XuatXu'] ?? 'VN'))),
                ':MA_DVT'              => $dvt,
                ':LUONG'               => $luong,
                ':LUONG2'              => $luong2,
                ':MA_DVT2'             => $dvt2,
                ':DGIA_KB'             => $donGia,
                ':DGIA_TT'             => $donGia,
                ':TRIGIA_KB'           => $triGia,
                ':TRIGIA_TT'           => $triGia,
                ':TGKB_VND'            => $triGia,
                ':TRIGIA_HDTM'         => $triGia,
                ':DGIA_HDTM'           => $donGia,
                ':MA_NT_DGIA_HDTM'     => 'VND',
                ':DVT_DGIA_HDTM'       => $dvt,
                ':MA_NT_TRIGIA_TT'     => 'VND',
                ':MA_NT_DGIA_TT'       => 'VND',
                ':DVT_DGIA_TT'         => $dvt,
                ':MA_NT_THUE_XNK'      => 'VND',
                ':THUE_XNK'            => 0,
                ':LOAI_HANG'           => 2,
                ':MA_NT_TRIGIA_TT_S'   => 'VND',
                ':TRIGIA_TT_S'         => $triGia,
            ]);
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Tên hàng luôn kết thúc bằng " hàng mới 100%#&VN"
     */
    private function buildTenHang(string $ten): string
    {
        $ten      = trim($ten);
        $suffix   = ' hàng mới 100%#&VN';
        $suffixAlt = 'hàng mới 100%';

        // Xóa suffix cũ nếu có (tránh duplicate)
        if (str_contains($ten, $suffixAlt)) {
            $ten = trim(str_replace($suffix,    '', $ten));
            $ten = trim(str_replace($suffixAlt, '', $ten));
            $ten = rtrim($ten, ' ,#&VN');
            $ten = trim($ten);
        }
        return $ten . $suffix;
    }

    /**
     * Format ngày → YYYY-MM-DD HH:MM:SS
     */
    private function formatDate(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return date('Y-m-d') . ' 00:00:00';
        }
        return date('Y-m-d', $ts) . ' 00:00:00';
    }

    // ================================================================
    // TRANSACTION WRAPPER
    // ================================================================
    public function import(array $headerData, array $items): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Insert header
            $tkId = $this->importHeader($headerData);
            if ($tkId <= 0) {
                throw new RuntimeException('Không lấy được ID tờ khai vừa tạo.');
            }

            // 2. Insert hàng hóa
            $maHq = strtoupper(trim((string)($headerData['CoQuanHaiQuan'] ?? '')));
            $this->importItems($tkId, $maHq, 'B11', '', $items);

            $this->pdo->commit();

            return [
                'success' => true,
                'tkId'    => $tkId,
                'message' => "Đã lưu tờ khai ID: $tkId với " . count($items) . " mặt hàng.",
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'tkId'    => 0,
                'message' => 'Lỗi: ' . $e->getMessage(),
            ];
        }
    }
}
