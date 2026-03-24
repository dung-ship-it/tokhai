<?php
/**
 * index.php
 * Trang chủ — upload file XML hóa đơn điện tử.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nhập tờ khai ECUS — Upload XML</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
  <header class="page-header">
    <h1>📦 Nhập tờ khai xuất khẩu vào ECUS</h1>
    <p class="subtitle">Upload file XML hóa đơn điện tử để tạo tờ khai trong hệ thống ECUS5VNACCS</p>
  </header>

  <div class="card">
    <h2>🗂 Chọn file XML hóa đơn điện tử</h2>
    <form method="POST" action="upload.php" enctype="multipart/form-data">
      <div class="upload-area" id="uploadArea">
        <div class="upload-icon">📄</div>
        <p>Kéo thả file XML vào đây hoặc</p>
        <label for="xmlFile" class="btn btn-primary">Chọn file XML</label>
        <input type="file" id="xmlFile" name="xmlFile" accept=".xml" required style="display:none">
        <p class="file-hint" id="fileNameDisplay">Chưa chọn file nào</p>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-success btn-lg">🔍 Upload &amp; Xem trước</button>
      </div>
    </form>
  </div>

  <div class="card info-card">
    <h3>📋 Hướng dẫn sử dụng</h3>
    <ol>
      <li>Chọn file XML hóa đơn điện tử (định dạng XML theo chuẩn Việt Nam)</li>
      <li>Nhấn <strong>"Upload &amp; Xem trước"</strong> để hệ thống đọc và điền sẵn thông tin</li>
      <li>Kiểm tra lại các thông tin trên form tờ khai, chỉnh sửa nếu cần</li>
      <li>Nhấn <strong>"Lưu vào ECUS"</strong> để truyền tờ khai vào SQL Server ECUS5VNACCS</li>
    </ol>
    <p class="note">⚠️ Yêu cầu: web server phải cùng mạng với SQL Server ECUS5VNACCS. Xem <code>README.md</code> để biết hướng dẫn cài đặt.</p>
  </div>
</div>

<script>
// Hiển thị tên file sau khi chọn
document.getElementById('xmlFile').addEventListener('change', function () {
  var name = this.files.length ? this.files[0].name : 'Chưa chọn file nào';
  document.getElementById('fileNameDisplay').textContent = name;
});

// Drag & drop
var uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', function (e) {
  e.preventDefault();
  uploadArea.classList.add('dragover');
});
uploadArea.addEventListener('dragleave', function () {
  uploadArea.classList.remove('dragover');
});
uploadArea.addEventListener('drop', function (e) {
  e.preventDefault();
  uploadArea.classList.remove('dragover');
  var files = e.dataTransfer.files;
  if (files.length && files[0].name.match(/\.xml$/i)) {
    document.getElementById('xmlFile').files = files;
    document.getElementById('fileNameDisplay').textContent = files[0].name;
  }
});
</script>
</body>
</html>
