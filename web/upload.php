<?php
/**
 * upload.php
 * Nhận file XML upload, parse, hiển thị form tờ khai để người dùng kiểm tra.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/XmlParser.php';

// ---- Xử lý upload ----
$error   = '';
$parsed  = null;
$tmpName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xmlFile'])) {
    $file = $_FILES['xmlFile'];

    // Validate
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File vượt quá giới hạn upload_max_filesize trong php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File vượt quá giới hạn MAX_FILE_SIZE trong form.',
            UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần.',
            UPLOAD_ERR_NO_FILE    => 'Không có file nào được upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tạm.',
            UPLOAD_ERR_CANT_WRITE => 'Không ghi được file lên đĩa.',
        ];
        $error = $uploadErrors[$file['error']] ?? 'Lỗi upload không xác định (code: ' . $file['error'] . ').';
    } elseif (!preg_match('/\.xml$/i', $file['name'])) {
        $error = 'Chỉ chấp nhận file có định dạng .xml.';
    } else {
        // Tạo thư mục uploads/ nếu chưa có
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        // Lưu file tạm với tên ngẫu nhiên
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $tmpName  = $safeBase . '_' . bin2hex(random_bytes(8)) . '.xml';
        $destPath = UPLOAD_DIR . $tmpName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $error = 'Không thể lưu file XML. Kiểm tra quyền ghi thư mục uploads/.';
        } else {
            try {
                $parsed = InvoiceXmlParser::parse($destPath);
            } catch (Throwable $e) {
                @unlink($destPath);
                $error = 'Lỗi parse XML: ' . htmlspecialchars($e->getMessage());
                $tmpName = '';
            }
        }
    }
} else {
    header('Location: index.php');
    exit;
}

// ---- Helper lấy giá trị đã parse từ XML ----
$h = $parsed['header'] ?? [];
$items = $parsed['items'] ?? [];

function hval(string $key, string $default = ''): string
{
    global $h;
    $v = $h[$key] ?? $default;
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function field(string $name, string $val, string $placeholder = '', string $extra = ''): string
{
    $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $v = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    return "<input type=\"text\" name=\"$n\" value=\"$v\" placeholder=\"$p\" class=\"form-input\" $extra>";
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Xem trước tờ khai — ECUS</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
  <header class="page-header">
    <h1>📋 Form tờ khai xuất khẩu</h1>
    <a href="index.php" class="btn btn-secondary">← Quay lại</a>
  </header>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      ❌ <?= $error ?>
      <br><a href="index.php" class="btn btn-secondary mt-1">← Quay lại</a>
    </div>
  <?php else: ?>

  <form method="POST" action="submit.php">
    <input type="hidden" name="tmpFile" value="<?= htmlspecialchars($tmpName, ENT_QUOTES, 'UTF-8') ?>">

    <!-- ===== NHÓM LOẠI HÌNH ===== -->
    <div class="form-section">
      <h3 class="section-title">Loại hình &amp; Cơ quan hải quan</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Mã loại hình</label>
          <select name="MaLoaiHinh" class="form-input">
            <?php
            $loaiHinhOpts = ['B11','B13','E11','E21','E31','A11','A12','A21','A31','G11','G12','H11','H21','H22'];
            $selectedLH  = $h['MaLoaiHinh'] ?? '';
            foreach ($loaiHinhOpts as $opt) {
                $sel = ($opt === $selectedLH) ? ' selected' : '';
                echo "<option value=\"$opt\"$sel>$opt</option>";
            }
            ?>
          </select>
        </div>
        <div class="form-group">
          <label>Cơ quan hải quan <span class="required">*</span></label>
          <?= field('CoQuanHaiQuan', '01B1', 'VD: 01B1') ?>
        </div>
        <div class="form-group">
          <label>Mã bộ phận xử lý tờ khai</label>
          <?= field('MaBoPhanXuLyToKhai', '00', 'VD: 00') ?>
        </div>
        <div class="form-group">
          <label>Ký hiệu &amp; số hiệu</label>
          <?= field('KyHieuVaSoHieu', '', '') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM NGƯỜI XUẤT KHẨU ===== -->
    <div class="form-section">
      <h3 class="section-title">Người xuất khẩu</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Mã số thuế <span class="required">*</span></label>
          <?= field('NguoiXuatKhau_Ma', $h['BenBanMaSoThue'] ?? '', 'MST người xuất khẩu') ?>
        </div>
        <div class="form-group form-group-wide">
          <label>Tên đơn vị</label>
          <?= field('NguoiXuatKhau_Ten', $h['BenBanTenDonVi'] ?? '', 'Tên công ty xuất khẩu') ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group form-group-full">
          <label>Địa chỉ</label>
          <?= field('NguoiXuatKhau_DiaChi', $h['BenBanDiaChi'] ?? '', 'Địa chỉ người xuất khẩu') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM NGƯỜI ỦY THÁC ===== -->
    <div class="form-section">
      <h3 class="section-title">Người ủy thác</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Mã người ủy thác</label>
          <?= field('NguoiUyThac_Ma', '', 'MST người ủy thác') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM NGƯỜI NHẬP KHẨU ===== -->
    <div class="form-section">
      <h3 class="section-title">Người nhập khẩu</h3>
      <div class="form-row">
        <div class="form-group form-group-wide">
          <label>Tên đơn vị <span class="required">*</span></label>
          <?= field('NguoiNhapKhau_Ten', $h['BenMuaTenDonVi'] ?? '', 'Tên công ty nhập khẩu') ?>
        </div>
        <div class="form-group">
          <label>Mã nước</label>
          <?= field('NguoiNhapKhau_MaNuoc', 'VN', 'VD: VN') ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group form-group-full">
          <label>Địa chỉ (dòng 1)</label>
          <?= field('NguoiNhapKhau_DiaChi1', $h['BenMuaDiaChi'] ?? '', 'Địa chỉ người nhập khẩu') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM VẬN ĐƠN ===== -->
    <div class="form-section">
      <h3 class="section-title">Vận đơn &amp; Địa điểm</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Số vận đơn</label>
          <?= field('SoVanDon', '', 'Số vận đơn') ?>
        </div>
        <div class="form-group">
          <label>Số lượng kiện</label>
          <?= field('SoLuongKien', '', '0') ?>
        </div>
        <div class="form-group">
          <label>Tổng trọng lượng (kg)</label>
          <?= field('TongTrongLuongHang', '', '0') ?>
        </div>
        <div class="form-group">
          <label>Phương thức vận chuyển</label>
          <?= field('MaHieuPhuongThucVanChuyen', '', 'VD: 10') ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Mã cảng nhận hàng</label>
          <?= field('DiaDiemNhanHangCuoiCung_Ma', 'VNZZZ', 'VD: VNZZZ') ?>
        </div>
        <div class="form-group form-group-wide">
          <label>Tên cảng nhận hàng</label>
          <?= field('DiaDiemNhanHangCuoiCung_Ten', '', 'Tên địa điểm nhận hàng') ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Mã cảng xếp hàng</label>
          <?= field('DiaDiemXepHang_Ma', '', 'VD: VNSGN') ?>
        </div>
        <div class="form-group form-group-wide">
          <label>Tên cảng xếp hàng</label>
          <?= field('DiaDiemXepHang_Ten', '', 'Tên địa điểm xếp hàng') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM HỢP ĐỒNG ===== -->
    <div class="form-section">
      <h3 class="section-title">Hợp đồng</h3>
      <div class="form-row">
        <div class="form-group form-group-wide">
          <label>Số hợp đồng</label>
          <?= field('SoHopDong', $h['SoHopDong'] ?? '', 'Số hợp đồng thương mại') ?>
        </div>
        <div class="form-group">
          <label>Ngày hợp đồng</label>
          <?= field('NgayHopDong', '', 'dd/mm/yyyy') ?>
        </div>
      </div>
    </div>

    <!-- ===== NHÓM HÓA ĐƠN ===== -->
    <div class="form-section">
      <h3 class="section-title">Hóa đơn</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Số hóa đơn</label>
          <?= field('SoHoaDon', $h['SoHoaDon'] ?? '', 'Số hóa đơn') ?>
        </div>
        <div class="form-group">
          <label>Ngày phát hành</label>
          <?= field('NgayPhatHanh', $h['NgayXuatHoaDon'] ?? '', 'dd/mm/yyyy') ?>
        </div>
        <div class="form-group">
          <label>Phương thức thanh toán</label>
          <?= field('PhuongThucThanhToan', $h['HinhThucThanhToan'] ?? '', 'VD: KC, TT') ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Tổng trị giá hóa đơn</label>
          <?= field('TongTriGiaHoaDon', (string)($h['TongTienThanhToan'] ?? ''), '0') ?>
        </div>
        <div class="form-group">
          <label>Mã đồng tiền</label>
          <?= field('MaDongTienCuaHoaDon', $h['DongTienThanhToan'] ?? 'VND', 'VND') ?>
        </div>
        <div class="form-group">
          <label>Số quản lý nội bộ</label>
          <?= field('SoQuanLyNoiBo', '', '') ?>
        </div>
        <div class="form-group">
          <label>Số tờ khai đầu tiên</label>
          <?= field('SoToKhaiDauTien', '', '') ?>
        </div>
      </div>
    </div>

    <!-- ===== DANH SÁCH HÀNG HÓA ===== -->
    <div class="form-section">
      <h3 class="section-title">Danh sách hàng hóa</h3>
      <div class="table-responsive">
        <table class="items-table" id="itemsTable">
          <thead>
            <tr>
              <th class="col-stt">STT</th>
              <th>Mã hàng</th>
              <th class="col-ten">Tên hàng</th>
              <th>Mã HS</th>
              <th>Xuất xứ</th>
              <th>Lượng</th>
              <th>ĐVT</th>
              <th>Lượng 2</th>
              <th>ĐVT 2</th>
              <th>Đơn giá HĐ</th>
              <th>Trị giá HĐ</th>
              <th class="col-action">Xóa</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $idx => $item): ?>
            <tr>
              <td class="col-stt"><?= $idx + 1 ?></td>
              <td><input type="text" name="items[<?= $idx ?>][MaHang]"
                    value="<?= htmlspecialchars((string)($item['MaHang'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-sm"></td>
              <td><input type="text" name="items[<?= $idx ?>][TenHang]"
                    value="<?= htmlspecialchars((string)($item['TenHang'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-md"></td>
              <td><input type="text" name="items[<?= $idx ?>][MaHS]"
                    value="" class="form-input input-sm" placeholder="Mã HS"></td>
              <td><input type="text" name="items[<?= $idx ?>][XuatXu]"
                    value="" class="form-input input-xs" placeholder="VN"></td>
              <td><input type="text" name="items[<?= $idx ?>][Luong]"
                    value="<?= htmlspecialchars((string)($item['SoLuong'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td><input type="text" name="items[<?= $idx ?>][DonViTinh]"
                    value="<?= htmlspecialchars((string)($item['DonViTinh'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-xs"></td>
              <td><input type="text" name="items[<?= $idx ?>][Luong2]"
                    value="" class="form-input input-num"></td>
              <td><input type="text" name="items[<?= $idx ?>][DonViTinh2]"
                    value="" class="form-input input-xs"></td>
              <td><input type="text" name="items[<?= $idx ?>][DonGiaHoaDon]"
                    value="<?= htmlspecialchars((string)($item['DonGia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td><input type="text" name="items[<?= $idx ?>][TriGiaHoaDon]"
                    value="<?= htmlspecialchars((string)($item['ThanhTien'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    class="form-input input-num"></td>
              <td class="col-action">
                <button type="button" class="btn-del" onclick="removeRow(this)" title="Xóa dòng">✕</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-secondary btn-sm mt-1" onclick="addRow()">+ Thêm dòng</button>
    </div>

    <!-- ===== ACTIONS ===== -->
    <div class="form-actions form-actions-bottom">
      <a href="index.php" class="btn btn-secondary">← Quay lại</a>
      <button type="submit" class="btn btn-success btn-lg">💾 Lưu vào ECUS</button>
    </div>

  </form>
  <?php endif; ?>
</div>

<script>
var itemCount = <?= count($items) ?>;

function addRow() {
  var idx = itemCount++;
  var tbody = document.querySelector('#itemsTable tbody');
  var tr = document.createElement('tr');
  tr.innerHTML =
    '<td class="col-stt">' + (tbody.rows.length + 1) + '</td>' +
    '<td><input type="text" name="items[' + idx + '][MaHang]" class="form-input input-sm"></td>' +
    '<td><input type="text" name="items[' + idx + '][TenHang]" class="form-input input-md"></td>' +
    '<td><input type="text" name="items[' + idx + '][MaHS]" class="form-input input-sm" placeholder="Mã HS"></td>' +
    '<td><input type="text" name="items[' + idx + '][XuatXu]" class="form-input input-xs" placeholder="VN"></td>' +
    '<td><input type="text" name="items[' + idx + '][Luong]" class="form-input input-num"></td>' +
    '<td><input type="text" name="items[' + idx + '][DonViTinh]" class="form-input input-xs"></td>' +
    '<td><input type="text" name="items[' + idx + '][Luong2]" class="form-input input-num"></td>' +
    '<td><input type="text" name="items[' + idx + '][DonViTinh2]" class="form-input input-xs"></td>' +
    '<td><input type="text" name="items[' + idx + '][DonGiaHoaDon]" class="form-input input-num"></td>' +
    '<td><input type="text" name="items[' + idx + '][TriGiaHoaDon]" class="form-input input-num"></td>' +
    '<td class="col-action"><button type="button" class="btn-del" onclick="removeRow(this)" title="Xóa dòng">✕</button></td>';
  tbody.appendChild(tr);
  renumberRows();
}

function removeRow(btn) {
  btn.closest('tr').remove();
  renumberRows();
}

function renumberRows() {
  var rows = document.querySelectorAll('#itemsTable tbody tr');
  rows.forEach(function (tr, i) {
    tr.querySelector('.col-stt').textContent = i + 1;
  });
}
</script>
</body>
</html>
