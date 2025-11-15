<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - Kiểm Soát Gian Lận</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/upload.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container { max-width: 700px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h4><i class="fas fa-file-upload"></i> UPLOAD FILE EXCEL/CSV</h4>
        </div>
        
        <div class="card-body">
            <!-- Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <form id="uploadForm" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-file-excel"></i> Chọn File</label>
                    <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv" 
                           class="form-control form-control-lg" required>
                    <small class="text-muted d-block mt-2">
                        📁 Định dạng: .xlsx, .xls, .csv | Tối đa: <?= Config::$max_upload_size ?>MB
                    </small>
                    <div id="filePreview" class="file-preview"></div>
                </div>
                
                <button type="submit" id="submitBtn" class="btn btn-primary w-100 btn-lg" disabled>
                    <i class="fas fa-arrow-up"></i> Upload & Import
                </button>

                <div id="progressBar" class="progress-bar-upload">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%">
                            Đang xử lý...
                        </div>
                    </div>
                </div>
            </form>

            <!-- Thứ Tự Import -->
            <div class="upload-info">
                <h6><i class="fas fa-list-check"></i> Thứ Tự Import</h6>
                <ol class="mb-0">
                    <li>Import <code>Danh sách nhân viên</code> trước</li>
                    <li>Sau đó import <code>Báo cáo đơn hàng</code></li>
                    <li>Chọn tháng và khoảng ngày để rà soát</li>
                </ol>
            </div>

            <!-- Performance Info -->
            <div class="performance-info">
                <strong>⚡ Tối ưu Performance:</strong>
                <br>💡 <strong>CSV</strong>: Nhanh hơn 10x, tiết kiệm 80% RAM, thời gian 1-2 phút
                <br>📊 <strong>XLSX</strong>: Fallback, thời gian 5-10 phút, ~2GB RAM
                <br>💾 Khuyến nghị: Export sang CSV từ Excel (File → Save As → Format: CSV)
            </div>

            <!-- Tên File Hợp Lệ -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Tên File Hợp Lệ:</strong>
                <br>📄 Danh sách nhân viên: <code>DSach_NV_Công...</code> hoặc <code>DSNV...</code>
                <br>📊 Báo cáo đơn hàng: <code>1.3...</code> hoặc <code>Báo cáo...</code>
            </div>

            <!-- Back Button -->
            <a href="index.php?action=report" class="btn btn-secondary w-100 mt-3">
                <i class="fas fa-arrow-left"></i> Quay Lại Báo Cáo
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/upload.js"></script>
</body>
</html>