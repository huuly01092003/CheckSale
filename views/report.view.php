<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Kiểm Soát Gian Lận - Doanh Số Bán Hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/report.css">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px;">
<div class="container">
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h2><i class="fas fa-chart-bar"></i> KIỂM SOÁT GIAN LẬN BÁN HÀNG</h2>
        </div>
        
        <div class="card-body">
            <!-- Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Form Filter -->
            <form id="filterForm" method="get" class="filter-section">
                <input type="hidden" name="action" value="report">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select id="thang" name="thang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === $thang) ? 'selected' : '' ?>>
                                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" id="tuNgay" name="tu_ngay" class="form-control" 
                               value="<?= htmlspecialchars($tu_ngay) ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" id="denNgay" name="den_ngay" class="form-control" 
                               value="<?= htmlspecialchars($den_ngay) ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Rà Soát
                        </button>
                    </div>
                </div>
            </form>

            <!-- Tổng Quan -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-calendar-days"></i> Số Ngày</small>
                        <h5><?= intval($so_ngay) ?> ngày</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-money-bill-wave"></i> Tổng Tiền Kỳ</small>
                        <h5><?= Config::$currency ?><?= number_format($tong_tien_ky, 0) ?></h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-hourglass-half"></i> Tổng Tiền Khoảng</small>
                        <h5><?= Config::$currency ?><?= number_format($tong_tien_khoang, 0) ?></h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-exclamation-triangle"></i> Kết Quả Chung</small>
                        <h5><span class="badge bg-warning text-dark"><?= number_format($ket_qua_chung * 100, 2) ?>%</span></h5>
                    </div>
                </div>
            </div>

            <!-- Tỉ lệ Nghi Vấn -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-box">
                        <small><i class="fas fa-eye"></i> Tỉ Lệ Hoàn Thành Nghi Vấn (Kết quả chung × 1.5)</small>
                        <h5><span class="badge bg-danger"><?= number_format($ty_le_nghi_van * 100, 2) ?>%</span></h5>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box">
                        <small><i class="fas fa-user-secret"></i> Số Người Nghi Vấn Gian Lận</small>
                        <h5><span class="badge bg-danger" style="font-size: 18px;"><?= $tong_nghi_van ?> người</span></h5>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(90deg, #fee 0%, #fdd 100%); border-left-color: #dc3545;"></div>
                    <span><strong>Đỏ:</strong> Top <?= $top_threshold ?> Gian Lận Nghiêm Trọng</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: linear-gradient(90deg, #fff5e6 0%, #ffe6cc 100%); border-left-color: #ff9800;"></div>
                    <span><strong>Cam:</strong> Nghi Vấn Gian Lận Còn Lại (<?= max(0, $tong_nghi_van - $top_threshold) ?> người)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: white; border-left-color: #e0e0e0;"></div>
                    <span><strong>Trắng:</strong> Không Nghi Vấn (OK)</span>
                </div>
            </div>

            <!-- Bảng Báo Cáo -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 60px;">#</th>
                            <th style="width: 100px;">Mã NV</th>
                            <th>Tên Nhân Viên</th>
                            <th>Ngày Vào</th>
                            <th>Tỉnh</th>
                            <th>GS</th>
                            <th class="text-end">DS Tìm Kiếm</th>
                            <th class="text-end">DS Tiến Độ</th>
                            <th class="text-end">% Tiến Độ</th>
                            <th class="text-end">Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($report)): ?>
                        <?php foreach ($report as $r): ?>
                        <?php
                            if ($r['highlight_type'] === 'red') {
                                $row_class = 'bg-red-highlight';
                            } elseif ($r['highlight_type'] === 'orange') {
                                $row_class = 'bg-orange-highlight';
                            } else {
                                $row_class = 'bg-none-highlight';
                            }
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td class="text-center fw-bold">
                                <?php if ($r['rank'] > 0): ?>
                                    <span class="badge <?= ($r['highlight_type'] === 'red') ? 'bg-danger' : 'bg-warning text-dark' ?>">#<?= $r['rank'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($r['ma_nv']) ?></strong></td>
                            <td><?= htmlspecialchars($r['ten_nv'] ?? '') ?></td>
                            <td><?= $r['ngay_vao_cong_ty'] ? date('d/m/Y', strtotime($r['ngay_vao_cong_ty'])) : '-' ?></td>
                            <td><?= htmlspecialchars($r['tinh'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['gs'] ?? '') ?></td>
                            <td class="text-end"><?= Config::$currency ?><?= number_format($r['ds_tim_kiem'], 0) ?></td>
                            <td class="text-end"><?= Config::$currency ?><?= number_format($r['ds_tien_do'], 0) ?></td>
                            <td class="text-end">
                                <strong class="<?= ($r['ty_le'] >= $ty_le_nghi_van) ? 'text-danger' : 'text-success' ?>">
                                    <?= number_format($r['ty_le'] * 100, 2) ?>%
                                </strong>
                            </td>
                            <td class="text-end">
                                <?php if ($r['is_suspect']): ?>
                                    <span class="badge bg-danger">⚠️ NGHI VẤN</span>
                                <?php else: ?>
                                    <span class="badge bg-success">✅ OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group-custom">
                <a href="index.php?action=upload" class="btn btn-success">
                    <i class="fas fa-upload"></i> Upload File Mới
                </a>
                <a href="index.php?action=report" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Làm Mới
                </a>
                <button type="button" class="btn btn-info" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Xuất CSV
                </button>
            </div>

            <!-- Debug Info -->
            <?php if (!empty($debug_info)): ?>
            <div class="debug-info">
                <strong>📊 Thông Tin:</strong> <?= htmlspecialchars($debug_info) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/report.js"></script>
</body>
</html>