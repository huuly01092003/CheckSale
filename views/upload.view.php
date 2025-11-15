<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - Kiểm Soát Gian Lận</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container { max-width: 700px; }
        
        .card {
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border-radius: 12px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 25px;
        }
        
        .card-header h4 { margin: 0; font-weight: 700; }
        
        .card-body { padding: 30px; }
        
        .form-control { 
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn { 
            border-radius: 8px; 
            font-weight: 600; 
            padding: 12px; 
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .alert { 
            border-radius: 8px; 
            border: none; 
        }
        
        .upload-info {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid var(--primary);
        }
        
        .upload-info h6 { 
            font-weight: 700; 
            color: var(--primary); 
            margin-bottom: 15px; 
        }
        
        .upload-info ol { 
            margin-bottom: 0; 
            padding-left: 20px; 
        }
        
        .upload-info li { 
            margin-bottom: 8px; 
            color: #666; 
        }
        
        .upload-info code {
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            color: #e74c3c;
            font-weight: 600;
        }
        
        .performance-info {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #4caf50;
            font-size: 13px;
            color: #2e7d32;
        }
        
        .performance-info strong { color: #1b5e20; }
        
        .badge { 
            padding: 6px 12px; 
            border-radius: 20px; 
        }
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
            <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label fw-bold"><i class="fas fa-file-excel"></i> Chọn File</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control form-control-lg" required>
                    <small class="text-muted d-block mt-2">
                        📁 Định dạng: .xlsx, .xls, .csv | Tối đa: <?= Config::$max_upload_size ?>MB
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-arrow-up"></i> Upload & Import
                </button>
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
</body>
</html>