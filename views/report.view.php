<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Kiểm Soát Doanh Số</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; }
        .card { border: none; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border-radius: 12px; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px 12px 0 0; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .info-box { background: white; border-left: 5px solid #667eea; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .info-box small { color: #666; font-weight: 600; display: block; margin-bottom: 8px; }
        .info-box h5 { margin: 0; font-weight: 700; }
        .highlight { background-color: #fff3cd; }
        table { font-size: 14px; }
        th { background: #f8f9fa; font-weight: 700; color: #333; }
        td { vertical-align: middle; }
        .badge-success { background: #10b981; }
        .badge-warning { background: #f59e0b; }
        h2 { color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-control { border-radius: 8px; border: 1px solid #ddd; }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="card mt-4">
        <div class="card-header">
            <h2 class="mb-0"><i class="fas fa-chart-bar"></i> BÁO CÁO KIỂM SOÁT DOANH SỐ</h2>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form chọn ngày -->
            <form method="get" class="row g-3 mb-4 align-items-end">
                <input type="hidden" name="action" value="report">
                <div class="col-md-5">
                    <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ ngày</label>
                    <input type="text" name="tu_ngay" class="form-control flatpickr" value="<?= htmlspecialchars($tu_ngay ?? '') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến ngày</label>
                    <input type="text" name="den_ngay" class="form-control flatpickr" value="<?= htmlspecialchars($den_ngay ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Xem</button>
                </div>
            </form>

            <!-- Tổng quan -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="info-box">
                        <small><i class="fas fa-calendar-days"></i> Số ngày</small>
                        <h5><?= intval($so_ngay) ?> ngày</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <small><i class="fas fa-exclamation-triangle"></i> Tỉ lệ nghi vấn</small>
                        <h5><span class="badge badge-success"><?= number_format($ty_le_nghi_van * 100, 2) ?>%</span></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box">
                        <small><i class="fas fa-chart-line"></i> Kết quả chung</small>
                        <h5><span class="badge badge-warning"><?= number_format($ket_qua_chung * 100, 2) ?>%</span></h5>
                    </div>
                </div>
            </div>

            <!-- Bảng -->
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Mã NV</th>
                            <th>Tên Nhân Viên</th>
                            <th>Ngày Vào Công Ty</th>
                            <th>Tỉnh</th>
                            <th>GS</th>
                            <th class="text-end">DS Tìm Kiếm</th>
                            <th class="text-end">DS Tiến Độ</th>
                            <th class="text-end">% Tiến Độ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($report) > 0): ?>
                        <?php foreach ($report as $r): ?>
                        <tr class="<?= $r['ty_le'] > $ty_le_nghi_van ? 'highlight' : '' ?>">
                            <td><strong><?= htmlspecialchars($r['ma_nv']) ?></strong></td>
                            <td><?= htmlspecialchars($r['ten_nv']) ?></td>
                            <td><?= $r['ngay_vao_cong_ty'] ? date('d/m/Y', strtotime($r['ngay_vao_cong_ty'])) : '-' ?></td>
                            <td><?= htmlspecialchars($r['tinh'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['gs'] ?? '') ?></td>
                            <td class="text-end"><?= number_format($r['ds_tim_kiem'], 0) ?></td>
                            <td class="text-end"><?= number_format($r['ds_tien_do'], 0) ?></td>
                            <td class="text-end"><strong><?= number_format($r['ty_le'] * 100, 2) ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex gap-2">
                <a href="index.php?action=upload" class="btn btn-success"><i class="fas fa-upload"></i> Upload File Mới</a>
                <a href="index.php?action=report" class="btn btn-secondary"><i class="fas fa-sync"></i> Làm Mới</a>
            </div>
            
            <?php if (!empty($debug_info)): ?>
            <div class="alert alert-info mt-3" style="font-size: 12px;">
                <strong>Debug:</strong> <?= htmlspecialchars($debug_info) ?>
                <br><strong>Khoảng thời gian:</strong> <?= htmlspecialchars($tu_ngay) ?> → <?= htmlspecialchars($den_ngay) ?>
                <br><strong>Tổng tiền kỳ:</strong> <?= number_format($total_lay, 0) ?> | <strong>Tổng tiền khoảng:</strong> <?= number_format($total_xem, 0) ?>
                <br><strong>Số nhân viên có dữ liệu:</strong> <?= count($report) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vi.js"></script>
<script>
    flatpickr(".flatpickr", { 
        dateFormat: "Y-m-d", 
        locale: "vi",
        altFormat: "d/m/Y",
        altInput: true
    });
</script>
</body>
</html>