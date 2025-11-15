<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; padding: 20px; }
        .card { border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .form-control { border-radius: 8px; }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .alert { border-radius: 8px; border: none; }
        .upload-info { background: #f0f4ff; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .upload-info code { background: #fff; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container" style="max-width: 500px;">
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-file-upload"></i> UPLOAD FILE EXCEL</h4>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $type === 'success' ? 'check-circle' : ($type === 'warning' ? 'exclamation-triangle' : 'times-circle') ?>"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mt-4">
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="fas fa-file-excel"></i> Chọn file Excel</label>
                    <input type="file" name="file" accept=".xlsx,.xls" class="form-control" required>
                    <small class="text-muted">Định dạng: .xlsx hoặc .xls (Tối đa 200MB)</small>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="fas fa-arrow-up"></i> Upload & Import</button>
            </form>

            <div class="upload-info mt-4">
                <h6><i class="fas fa-list-ol"></i> <strong>Thứ tự upload:</strong></h6>
                <ol class="mb-0">
                    <li>File danh sách nhân viên: <code>DSach_NV_Công.xlsx</code></li>
                    <li>File báo cáo đơn hàng: <code>1.3 Báo cáo chi tiết đơn hàng_*.xlsx</code></li>
                </ol>
            </div>

            <a href="index.php?action=report" class="btn btn-secondary w-100 mt-3 fw-bold py-2"><i class="fas fa-arrow-left"></i> Quay Lại Báo Cáo</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>