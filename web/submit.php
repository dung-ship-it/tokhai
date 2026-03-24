<?php
/**
 * submit.php
 * Nhận POST data từ upload.php, gọi EcusImporter::import(), hiển thị kết quả.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EcusImporter.php';

// ---- Chỉ chấp nhận POST ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ---- Đọc dữ liệu form ----
$formData = [];
$skipKeys = ['tmpFile', 'items'];
foreach ($_POST as $key => $value) {
    if (in_array($key, $skipKeys, true)) {
        continue;
    }
    $formData[$key] = is_string($value) ? trim($value) : $value;
}

// ---- Đọc danh sách hàng hóa ----
$rawItems = $_POST['items'] ?? [];
$items = [];
if (is_array($rawItems)) {
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        // Bỏ qua dòng trống hoàn toàn
        $nonEmpty = array_filter($item, fn($v) => trim((string)$v) !== '');
        if (!empty($nonEmpty)) {
            $items[] = $item;
        }
    }
}

// ---- Xóa file XML tạm ----
$tmpFile = trim($_POST['tmpFile'] ?? '');
$tmpPath = '';
if ($tmpFile !== '') {
    // Sanitize: chỉ cho phép ký tự an toàn trong tên file
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $tmpFile);
    if ($safeName !== '' && preg_match('/\.xml$/i', $safeName)) {
        $tmpPath = UPLOAD_DIR . $safeName;
    }
}

// ---- Gọi EcusImporter ----
$importer = new EcusImporter();
$result   = $importer->import($formData, $items);

// Xóa file tạm sau khi import (bất kể thành công hay thất bại)
if ($tmpPath !== '' && file_exists($tmpPath)) {
    @unlink($tmpPath);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kết quả nhập tờ khai — ECUS</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
  <header class="page-header">
    <h1>📦 Kết quả nhập tờ khai</h1>
  </header>

  <?php if ($result['success']): ?>
    <div class="alert alert-success">
      <strong>✅ Đã lưu tờ khai thành công!</strong><br>
      ID tờ khai: <strong><?= (int)$result['tokhai_id'] ?></strong> &nbsp;|&nbsp;
      Số dòng hàng đã lưu: <strong><?= (int)$result['inserted_items'] ?></strong>
    </div>
    <div class="form-actions">
      <a href="index.php" class="btn btn-primary">📄 Nhập tờ khai mới</a>
    </div>
  <?php else: ?>
    <div class="alert alert-danger">
      <strong>❌ Lỗi khi lưu tờ khai:</strong><br>
      <?= htmlspecialchars($result['error'] ?? 'Lỗi không xác định.', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <p class="note">Chi tiết lỗi đã được ghi vào error log của web server.</p>
    <div class="form-actions">
      <button type="button" class="btn btn-secondary" onclick="history.back()">← Quay lại chỉnh sửa</button>
      <a href="index.php" class="btn btn-secondary">🏠 Về trang chủ</a>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
