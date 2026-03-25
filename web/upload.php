<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

/**
 * Bỏ dấu tiếng Việt
 */
function removeAccents(string $str): string
{
    $from = [
        'à','á','ả','ã','ạ','ă','ắ','ặ','ằ','ẵ','ẳ','â','ấ','ầ','ẩ','ẫ','ậ',
        'è','é','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ',
        'ì','í','ỉ','ĩ','ị',
        'ò','ó','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ',
        'ù','ú','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự',
        'ý','ỳ','ỷ','ỹ','ỵ','đ',
        'À','Á','Ả','Ã','Ạ','Ă','Ắ','Ặ','Ằ','Ẵ','Ẳ','Â','Ấ','Ầ','Ẩ','Ẫ','Ậ',
        'È','É','Ẻ','Ẽ','Ẹ','Ê','Ế','Ề','Ể','Ễ','Ệ',
        'Ì','Í','Ỉ','Ĩ','Ị',
        'Ò','Ó','Ỏ','Õ','Ọ','Ô','Ố','Ồ','Ổ','Ỗ','Ộ','Ơ','Ớ','Ờ','Ở','Ỡ','Ợ',
        'Ù','Ú','Ủ','Ũ','Ụ','Ư','Ứ','Ừ','Ử','Ữ','Ự',
        'Ý','Ỳ','Ỷ','Ỹ','Ỵ','Đ',
    ];
    $to = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
        'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
        'E','E','E','E','E','E','E','E','E','E','E',
        'I','I','I','I','I',
        'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
        'U','U','U','U','U','U','U','U','U','U','U',
        'Y','Y','Y','Y','Y','D',
    ];
    return str_replace($from, $to, $str);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/XmlParser.php';

$error   = '';
$parsed  = null;
$tmpName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xmlFile'])) {
    $file = $_FILES['xmlFile'];
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File vượt quá giới hạn upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File vượt quá giới hạn MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần.',
        UPLOAD_ERR_NO_FILE    => 'Không có file nào được upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm.',
        UPLOAD_ERR_CANT_WRITE => 'Không ghi được file lên đĩa.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = $uploadErrors[$file['error']] ?? 'Lỗi upload không xác định.';
    } elseif (!preg_match('/\.xml$/i', $file['name'])) {
        $error = 'Chỉ chấp nhận file .xml.';
    } else {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_',
            pathinfo($file['name'], PATHINFO_FILENAME));
        $tmpName  = $safeBase . '_' . bin2hex(random_bytes(8)) . '.xml';
        $destPath = UPLOAD_DIR . $tmpName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $error = 'Không thể lưu file XML.';
        } else {
            try {
                $parsed = InvoiceXmlParser::parse($destPath);
            } catch (Throwable $e) {
                @unlink($destPath);
                $error   = 'Lỗi parse XML: ' . htmlspecialchars($e->getMessage());
                $tmpName = '';
            }
        }
    }
} else {
    header('Location: index.php');
    exit;
}

$h     = $parsed['header'] ?? [];
$items = $parsed['items']  ?? [];

// Hậu tố tên hàng — giống DB thực tế TK 4099
define('TEN_HANG_SUFFIX', ' hàng mới 100%#&VN');

/**
 * Đảm bảo tên hàng kết thúc đúng suffix
 */
function ensureSuffix(string $val): string
{
    $val = trim($val);
    if ($val === '') return '';
    if (str_contains($val, 'hàng mới 100%')) return $val;
    return $val . TEN_HANG_SUFFIX;
}

function fi(string $name, string $val, string $placeholder = '',
            string $class = 'form-input', string $extra = ''): string
{
    $n = htmlspecialchars($name,        ENT_QUOTES, 'UTF-8');
    $v = htmlspecialchars($val,         ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    return "<input type=\"text\" name=\"$n\" value=\"$v\""
         . " placeholder=\"$p\" class=\"$class\" $extra>";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xem trước tờ khai — ECUS</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .locked   { background:#f5f5f5!important; color:#888!important; cursor:default!important; }
    .unlocked { background:#fff9c4!important; color:#333!important; }
    .field-code  { font-size:.75em; color:#aaa; font-family:monospace; margin-left:4px; }
    .badge-fixed { font-size:.7em; background:#fff9c4; color:#f57f17;
                   border:1px solid #f9a825; border-radius:3px; padding:1px 4px; margin-left:4px; }
    .badge-input { font-size:.7em; background:#e3f2fd; color:#1565c0;
                   border:1px solid #90caf9; border-radius:3px; padding:1px 4px; margin-left:4px; }
    .badge-auto  { font-size:.7em; background:#e8f5e9; color:#2e7d32;
                   border:1px solid #a5d6a7; border-radius:3px; padding:1px 4px; margin-left:4px; }
    .tab-nav        { display:flex; gap:4px; margin-bottom:0; }
    .tab-nav button { padding:6px 14px; border:1px solid #ccc; border-bottom:none;
                      border-radius:4px 4px 0 0; background:#f5f5f5; cursor:pointer; font-size:.9em; }
    .tab-nav button.active { background:#fff; font-weight:bold; color:#1565c0; }
    .tab-panel        { display:none; border:1px solid #ccc; border-radius:0 4px 4px 4px; padding:12px; }
    .tab-panel.active { display:block; }
    .f2-hint { font-size:.65em; color:#aaa; display:block; margin-top:1px; }
    .suffix-hint { font-size:.7em; color:#f57f17; margin-left:6px; }
  </style>
</head>
<body>
<div class="container">
  <header class="page-header">
    <h1>📋 Form tờ khai xuất khẩu</h1>
    <a href="index.php" class="btn btn-secondary">← Quay lại</a>
  </header>

<?php if ($error): ?>
  <div class="alert alert-danger">
    ❌ <?= htmlspecialchars($error) ?>
    <br><a href="index.php" class="btn btn-secondary mt-1">← Quay lại</a>
  </div>
<?php else: ?>

<form method="POST" action="submit.php">
  <input type="hidden" name="tmpFile"
         value="<?= htmlspecialchars($tmpName, ENT_QUOTES, 'UTF-8') ?>">

  <!-- TAB NAV -->
  <div class="tab-nav">
    <button type="button" class="active"
            onclick="switchTab('tab-chung',this)">📋 Thông tin chung</button>
    <button type="button"
            onclick="switchTab('tab-container',this)">🚛 Container</button>
    <button type="button"
            onclick="switchTab('tab-hang',this)">📦 Danh sách hàng</button>
  </div>

  <!-- ============================================================ -->
  <!-- TAB 1: THÔNG TIN CHUNG                                       -->
  <!-- ============================================================ -->
  <div id="tab-chung" class="tab-panel active">

    <!-- THÔNG TIN CHUNG -->
    <div class="form-section">
      <h3 class="section-title">Thông tin chung</h3>
      <div class="form-row">

        <div class="form-group">
          <label>Cơ quan hải quan <span class="required">*</span>
            <small class="field-code">[MA_HQ]</small>
            <span class="badge-input">Điền tay</span></label>
          <?= fi('CoQuanHaiQuan', '', 'VD: 18B1') ?>
        </div>

        <div class="form-group">
          <label>Mã loại hình
            <small class="field-code">[MA_LH]</small>
            <span class="badge-fixed">🔒 B11</span></label>
          <input type="text" class="form-input locked" value="B11" readonly>
          <input type="hidden" name="MaLoaiHinh" value="B11">
        </div>

        <div class="form-group">
          <label>Nhóm hồ sơ
            <small class="field-code">[NHOM_HO_SO]</small>
            <span class="badge-input">Điền tay</span></label>
          <?= fi('NhomHoSo', '', 'VD: 1') ?>
        </div>

        <div class="form-group">
          <label>Mã PTVT
            <small class="field-code">[MA_PTVT]</small>
            <span class="badge-input">Điền tay</span></label>
          <?= fi('MaPhuongTienVanChuyen', '9', 'VD: 9') ?>
        </div>

        <div class="form-group">
          <label>Mã bộ phận xử lý
            <small class="field-code">[MA_BC_DV]</small></label>
          <?= fi('MaBoPhanXuLyToKhai', '', 'VD: 00') ?>
        </div>

      </div>
    </div>

    <!-- NGƯỜI XUẤT KHẨU -->
    <div class="form-section">
      <h3 class="section-title">Người xuất khẩu (Công ty bán)</h3>
      <div class="form-row">

        <div class="form-group">
          <label>Mã số thuế người bán <span class="required">*</span>
            <small class="field-code">[MA_DV]</small></label>
          <?= fi('NguoiXuatKhau_Ma',
                 (string)($h['BenBanMaSoThue'] ?? ''),
                 'MST người xuất khẩu') ?>
        </div>

        <div class="form-group form-group-wide">
          <label>Tên Công ty Bán — có dấu
            <small class="field-code">[_Ten_DV_L1]</small></label>
          <?= fi('NguoiXuatKhau_Ten',
                 (string)($h['BenBanTenDonVi'] ?? ''),
                 'Tên đầy đủ có dấu') ?>
        </div>

      </div>
      <div class="form-row">
        <div class="form-group form-group-full">
          <label>Địa chỉ công ty bán — có dấu
            <small class="field-code">[DIA_CHI_DV]</small></label>
          <?= fi('NguoiXuatKhau_DiaChi',
                 (string)($h['BenBanDiaChi'] ?? ''),
                 'Địa chỉ đầy đủ có dấu') ?>
        </div>
      </div>
    </div>

    <!-- NGƯỜI ỦY THÁC -->
    <div class="form-section">
      <h3 class="section-title">Người ủy thác xuất khẩu</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Mã người ủy thác
            <small class="field-code">[MA_DVUT]</small></label>
          <?= fi('NguoiUyThac_Ma', '', 'MST người ủy thác') ?>
        </div>
      </div>
    </div>

    <!-- NGƯỜI NHẬP KHẨU -->
    <div class="form-section">
      <h3 class="section-title">Người nhập khẩu (Công ty mua)</h3>
      <div class="form-row">

        <div class="form-group form-group-wide">
          <label>Tên công ty mua — không dấu <span class="required">*</span>
            <small class="field-code">[DV_DT]</small>
            <span class="badge-auto">✏️ tự động bỏ dấu</span></label>
          <?= fi('NguoiNhapKhau_Ten',
                 removeAccents((string)($h['BenMuaTenDonVi'] ?? '')),
                 'Tên không dấu',
                 'form-input no-accent-input') ?>
        </div>

        <div class="form-group">
          <label>Mã nước
            <small class="field-code">[NUOC_NK]</small>
            <span class="badge-fixed">🔒 VN</span></label>
          <input type="text" class="form-input locked" value="VN" readonly>
          <input type="hidden" name="NguoiNhapKhau_MaNuoc" value="VN">
        </div>

      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Địa chỉ dòng 1 (≤20 ký tự)
            <small class="field-code">[_DV_DT_L2]</small></label>
          <?= fi('NguoiNhapKhau_DiaChi1',
                 (string)($h['BenMuaDiaChi'] ?? ''),
                 'Tối đa 20 ký tự', 'form-input', 'maxlength="20"') ?>
        </div>
        <div class="form-group">
          <label>Địa chỉ dòng 2 (≤20 ký tự)
            <small class="field-code">[_DV_DT_L3]</small></label>
          <?= fi('NguoiNhapKhau_DiaChi2', '', 'Tối đa 20 ký tự', 'form-input', 'maxlength="20"') ?>
        </div>
        <div class="form-group">
          <label>Địa chỉ dòng 3 (≤20 ký tự)
            <small class="field-code">[_DV_DT_L4]</small></label>
          <?= fi('NguoiNhapKhau_DiaChi3', '', 'Tối đa 20 ký tự', 'form-input', 'maxlength="20"') ?>
        </div>
      </div>
    </div>

    <!-- VẬN ĐƠN & ĐỊA ĐIỂM -->
    <div class="form-section">
      <h3 class="section-title">Vận đơn &amp; Địa điểm</h3>
      <div class="form-row">

        <div class="form-group">
          <label>Số vận đơn
            <small class="field-code">[VAN_DON]</small></label>
          <?= fi('SoVanDon', '', 'Số vận đơn') ?>
        </div>

        <div class="form-group">
          <label>Số lượng kiện
            <small class="field-code">[SO_KIEN]</small></label>
          <?= fi('SoLuongKien',
                 (string)($h['SoLuongKien'] ?? ''),
                 '0') ?>
        </div>

        <div class="form-group">
          <label>ĐVT kiện
            <small class="field-code">[DVT_KIEN]</small>
            <span class="badge-fixed">🔒 PK</span></label>
          <input type="text" class="form-input locked" value="PK" readonly>
          <input type="hidden" name="DvtKien" value="PK">
        </div>

        <div class="form-group">
          <label>Tổng trọng lượng (kg)
            <small class="field-code">[TR_LUONG]</small></label>
          <?= fi('TongTrongLuongHang',
                 (string)($h['TongTrongLuong'] ?? ''),
                 '0') ?>
        </div>

        <div class="form-group">
          <label>ĐVT trọng lượng
            <small class="field-code">[DVT_TR_LUONG]</small>
            <span class="badge-fixed">🔒 KGM</span></label>
          <input type="text" class="form-input locked" value="KGM" readonly>
          <input type="hidden" name="DvtTrongLuong" value="KGM">
        </div>

      </div>
      <div class="form-row">

        <div class="form-group">
          <label>Mã cảng nhận hàng
            <small class="field-code">[MA_CANGNN]</small>
            <span class="badge-fixed">🔒 VNZZZ</span></label>
          <input type="text" class="form-input locked" value="VNZZZ" readonly>
          <input type="hidden" name="DiaDiemNhanHangCuoiCung_Ma" value="VNZZZ">
        </div>

        <div class="form-group form-group-wide">
          <label>Tên cảng nhận — Tên cty mua (không dấu)
            <small class="field-code">[CANGNN]</small>
            <span class="badge-auto">✏️ tự động bỏ dấu</span></label>
          <?= fi('DiaDiemNhanHangCuoiCung_Ten',
                 removeAccents((string)($h['BenMuaTenDonVi'] ?? '')),
                 'Tên công ty mua không dấu',
                 'form-input no-accent-input') ?>
        </div>

      </div>
      <div class="form-row">

        <div class="form-group">
          <label>Mã cảng xếp hàng
            <small class="field-code">[MA_CK]</small>
            <span class="badge-fixed">🔒 VNZZZ</span></label>
          <input type="text" class="form-input locked" value="VNZZZ" readonly>
          <input type="hidden" name="DiaDiemXepHang_Ma" value="VNZZZ">
        </div>

        <div class="form-group form-group-wide">
          <label>Tên cảng xếp — Tên cty bán (không dấu)
            <small class="field-code">[TEN_CK]</small>
            <span class="badge-auto">✏️ tự động bỏ dấu</span></label>
          <?= fi('DiaDiemXepHang_Ten',
                 removeAccents((string)($h['BenBanTenDonVi'] ?? '')),
                 'Tên công ty bán không dấu',
                 'form-input no-accent-input') ?>
        </div>

      </div>
      <div class="form-row">

        <div class="form-group">
          <label>Phương tiện vận tải
            <small class="field-code">[TEN_PTVT]</small>
            <span class="badge-fixed">🔒 TRUCK</span></label>
          <input type="text" class="form-input locked" value="TRUCK" readonly>
          <input type="hidden" name="TenPhuongTienVanTai" value="TRUCK">
        </div>

        <div class="form-group">
          <label>Ngày hàng đi dự kiến
            <small class="field-code">[NGAYDEN]</small></label>
          <?= fi('NgayHangDiDuKien', date('Y-m-d'), 'YYYY-MM-DD') ?>
        </div>

      </div>
    </div>

    <!-- HÓA ĐƠN -->
    <div class="form-section">
      <h3 class="section-title">Thông tin hóa đơn</h3>
      <div class="form-row">

        <div class="form-group">
          <label>Mã loại hóa đơn
            <small class="field-code">[MA_HDTM]</small>
            <span class="badge-fixed">🔒 B</span></label>
          <input type="text" class="form-input locked" value="B" readonly>
          <input type="hidden" name="PhanLoaiHinhThucHoaDon" value="B">
        </div>

        <div class="form-group">
          <label>Số hóa đơn — theo PXK
            <small class="field-code">[SO_HDTM]</small></label>
          <?= fi('SoHoaDon',
                 (string)($h['SoHoaDon'] ?? ''),
                 'Số phiếu xuất kho') ?>
        </div>

        <div class="form-group">
          <label>Ngày phát hành — theo PXK
            <small class="field-code">[NGAY_HDTM]</small></label>
          <?= fi('NgayPhatHanh',
                 (string)($h['NgayXuatHoaDon'] ?? ''),
                 'YYYY-MM-DD') ?>
        </div>

        <div class="form-group">
          <label>Mã phân loại giá HĐ
            <small class="field-code">[MA_PL_GIA_HDTM]</small>
            <span class="badge-fixed">🔒 A</span></label>
          <input type="text" class="form-input locked" value="A" readonly>
          <input type="hidden" name="MaPhanLoaiGiaHoaDon" value="A">
        </div>

      </div>
      <div class="form-row">

        <div class="form-group">
          <label>Tổng trị giá HĐ
            <small class="field-code">[TONGTG_HDTM]</small></label>
          <?= fi('TongTriGiaHoaDon',
                 (string)($h['TongTienThanhToan'] ?? ''),
                 '0') ?>
        </div>

        <div class="form-group">
          <label>Tổng trị giá tính thuế
            <small class="field-code">[TONGTGTT]</small></label>
          <?= fi('TriGiaTinhThue',
                 (string)($h['TongTienThanhToan'] ?? ''),
                 '0') ?>
        </div>

        <div class="form-group">
          <label>Mã đồng tiền
            <small class="field-code">[MA_NT]</small>
            <span class="badge-fixed">🔒 VND</span></label>
          <input type="text" class="form-input locked" value="VND" readonly>
          <input type="hidden" name="MaDongTienCuaHoaDon" value="VND">
        </div>

      </div>
    </div>

    <!-- THUẾ & BẢO LÃNH -->
    <div class="form-section">
      <h3 class="section-title">Thuế &amp; Bảo lãnh</h3>
      <div class="form-row">

        <div class="form-group">
          <label>Người nộp thuế
            <small class="field-code">[THUE]</small>
            <span class="badge-fixed">🔒 1</span></label>
          <input type="text" class="form-input locked" value="1" readonly>
          <input type="hidden" name="NguoiNopThue" value="1">
        </div>

        <div class="form-group">
          <label>Thời hạn nộp thuế
            <small class="field-code">[MA_THOI_HAN_NOP_THUE]</small>
            <span class="badge-fixed">🔒 D</span></label>
          <input type="text" class="form-input locked" value="D" readonly>
          <input type="hidden" name="MaXacDinhThoiHanNopThue" value="D">
        </div>

      </div>
    </div>

    <!-- THÔNG TIN KHÁC -->
    <div class="form-section">
      <h3 class="section-title">Thông tin khác</h3>
      <div class="form-row">

        <div class="form-group form-group-wide">
          <label>Ghi chú duyệt
            <small class="field-code">[GhiChuDuyet]</small>
            <span class="badge-input">Điền tay</span></label>
          <?= fi('PhanGhiChu', '', 'Ghi chú nếu có') ?>
        </div>

        <div class="form-group">
          <label>Số quản lý nội bộ
            <small class="field-code">[SoHSTK]</small>
            <span class="badge-fixed">🔒 #&amp;XKTC</span></label>
          <input type="text" class="form-input locked" value="#&amp;XKTC" readonly>
          <input type="hidden" name="SoQuanLyNoiBo" value="#&XKTC">
        </div>

        <div class="form-group">
          <label>Số tờ khai đầu tiên
            <small class="field-code">[SOTK_DAU_TIEN]</small></label>
          <?= fi('SoToKhaiDauTien', '', '') ?>
        </div>

      </div>
    </div>

  </div><!-- /tab-chung -->

  <!-- ============================================================ -->
  <!-- TAB 2: CONTAINER                                             -->
  <!-- ============================================================ -->
  <div id="tab-container" class="tab-panel">
    <div class="form-section">
      <h3 class="section-title">Địa điểm xếp hàng lên xe</h3>
      <p style="font-size:.85em;color:#555;margin-bottom:8px;">
        <strong>Mã điểm</strong> = <code>(MA_HQ) + OZZ</code> —
        VD: MA_HQ = <code>18B1</code> → <code>18B1OZZ</code>
      </p>
      <div class="form-row">

        <div class="form-group">
          <label>Mã điểm xếp hàng lên xe
            <small class="field-code">[DDIEM_KH]</small>
            <span class="badge-auto">🔗 tự động từ MA_HQ</span></label>
          <?= fi('MaDiemXepHangLenXe', '', 'VD: 18B1OZZ') ?>
        </div>

        <div class="form-group form-group-wide">
          <label>Tên điểm xếp hàng — Tên cty bán (không dấu)
            <small class="field-code">[DIA_DIEM_GIAO_HANG]</small>
            <span class="badge-auto">✏️ tự động bỏ dấu</span></label>
          <?= fi('TenDiemXepHangLenXe',
                 removeAccents((string)($h['BenBanTenDonVi'] ?? '')),
                 'Tên công ty bán không dấu',
                 'form-input no-accent-input') ?>
        </div>

      </div>
      <div class="form-row">
        <div class="form-group form-group-full">
          <label>Địa chỉ điểm xếp hàng — Địa chỉ cty bán (không dấu)
            <small class="field-code">[Noi_Dung_Chuyen_Cua_Khau]</small>
            <span class="badge-auto">✏️ tự động bỏ dấu</span></label>
          <?= fi('DiaChiDiemXepHangLenXe',
                 removeAccents((string)($h['BenBanDiaChi'] ?? '')),
                 'Địa chỉ công ty bán không dấu',
                 'form-input no-accent-input') ?>
        </div>
      </div>
    </div>
  </div><!-- /tab-container -->

  <!-- ============================================================ -->
  <!-- TAB 3: DANH SÁCH HÀNG HÓA                                   -->
  <!-- ============================================================ -->
  <div id="tab-hang" class="tab-panel">
    <div class="form-section">
      <h3 class="section-title">Danh sách hàng hóa</h3>
      <p style="font-size:.85em;color:#555;margin-bottom:8px;">
        💡 Tên hàng tự thêm <strong>"hàng mới 100%#&amp;VN"</strong> khi rời ô &nbsp;|&nbsp;
        Lượng 2 &amp; ĐVT 2 tự đồng bộ từ ô 1 &nbsp;|&nbsp;
        <kbd>F2</kbd> để sửa tay / khóa lại
      </p>
      <div class="table-responsive">
        <table class="items-table" id="itemsTable">
          <thead>
            <tr>
              <th class="col-stt">STT</th>
              <th>Mã HS<br><small class="field-code">MA_HANGKB</small></th>
              <th class="col-ten">Tên hàng<br><small class="field-code">TEN_HANG</small></th>
              <th>Xuất xứ<br><small class="field-code">NUOC_XX</small></th>
              <th>Lượng<br><small class="field-code">LUONG</small></th>
              <th>ĐVT<br><small class="field-code">MA_DVT</small></th>
              <th>Lượng 2<br><small class="field-code">LUONG2</small>
                <span class="f2-hint">F2=sửa</span></th>
              <th>ĐVT 2<br><small class="field-code">MA_DVT2</small>
                <span class="f2-hint">F2=sửa</span></th>
              <th>Đơn giá HĐ<br><small class="field-code">DGIA_TT</small></th>
              <th>Trị giá HĐ<br><small class="field-code">TRIGIA_TT</small></th>
              <th class="col-action">Xóa</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $idx => $item):
              $tenHang = ensureSuffix((string)($item['TenHang'] ?? ''));
              $luong   = (string)($item['SoLuong'] ?? '');
              $dvt     = (string)($item['DonViTinh'] ?? '');
          ?>
            <tr>
              <td class="col-stt"><?= $idx + 1 ?></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][MaHang]"
                    data-col="MaHang"
                    value="<?= htmlspecialchars((string)($item['MaHang'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-sm"
                    placeholder="Mã HS"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][TenHang]"
                    data-col="TenHang"
                    value="<?= htmlspecialchars($tenHang, ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-md"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][XuatXu]"
                    data-col="XuatXu"
                    value="<?= htmlspecialchars((string)($item['XuatXu'] ?? 'VN'), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-xs" placeholder="VN"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][Luong]"
                    data-col="Luong"
                    value="<?= htmlspecialchars($luong, ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][DonViTinh]"
                    data-col="DonViTinh"
                    value="<?= htmlspecialchars($dvt, ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-xs"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][Luong2]"
                    data-col="Luong2"
                    value="<?= htmlspecialchars($luong, ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num locked" readonly
                    title="Nhấn F2 để sửa"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][DonViTinh2]"
                    data-col="DonViTinh2"
                    value="<?= htmlspecialchars($dvt, ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-xs locked" readonly
                    title="Nhấn F2 để sửa"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][DonGiaHoaDon]"
                    data-col="DonGiaHoaDon"
                    value="<?= htmlspecialchars((string)($item['DonGia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td><input type="text"
                    name="items[<?= $idx ?>][TriGiaHoaDon]"
                    data-col="TriGiaHoaDon"
                    value="<?= htmlspecialchars((string)($item['ThanhTien'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td class="col-action">
                <button type="button" class="btn-del"
                        onclick="removeRow(this)" title="Xóa dòng">✕</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-secondary btn-sm mt-1"
              onclick="addRow()">+ Thêm dòng</button>
    </div>
  </div><!-- /tab-hang -->

  <!-- ACTIONS -->
  <div class="form-actions" style="margin-top:12px;">
    <a href="index.php" class="btn btn-secondary">← Quay lại</a>
    <button type="submit" class="btn btn-success btn-lg">💾 Lưu vào ECUS</button>
  </div>

</form>
<?php endif; ?>
</div><!-- /container -->

<script>
// ================================================================
// TAB SWITCHING
// ================================================================
function switchTab(id, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p) {
    p.classList.remove('active');
  });
  document.querySelectorAll('.tab-nav button').forEach(function(b) {
    b.classList.remove('active');
  });
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
}

// ================================================================
// BỎ DẤU TIẾNG VIỆT (JS)
// ================================================================
var accentsMap = {
  'à':'a','á':'a','ả':'a','ã':'a','ạ':'a',
  'ă':'a','ắ':'a','ặ':'a','ằ':'a','ẵ':'a','ẳ':'a',
  'â':'a','ấ':'a','ầ':'a','ẩ':'a','ẫ':'a','ậ':'a',
  'è':'e','é':'e','ẻ':'e','ẽ':'e','ẹ':'e',
  'ê':'e','ế':'e','ề':'e','ể':'e','ễ':'e','ệ':'e',
  'ì':'i','í':'i','ỉ':'i','ĩ':'i','ị':'i',
  'ò':'o','ó':'o','ỏ':'o','õ':'o','ọ':'o',
  'ô':'o','ố':'o','ồ':'o','ổ':'o','ỗ':'o','ộ':'o',
  'ơ':'o','ớ':'o','ờ':'o','ở':'o','ỡ':'o','ợ':'o',
  'ù':'u','ú':'u','ủ':'u','ũ':'u','ụ':'u',
  'ư':'u','ứ':'u','ừ':'u','ử':'u','ữ':'u','ự':'u',
  'ý':'y','ỳ':'y','ỷ':'y','ỹ':'y','ỵ':'y','đ':'d',
  'À':'A','Á':'A','Ả':'A','Ã':'A','Ạ':'A',
  'Ă':'A','Ắ':'A','Ặ':'A','Ằ':'A','Ẵ':'A','Ẳ':'A',
  'Â':'A','Ấ':'A','Ầ':'A','Ẩ':'A','Ẫ':'A','Ậ':'A',
  'È':'E','É':'E','Ẻ':'E','Ẽ':'E','Ẹ':'E',
  'Ê':'E','Ế':'E','Ề':'E','Ể':'E','Ễ':'E','Ệ':'E',
  'Ì':'I','Í':'I','Ỉ':'I','Ĩ':'I','Ị':'I',
  'Ò':'O','Ó':'O','Ỏ':'O','Õ':'O','Ọ':'O',
  'Ô':'O','Ố':'O','Ồ':'O','Ổ':'O','Ỗ':'O','Ộ':'O',
  'Ơ':'O','Ớ':'O','Ờ':'O','Ở':'O','Ỡ':'O','Ợ':'O',
  'Ù':'U','Ú':'U','Ủ':'U','Ũ':'U','Ụ':'U',
  'Ư':'U','Ứ':'U','Ừ':'U','Ử':'U','Ữ':'U','Ự':'U',
  'Ý':'Y','Ỳ':'Y','Ỷ':'Y','Ỹ':'Y','Ỵ':'Y','Đ':'D'
};

function stripAccents(str) {
  return str.replace(/[^\u0000-\u007E]/g, function(c) {
    return accentsMap[c] || c;
  });
}

document.querySelectorAll('.no-accent-input').forEach(function(el) {
  el.value = stripAccents(el.value);
  el.addEventListener('input', function() {
    var pos = el.selectionStart;
    el.value = stripAccents(el.value);
    try { el.setSelectionRange(pos, pos); } catch(e) {}
  });
});

// ================================================================
// TỰ ĐỘNG ĐIỀN MÃ ĐIỂM XẾP HÀNG = MA_HQ + OZZ
// ================================================================
var maHqEl = document.querySelector('[name="CoQuanHaiQuan"]');
if (maHqEl) {
  maHqEl.addEventListener('input', function() {
    var maHq    = this.value.trim().toUpperCase();
    var maField = document.querySelector('[name="MaDiemXepHangLenXe"]');
    if (maField && maField.value === '') {
      maField.value = maHq ? maHq + 'OZZ' : '';
    }
  });
}

// ================================================================
// BẢNG HÀNG HÓA
// ================================================================
var itemCount  = <?= count($items) ?>;
var TEN_SUFFIX = ' hàng mới 100%#&VN';

function ensureSuffix(val) {
  val = val.trim();
  if (val === '') return '';
  if (val.indexOf('hàng mới 100%') !== -1) return val;
  return val + TEN_SUFFIX;
}

function setLocked(el, locked) {
  if (locked) {
    el.classList.add('locked');
    el.classList.remove('unlocked');
    el.readOnly = true;
    el.title    = 'Nhấn F2 để sửa';
  } else {
    el.classList.remove('locked');
    el.classList.add('unlocked');
    el.readOnly = false;
    el.title    = 'Nhấn F2 để khóa lại';
  }
}

function bindRowEvents(tr) {
  var tenEl   = tr.querySelector('[data-col="TenHang"]');
  var luongEl = tr.querySelector('[data-col="Luong"]');
  var dvtEl   = tr.querySelector('[data-col="DonViTinh"]');
  var l2El    = tr.querySelector('[data-col="Luong2"]');
  var dvt2El  = tr.querySelector('[data-col="DonViTinh2"]');

  // Tên hàng: thêm suffix khi blur
  if (tenEl) {
    tenEl.addEventListener('blur', function() {
      if (this.value.trim() !== '') {
        this.value = ensureSuffix(this.value);
      }
    });
    if (tenEl.value.trim() !== '') {
      tenEl.value = ensureSuffix(tenEl.value);
    }
  }

  // Lượng 1 → Lượng 2
  if (luongEl && l2El) {
    luongEl.addEventListener('input', function() {
      if (!l2El.dataset.unlocked) l2El.value = this.value;
    });
    if (!l2El.dataset.unlocked && l2El.value === '')
      l2El.value = luongEl.value;
  }

  // ĐVT 1 → ĐVT 2
  if (dvtEl && dvt2El) {
    dvtEl.addEventListener('input', function() {
      if (!dvt2El.dataset.unlocked) dvt2El.value = this.value;
    });
    if (!dvt2El.dataset.unlocked && dvt2El.value === '')
      dvt2El.value = dvtEl.value;
  }

  // F2 mở khóa / khóa lại
  [l2El, dvt2El].forEach(function(el) {
    if (!el) return;
    if (!el.dataset.unlocked) setLocked(el, true);

    el.addEventListener('keydown', function(e) {
      if (e.key !== 'F2') return;
      e.preventDefault();
      if (!el.dataset.unlocked) {
        el.dataset.unlocked = '1';
        setLocked(el, false);
        el.focus(); el.select();
      } else {
        delete el.dataset.unlocked;
        setLocked(el, true);
        if (el.dataset.col === 'Luong2'     && luongEl) el.value = luongEl.value;
        if (el.dataset.col === 'DonViTinh2' && dvtEl)   el.value = dvtEl.value;
      }
    });
  });
}

// Khởi tạo tất cả dòng
document.querySelectorAll('#itemsTable tbody tr').forEach(function(tr) {
  bindRowEvents(tr);
});

// Thêm dòng mới
function addRow() {
  var idx   = itemCount++;
  var tbody = document.querySelector('#itemsTable tbody');
  var tr    = document.createElement('tr');
  tr.innerHTML =
    '<td class="col-stt">' + (tbody.rows.length + 1) + '</td>' +
    '<td><input type="text" name="items['+idx+'][MaHang]"'+
         ' data-col="MaHang" class="form-input input-sm" placeholder="Mã HS"></td>' +
    '<td><input type="text" name="items['+idx+'][TenHang]"'+
         ' data-col="TenHang" class="form-input input-md"></td>' +
    '<td><input type="text" name="items['+idx+'][XuatXu]"'+
         ' data-col="XuatXu" class="form-input input-xs" value="VN"></td>' +
    '<td><input type="text" name="items['+idx+'][Luong]"'+
         ' data-col="Luong" class="form-input input-num"></td>' +
    '<td><input type="text" name="items['+idx+'][DonViTinh]"'+
         ' data-col="DonViTinh" class="form-input input-xs"></td>' +
    '<td><input type="text" name="items['+idx+'][Luong2]"'+
         ' data-col="Luong2" class="form-input input-num locked"'+
         ' readonly title="Nhấn F2 để sửa"></td>' +
    '<td><input type="text" name="items['+idx+'][DonViTinh2]"'+
         ' data-col="DonViTinh2" class="form-input input-xs locked"'+
         ' readonly title="Nhấn F2 để sửa"></td>' +
    '<td><input type="text" name="items['+idx+'][DonGiaHoaDon]"'+
         ' data-col="DonGiaHoaDon" class="form-input input-num"></td>' +
    '<td><input type="text" name="items['+idx+'][TriGiaHoaDon]"'+
         ' data-col="TriGiaHoaDon" class="form-input input-num"></td>' +
    '<td class="col-action"><button type="button" class="btn-del"'+
         ' onclick="removeRow(this)" title="Xóa dòng">✕</button></td>';
  tbody.appendChild(tr);
  bindRowEvents(tr);
  renumberRows();
}

function removeRow(btn) {
  btn.closest('tr').remove();
  renumberRows();
}

function renumberRows() {
  document.querySelectorAll('#itemsTable tbody tr').forEach(function(tr, i) {
    tr.querySelector('.col-stt').textContent = i + 1;
  });
}
</script>
</body>
</html>
