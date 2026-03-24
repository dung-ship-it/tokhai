<?php
/**
 * EcusImporter.php
 * Kết nối SQL Server ECUS5VNACCS và INSERT tờ khai vào DTOKHAIMD + DHANGMDDK.
 * Port từ _do_insert() trong gui_tokhai.py.
 */

require_once __DIR__ . '/config.php';

class EcusImporter
{
    /** @var resource|PDO|null */
    private $conn = null;

    // Giới hạn cứng cho các cột kiểu ntext / computed (CHARACTER_MAXIMUM_LENGTH = -1)
    // Nguồn: quan sát schema ECUS5VNACCS thực tế và yêu cầu đặc tả hệ thống.
    // Các cột này trả về -1 từ INFORMATION_SCHEMA nên cần hardcode giới hạn an toàn.
    private const HARD_LIMITS_TOKHAI = [
        '_Ten_DV_L1' => 500,   // ntext — tên đơn vị xuất khẩu (có dấu)
        '_DV_DT_L2'  => 800,   // ntext — địa chỉ đơn vị nhập khẩu dòng 2
        '_DV_DT_L3'  => 255,   // ntext — địa chỉ đơn vị nhập khẩu dòng 3/4
        'CANGNN'     => 60,    // tên cảng nhận hàng cuối cùng
    ];

    private const HARD_LIMITS_HANG = [
        'TEN_HANG' => 500,     // tên hàng hóa trong DHANGMDDK
    ];

    // -----------------------------------------------------------------------
    // Kết nối
    // -----------------------------------------------------------------------

    /**
     * Tạo kết nối đến SQL Server.
     * Ưu tiên extension sqlsrv; fallback sang PDO_SQLSRV.
     *
     * @return resource|PDO
     * @throws RuntimeException nếu kết nối thất bại.
     */
    public function connect()
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        if (function_exists('sqlsrv_connect')) {
            // ---- Microsoft sqlsrv extension ----
            $serverName = DB_SERVER;
            $connectionInfo = [
                'Database'              => DB_NAME,
                'UID'                   => DB_USER,
                'PWD'                   => DB_PASS,
                'CharacterSet'          => 'UTF-8',
                'TrustServerCertificate' => true,
            ];
            $conn = sqlsrv_connect($serverName, $connectionInfo);
            if ($conn === false) {
                $errors = sqlsrv_errors();
                $msg = $errors ? $errors[0]['message'] : 'Không rõ lỗi';
                throw new RuntimeException("sqlsrv_connect thất bại: $msg");
            }
            $this->conn = $conn;
        } elseif (class_exists('PDO')) {
            // ---- PDO fallback ----
            $drivers = PDO::getAvailableDrivers();
            if (in_array('sqlsrv', $drivers, true)) {
                $dsn = 'sqlsrv:Server=' . DB_SERVER . ';Database=' . DB_NAME;
            } elseif (in_array('odbc', $drivers, true)) {
                $dsn = 'odbc:Driver={SQL Server};Server=' . DB_SERVER . ';Database=' . DB_NAME;
            } else {
                throw new RuntimeException(
                    'Không tìm thấy PHP extension cho SQL Server. '
                    . 'Vui lòng cài php_sqlsrv hoặc php_pdo_sqlsrv. '
                    . 'Xem README.md để biết hướng dẫn.'
                );
            }
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException('PDO kết nối thất bại: ' . $e->getMessage());
            }
            $this->conn = $pdo;
        } else {
            throw new RuntimeException(
                'PHP extension sqlsrv hoặc PDO chưa được cài đặt. Xem README.md.'
            );
        }

        return $this->conn;
    }

    // -----------------------------------------------------------------------
    // Import chính
    // -----------------------------------------------------------------------

    /**
     * INSERT tờ khai vào DTOKHAIMD và DHANGMDDK trong một transaction.
     *
     * @param  array $formData  Dữ liệu form (key = tên field form).
     * @param  array $items     Danh sách hàng hóa, mỗi phần tử là array.
     * @return array ['success' => bool, 'tokhai_id' => int, 'inserted_items' => int]
     *               hoặc ['success' => false, 'error' => string]
     */
    public function import(array $formData, array $items): array
    {
        try {
            $conn = $this->connect();
            $this->beginTransaction($conn);

            // ---- Kiểm tra bảng ----
            $this->assertTableExists($conn, TABLE_TOKHAI);
            $this->assertTableExists($conn, TABLE_HANG);

            // ---- Lấy danh sách cột ----
            $hdrCols = $this->getColumns($conn, TABLE_TOKHAI);
            $detCols = $this->getColumns($conn, TABLE_HANG);

            // ---- Mapping form field → cột DB ----
            $HEADER_FIELD_TO_COL = [
                // 4 cột NOT NULL bắt buộc (MA_HQ, MA_LH, MA_DV, DV_DT)
                'CoQuanHaiQuan'                  => 'MA_HQ',
                'NguoiXuatKhau_Ma'               => 'MA_DV',
                'NguoiNhapKhau_Ten'              => 'DV_DT',
                'NguoiNhapKhau_Ma'               => '',        // bỏ qua

                // Tên & địa chỉ bên xuất khẩu
                'NguoiXuatKhau_Ten'              => '_Ten_DV_L1',
                'NguoiXuatKhau_DiaChi'           => 'DIA_CHI_DV',

                // Địa chỉ & mã nước người nhập khẩu
                'NguoiNhapKhau_DiaChi1'          => '_DV_DT_L2',
                'NguoiNhapKhau_DiaChi2'          => '_DV_DT_L3',
                'NguoiNhapKhau_DiaChi3'          => '_DV_DT_L3',
                'NguoiNhapKhau_DiaChi4'          => '_DV_DT_L3',
                'NguoiNhapKhau_MaNuoc'           => 'MA_NUOC_DT',

                // Địa điểm nhận / xếp hàng
                'DiaDiemNhanHangCuoiCung_Ma'     => 'MA_CANGNN',
                'DiaDiemNhanHangCuoiCung_Ten'    => 'CANGNN',
                'DiaDiemXepHang_Ma'              => 'MA_CANGXEP',
                'DiaDiemXepHang_Ten'             => 'CANGXEP',

                // Các cột khác
                'MaLoaiHinh'                     => 'MA_LH',
                'MaDongTienCuaHoaDon'            => 'MA_NT',
                'PhuongThucThanhToan'            => 'MA_PTTT',
                'SoToKhaiDauTien'                => 'SOTK_DAU_TIEN',
                'MaBoPhanXuLyToKhai'             => 'MA_BC_DV',
                'NguoiUyThac_Ma'                 => 'MA_DVUT',
                'MaHieuPhuongThucVanChuyen'      => 'MA_PTVT',
                'SoVanDon'                       => 'VAN_DON',
                'SoHoaDon'                       => 'SO_HD',
                'NgayPhatHanh'                   => 'NGAY_HD',
                'SoHopDong'                      => 'SO_HDTM',
                'KyHieuVaSoHieu'                 => 'KY_HIEU_SO_HIEU',
                'SoQuanLyNoiBo'                  => 'MA_KHACH_HANG',

                // Numeric
                'TongTriGiaHoaDon'               => 'TONGTGKB',
                'TongTrongLuongHang'             => 'TR_LUONG',
                'SoLuongKien'                    => 'SO_KIEN',
            ];

            // Remap field form → cột DB
            $hdrData = [];
            foreach ($formData as $formField => $value) {
                if (substr($formField, 0, 1) === '_') {
                    continue; // bỏ qua field nội bộ
                }
                $val = is_string($value) ? trim($value) : $value;
                $val = ($val === '') ? null : $val;

                $dbCol = $HEADER_FIELD_TO_COL[$formField] ?? $formField;
                if ($dbCol === '') {
                    continue; // bỏ qua
                }
                $hdrData[$dbCol] = $val;
            }

            // Chỉ giữ cột tồn tại trong bảng, bỏ identity
            $identityHdr = '_DToKhaiMDID';
            $insertableHdr = array_filter($hdrCols, fn($c) => $c !== $identityHdr);
            $rowHdr = array_filter(
                $hdrData,
                fn($k) => in_array($k, $insertableHdr, true) && $hdrData[$k] !== null,
                ARRAY_FILTER_USE_KEY
            );

            if (empty($rowHdr)) {
                throw new RuntimeException('Không có trường nào khớp với cột trong bảng ' . TABLE_TOKHAI . '.');
            }

            // ---- Truncate chuỗi vượt giới hạn cột ----
            $colLimits = $this->getColumnLengths($conn, TABLE_TOKHAI);
            // Merge giới hạn cứng cho cột ntext / computed
            $colLimits = array_merge($colLimits, self::HARD_LIMITS_TOKHAI);
            foreach ($rowHdr as $col => $val) {
                if (is_string($val) && isset($colLimits[$col])) {
                    $maxLen = $colLimits[$col];
                    if (mb_strlen($val, 'UTF-8') > $maxLen) {
                        $rowHdr[$col] = mb_substr($val, 0, $maxLen, 'UTF-8');
                    }
                }
            }

            // ---- Validate 4 cột bắt buộc NOT NULL ----
            foreach (['MA_HQ', 'MA_LH', 'MA_DV', 'DV_DT'] as $required) {
                if (empty($rowHdr[$required])) {
                    throw new RuntimeException(
                        "Cột bắt buộc '$required' không có giá trị. Vui lòng kiểm tra form."
                    );
                }
            }

            // ---- Hardcode cố định cho tờ khai xuất mới ----
            $now = date('Y-m-d H:i:s');
            $HARDCODE_HDR = [
                '_XorN'                => 'X',
                'MA_GH'                => 'DAP',
                'MA_NT'                => 'VND',
                'MA_NT_TY_GIA_VND'    => 'VND',
                'MA_PTTT'              => 'KC',
                'PPT_GTGT'             => 'A',
                'TR_LUONG'             => 1,
                'MA_THOI_HAN_NOP_THUE' => 'D',
                'MA_KHACH_HANG'        => '#&XKTC',
                'APP_NAME'             => 'ECUSK5NET',
                'NGUOINHAP'            => 'Root',
                'NGAYNHAP'             => $now,
                'NGAY_DK'              => $now,
                'IsVNACCS'             => 1,
                'IsVersion2'           => 1,
                'NHOMTK'               => 2,
                'PhienBan_TK'          => '1',
            ];
            foreach ($HARDCODE_HDR as $col => $val) {
                if (in_array($col, $insertableHdr, true)) {
                    $rowHdr[$col] = $val;
                }
            }

            // TONGTGTT = TONGTGKB
            if (isset($rowHdr['TONGTGKB']) && in_array('TONGTGTT', $insertableHdr, true)) {
                $rowHdr['TONGTGTT'] = $rowHdr['TONGTGKB'];
            }

            // Tờ khai mới: bỏ các cột này
            foreach (['TTTK', 'MA_NGHIEP_VU', 'PLUONG'] as $skip) {
                unset($rowHdr[$skip]);
            }

            // ---- Convert kiểu số ----
            $colTypes = $this->getColumnTypes($conn, TABLE_TOKHAI);
            $numericTypes = ['float','real','numeric','decimal','int','bigint','smallint','tinyint','money','smallmoney'];
            foreach ($rowHdr as $col => $val) {
                $dtype = strtolower($colTypes[$col] ?? '');
                if (in_array($dtype, $numericTypes, true) && is_string($val)) {
                    $stripped = trim($val);
                    if ($stripped === '') {
                        $rowHdr[$col] = null;
                    } else {
                        $num = str_replace(',', '.', $stripped);
                        if (is_numeric($num)) {
                            $intTypes = ['int','bigint','smallint','tinyint'];
                            $rowHdr[$col] = in_array($dtype, $intTypes, true)
                                ? (int)(float)$num
                                : (float)$num;
                        } else {
                            $rowHdr[$col] = null;
                        }
                    }
                }
            }

            // ---- INSERT DTOKHAIMD ----
            $newId = $this->insertRow($conn, TABLE_TOKHAI, $rowHdr);

            // ---- INSERT DHANGMDDK ----
            $FIELD_TO_COL_DET = [
                'MaHang'       => 'MA_HANG',
                'TenHang'      => 'TEN_HANG',
                'MaHS'         => 'Ma_HTS',
                'XuatXu'       => 'NUOC_XX',
                'Luong'        => 'LUONG',
                'DonViTinh'    => 'MA_DVT',
                'Luong2'       => 'LUONG2',
                'DonViTinh2'   => 'MA_DVT2',
                'DonGiaHoaDon' => 'DGIA_TT',
                'TriGiaHoaDon' => 'TRIGIA_TT',
            ];

            $colLimitsDet  = $this->getColumnLengths($conn, TABLE_HANG);
            $colLimitsDet  = array_merge($colLimitsDet, self::HARD_LIMITS_HANG);
            $colTypesDet   = $this->getColumnTypes($conn, TABLE_HANG);
            $identityDet   = '_DHangMDDKID';
            $fkDet         = '_DToKhaiMDID';
            $insertableDet = array_filter($detCols, fn($c) => $c !== $identityDet);

            $insertedItems = 0;
            foreach ($items as $idx => $item) {
                $itemData = [$fkDet => $newId, 'STTHANG' => $idx + 1];
                foreach ($FIELD_TO_COL_DET as $formField => $dbCol) {
                    $val = isset($item[$formField]) ? trim((string)$item[$formField]) : '';
                    $dtype = strtolower($colTypesDet[$dbCol] ?? '');
                    if (in_array($dtype, $numericTypes, true)) {
                        if ($val === '') {
                            $val = null;
                        } else {
                            $num = str_replace(',', '.', $val);
                            if (is_numeric($num)) {
                                $intTypes = ['int','bigint','smallint','tinyint'];
                                $val = in_array($dtype, $intTypes, true)
                                    ? (int)(float)$num
                                    : (float)$num;
                            } else {
                                $val = null;
                            }
                        }
                    } else {
                        $val = ($val === '') ? null : $val;
                        // Truncate
                        if (is_string($val) && isset($colLimitsDet[$dbCol])) {
                            $maxLen = $colLimitsDet[$dbCol];
                            if (mb_strlen($val, 'UTF-8') > $maxLen) {
                                $val = mb_substr($val, 0, $maxLen, 'UTF-8');
                            }
                        }
                    }
                    $itemData[$dbCol] = $val;
                }

                $rowDet = array_filter(
                    $itemData,
                    fn($k) => in_array($k, $insertableDet, true),
                    ARRAY_FILTER_USE_KEY
                );

                if (empty($rowDet)) {
                    continue;
                }

                $this->executeInsert($conn, TABLE_HANG, $rowDet);
                $insertedItems++;
            }

            $this->commitTransaction($conn);

            return [
                'success'        => true,
                'tokhai_id'      => $newId,
                'inserted_items' => $insertedItems,
            ];

        } catch (Throwable $e) {
            if (isset($conn)) {
                $this->rollbackTransaction($conn);
            }
            error_log('[EcusImporter] Lỗi: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers: database
    // -----------------------------------------------------------------------

    private function assertTableExists($conn, string $table): void
    {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?";
        $count = $this->queryScalar($conn, $sql, [$table]);
        if ((int)$count === 0) {
            throw new RuntimeException("Bảng '$table' không tồn tại trong database.");
        }
    }

    private function getColumns($conn, string $table): array
    {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
             . "WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION";
        return $this->queryColumn($conn, $sql, [$table]);
    }

    private function getColumnLengths($conn, string $table): array
    {
        $sql = "SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH "
             . "FROM INFORMATION_SCHEMA.COLUMNS "
             . "WHERE TABLE_NAME = ? "
             . "  AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL "
             . "  AND CHARACTER_MAXIMUM_LENGTH > 0";
        $rows = $this->queryRows($conn, $sql, [$table]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row[0]] = (int)$row[1];
        }
        return $result;
    }

    private function getColumnTypes($conn, string $table): array
    {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS "
             . "WHERE TABLE_NAME = ?";
        $rows = $this->queryRows($conn, $sql, [$table]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row[0]] = $row[1];
        }
        return $result;
    }

    /**
     * INSERT một hàng và trả về identity mới.
     */
    private function insertRow($conn, string $table, array $row): int
    {
        $this->executeInsert($conn, $table, $row);
        return $this->fetchLastIdentity($conn, $table);
    }

    private function executeInsert($conn, string $table, array $row): void
    {
        $cols = implode(', ', array_map(fn($c) => "[$c]", array_keys($row)));
        $vals = array_values($row);

        if ($conn instanceof PDO) {
            $placeholders = implode(', ', array_fill(0, count($vals), '?'));
            $stmt = $conn->prepare("INSERT INTO [$table] ($cols) VALUES ($placeholders)");
            $stmt->execute($vals);
        } else {
            // sqlsrv
            $placeholders = implode(', ', array_fill(0, count($vals), '?'));
            $sql = "INSERT INTO [$table] ($cols) VALUES ($placeholders)";
            $result = sqlsrv_query($conn, $sql, $vals);
            if ($result === false) {
                $errors = sqlsrv_errors();
                $msg = $errors ? $errors[0]['message'] : 'Không rõ lỗi';
                throw new RuntimeException("INSERT vào $table thất bại: $msg");
            }
        }
    }

    private function fetchLastIdentity($conn, string $table): int
    {
        // Thử lần lượt: SCOPE_IDENTITY → @@IDENTITY → IDENT_CURRENT
        // (port từ ecus_importer.py::_fetch_last_identity — tương thích SQL Server 2008+)
        $id = $this->queryScalar($conn, "SELECT SCOPE_IDENTITY()", []);
        if ($id === null) {
            $id = $this->queryScalar($conn, "SELECT @@IDENTITY", []);
        }
        if ($id === null) {
            $id = $this->queryScalar($conn, "SELECT IDENT_CURRENT(?)", [$table]);
        }
        if ($id === null) {
            throw new RuntimeException("Không lấy được identity sau khi INSERT vào '$table'.");
        }
        return (int)$id;
    }

    // -----------------------------------------------------------------------
    // Helpers: query
    // -----------------------------------------------------------------------

    private function queryScalar($conn, string $sql, array $params)
    {
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return $row ? $row[0] : null;
        } else {
            $stmt = sqlsrv_query($conn, $sql, $params ?: null);
            if ($stmt === false) {
                return null;
            }
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
            sqlsrv_free_stmt($stmt);
            return $row ? $row[0] : null;
        }
    }

    private function queryColumn($conn, string $sql, array $params): array
    {
        $rows = $this->queryRows($conn, $sql, $params);
        return array_column($rows, 0);
    }

    private function queryRows($conn, string $sql, array $params): array
    {
        $rows = [];
        if ($conn instanceof PDO) {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $rows[] = $row;
            }
        } else {
            $stmt = sqlsrv_query($conn, $sql, $params ?: null);
            if ($stmt === false) {
                return [];
            }
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmt);
        }
        return $rows;
    }

    // -----------------------------------------------------------------------
    // Helpers: transaction
    // -----------------------------------------------------------------------

    private function beginTransaction($conn): void
    {
        if ($conn instanceof PDO) {
            $conn->beginTransaction();
        } else {
            sqlsrv_begin_transaction($conn);
        }
    }

    private function commitTransaction($conn): void
    {
        if ($conn instanceof PDO) {
            $conn->commit();
        } else {
            sqlsrv_commit($conn);
        }
    }

    private function rollbackTransaction($conn): void
    {
        try {
            if ($conn instanceof PDO) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
            } else {
                sqlsrv_rollback($conn);
            }
        } catch (Throwable $e) {
            // Rollback thất bại — ghi log nhưng không ném ngoại lệ mới
            error_log('[EcusImporter] Rollback thất bại: ' . $e->getMessage());
        }
    }
}
