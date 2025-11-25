<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Dữ Liệu Giám Sát</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/upload.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .container { max-width: 700px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h4><i class="fas fa-file-upload"></i> IMPORT DỮ LIỆU GIÁM SÁT</h4>
        </div>

        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="uploadForm" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-file-csv"></i> Chọn File CSV</label>
                    <input type="file" id="fileInput" name="file" accept=".csv" 
                           class="form-control form-control-lg" required>
                    <small class="text-muted d-block mt-2">
                        📁 Định dạng: CSV | Tối đa: 500MB
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

            <!-- HƯỚNG DẪN -->
            <div class="upload-info" style="margin-top: 20px;">
                <h6><i class="fas fa-info-circle"></i> Cấu Trúc File CSV</h6>
                <p>File CSV phải có các cột theo thứ tự sau:</p>
                <ul style="font-size: 12px;">
                    <li><strong>B:</strong> Mã Đơn Vị Phân Phối</li>
                    <li><strong>C:</strong> Tên Đơn Vị Phân Phối</li>
                    <li><strong>F:</strong> Mã Nhân Viên</li>
                    <li><strong>G:</strong> Tên Nhân Viên</li>
                    <li><strong>H:</strong> Chức Vụ</li>
                    <li><strong>I:</strong> Mã Tuyến Bán Hàng</li>
                    <li><strong>J:</strong> Tên Tuyến Bán Hàng</li>
                    <li><strong>K:</strong> Ngày (dd/mm/yyyy)</li>
                    <li><strong>L:</strong> Thứ</li>
                    <li><strong>M:</strong> Thứ Tự Ghé Thăm</li>
                    <li><strong>N:</strong> Lộ Trình</li>
                    <li><strong>O:</strong> Mã Khách Hàng</li>
                    <li><strong>P:</strong> Tên Khách Hàng</li>
                    <li><strong>Q:</strong> Địa Chỉ</li>
                    <li><strong>R:</strong> Lần Ghé Thăm</li>
                    <li><strong>S:</strong> Kết Quả Ghé Thăm</li>
                    <li><strong>T:</strong> Thời Gian Bắt Đầu (HH:mm)</li>
                    <li><strong>U:</strong> Thời Gian Kết Thúc (HH:mm)</li>
                    <li><strong>V:</strong> Tổng Thời Gian (phút)</li>
                    <li><strong>AC:</strong> Tọa Độ Ghé Thăm Lat</li>
                    <li><strong>AD:</strong> Tọa Độ Ghé Thăm Lng</li>
                    <li><strong>AE:</strong> Tọa Độ Kết Thúc Lat</li>
                    <li><strong>AF:</strong> Tọa Độ Kết Thúc Lng</li>
                    <li><strong>AI:</strong> Tỉnh/Thành Phố</li>
                </ul>
            </div>

            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Lưu Ý:</strong>
                <br>• Dòng đầu tiên coi là header (tiêu đề)
                <br>• Dữ liệu trùng lặp sẽ bị bỏ qua
                <br>• Thời gian import có thể mất vài phút với file lớn
            </div>

            <!-- NÚT HÀNH ĐỘNG -->
            <div class="mt-3">
                <a href="index.php?action=giamsat" class="btn btn-secondary w-100">
                    <i class="fas fa-arrow-left"></i> Quay Lại
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/upload.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('fileInput');
    const submitBtn = document.getElementById('submitBtn');
    const filePreview = document.getElementById('filePreview');

    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.name.endsWith('.csv')) {
                const size = (file.size / 1024 / 1024).toFixed(2);
                filePreview.innerHTML = `
                    <div class="file-item">
                        <div class="file-item-info">
                            <div class="file-item-icon">📄</div>
                            <div>
                                <div class="file-item-name">${file.name}</div>
                                <div class="file-item-size">${size} MB</div>
                            </div>
                        </div>
                        <span class="badge bg-success">✓ OK</span>
                    </div>
                `;
                filePreview.classList.add('active');
                submitBtn.disabled = false;
            } else {
                filePreview.classList.remove('active');
                submitBtn.disabled = true;
            }
        });
    }
});
</script>
</body>
</html>