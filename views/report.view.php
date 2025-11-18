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
    <link rel="stylesheet" href="assets/css/kpi.css">
    <style>
        .kpi-table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            font-weight: 700;
            border: none;
            padding: 15px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .kpi-table tbody tr {
            border-bottom: 1px solid #e0e0e0ff;
            transition: background 0.2s;
        }
        
        .kpi-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .detail-metric {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-metric-label {
            font-weight: 600;
            color: #333;
            flex: 0 0 40%;
        }

        .detail-metric-value {
            flex: 0 0 60%;
            text-align: right;
            font-weight: 500;
        }

        .suspicion-detail h6 {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
    </style>
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
                        <small><i class="fas fa-money-bill-wave"></i> Tổng Tiền Kỳ (Tháng)</small>
                        <h5><?= Config::$currency ?><?= number_format($tong_tien_ky, 0) ?></h5>
                        <small class="text-muted">Chỉ tính tháng: <?= date('m/Y', strtotime($thang . '-01')) ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-hourglass-half"></i> Tổng Tiền Khoảng</small>
                        <h5><?= Config::$currency ?><?= number_format($tong_tien_khoang, 0) ?></h5>
                        <small class="text-muted"><?= $tu_ngay ?> ~ <?= $den_ngay ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box">
                        <small><i class="fas fa-exclamation-triangle"></i> Kết Quả Chung</small>
                        <h5><span class="badge bg-warning text-dark"><?= number_format($ket_qua_chung * 100, 2) ?>%</span></h5>
                        <small class="text-muted">Khoảng/Kỳ</small>
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
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">
                <table class="table table-hover kpi-table" style="margin-bottom: 0;">
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
                            <th class="text-center">Chi Tiết</th>
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
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" 
                                        type="button"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailModal"
                                        onclick="showReportDetails('<?= htmlspecialchars(json_encode($r)) ?>', '<?= htmlspecialchars(json_encode($tong_tien_ky_detailed)) ?>')">
                                    <i class="fas fa-eye"></i> Xem
                                </button>
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
                        <tr><td colspan="11" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group-custom">
                <a href="index.php?action=kpi_report" class="btn btn-info">
                    <i class="fas fa-chart-line"></i> Báo Cáo KPI
                </a>
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
            <div class="debug-info" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 20px;">
                <strong>📊 Thông Tin:</strong> <?= htmlspecialchars($debug_info) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="detailModalLabel">Chi Tiết Nhân Viên - <span id="modalEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent" style="max-height: 600px; overflow-y: auto;">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function showReportDetails(jsonData, jsonBenchmark) {
    try {
        const data = JSON.parse(jsonData);
        const benchmark = JSON.parse(jsonBenchmark);
        
        document.getElementById('modalEmpName').textContent = data.ten_nv + ' (' + data.ma_nv + ')';
        
        // ========== KHOẢNG THỜI GIAN ==========
        const dsTBKhoang_NV = data.ds_tien_do;
        const dsTBKhoang_Chung = benchmark.ds_tb_chung_khoang;
        const dsMaxKhoang_NV = data.ds_ngay_cao_nhat_nv_khoang;
        const dsMaxKhoang_Chung = benchmark.ds_ngay_cao_nhat_tb_khoang;
        
        // ========== THÁNG ==========
        const dsTBThang_NV = data.ds_tong_thang_nv;
        const dsTBThang_Chung = benchmark.ds_tb_chung_thang;
        const dsMaxThang_NV = data.ds_ngay_cao_nhat_nv_thang;
        const dsMaxThang_Chung = benchmark.ds_ngay_cao_nhat_tb_thang;
        
        // ========== NGÀY HOẠT ĐỘNG ==========
        const soNgayKhoang_NV = data.so_ngay_co_doanh_so_khoang || 0;
        const soNgayThang_NV = data.so_ngay_co_doanh_so_thang || 0;
        const soNgayTrongKhoang = benchmark.so_ngay || 1;
        const soNgayTrongThang = benchmark.so_ngay_trong_thang || 1;
        
        const formatCurrency = (val) => {
            if (isNaN(val) || val === 0) return '0 đ';
            return parseFloat(val).toLocaleString('vi-VN') + ' đ';
        };
        
        const calcPercent = (emp, system) => {
            if (system === 0 || isNaN(system)) return 0;
            return ((emp - system) / system * 100);
        };
        
        const getCompareIcon = (emp, system) => {
            return (emp >= system) ? '✅' : '⚠️';
        };
        
        const getCompareColor = (emp, system) => {
            return (emp >= system) ? '#28a745' : '#dc3545';
        };
        
        let html = `
            <div class="suspicion-detail mb-3">
                <h6 class="mb-3">
                    <i class="fas fa-user-circle"></i> Thông Tin Nhân Viên
                </h6>
                <div class="detail-metric">
                    <span class="detail-metric-label">Mã NV:</span>
                    <span class="detail-metric-value">${escapeHtml(data.ma_nv)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tên:</span>
                    <span class="detail-metric-value">${escapeHtml(data.ten_nv)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tỉnh:</span>
                    <span class="detail-metric-value">${escapeHtml(data.tinh || 'N/A')}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">GS:</span>
                    <span class="detail-metric-value">${escapeHtml(data.gs || 'N/A')}</span>
                </div>
            </div>

            <hr>

            <div class="suspicion-detail mb-3">
                <h6 class="mb-3">
                    <i class="fas fa-calendar-days"></i> So Sánh Trong Khoảng Thời Gian
                </h6>
                
                <div class="detail-metric">
                    <span class="detail-metric-label">📊 DS TB/Ngày (NV):</span>
                    <span class="detail-metric-value">${formatCurrency(dsTBKhoang_NV)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📊 DS TB/Ngày (Chung):</span>
                    <span class="detail-metric-value">${formatCurrency(dsTBKhoang_Chung)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📊 Chênh Lệch:</span>
                    <span class="detail-metric-value" style="color: ${getCompareColor(dsTBKhoang_NV, dsTBKhoang_Chung)};">
                        ${getCompareIcon(dsTBKhoang_NV, dsTBKhoang_Chung)} ${Math.abs(calcPercent(dsTBKhoang_NV, dsTBKhoang_Chung)).toFixed(1)}%
                    </span>
                </div>
                
                <div class="detail-metric mt-2">
                    <span class="detail-metric-label">🔝 DS Ngày Cao Nhất (NV):</span>
                    <span class="detail-metric-value">${formatCurrency(dsMaxKhoang_NV)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">🔝 DS Ngày Cao Nhất TB (Chung):</span>
                    <span class="detail-metric-value">${formatCurrency(dsMaxKhoang_Chung)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">🔝 Chênh Lệch:</span>
                    <span class="detail-metric-value" style="color: ${getCompareColor(dsMaxKhoang_NV, dsMaxKhoang_Chung)};">
                        ${getCompareIcon(dsMaxKhoang_NV, dsMaxKhoang_Chung)} ${Math.abs(calcPercent(dsMaxKhoang_NV, dsMaxKhoang_Chung)).toFixed(1)}%
                    </span>
                </div>
            </div>

            <hr>

            <div class="suspicion-detail mb-3">
                <h6 class="mb-3">
                    <i class="fas fa-calendar-alt"></i> So Sánh Trong Tháng
                </h6>
                
                <div class="detail-metric">
                    <span class="detail-metric-label">📋 DS Tháng (NV):</span>
                    <span class="detail-metric-value">${formatCurrency(dsTBThang_NV)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📋 DS TB/Ngày/NV (Chung):</span>
                    <span class="detail-metric-value">${formatCurrency(dsTBThang_Chung)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📋 % So Với Chung:</span>
                    <span class="detail-metric-value" style="color: ${getCompareColor(dsTBThang_NV, dsTBThang_Chung)};">
                        ${getCompareIcon(dsTBThang_NV, dsTBThang_Chung)} ${Math.abs(calcPercent(dsTBThang_NV, dsTBThang_Chung)).toFixed(1)}%
                    </span>
                </div>
                
                <div class="detail-metric mt-2">
                    <span class="detail-metric-label">🔝 DS Ngày Cao Nhất (NV-Tháng):</span>
                    <span class="detail-metric-value">${formatCurrency(dsMaxThang_NV)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">🔝 DS Ngày Cao Nhất TB (Chung-Tháng):</span>
                    <span class="detail-metric-value">${formatCurrency(dsMaxThang_Chung)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">🔝 Chênh Lệch:</span>
                    <span class="detail-metric-value" style="color: ${getCompareColor(dsMaxThang_NV, dsMaxThang_Chung)};">
                        ${getCompareIcon(dsMaxThang_NV, dsMaxThang_Chung)} ${Math.abs(calcPercent(dsMaxThang_NV, dsMaxThang_Chung)).toFixed(1)}%
                    </span>
                </div>
            </div>

            <hr>

            <div class="suspicion-detail">
                <h6 class="mb-3">
                    <i class="fas fa-calendar-check"></i> Ngày Hoạt Động
                </h6>
                <div class="detail-metric">
                    <span class="detail-metric-label">📅 Ngày Có Doanh Số (Khoảng):</span>
                    <span class="detail-metric-value"><strong>${soNgayKhoang_NV} / ${soNgayTrongKhoang} ngày</strong></span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📊 % Hoạt Động (Khoảng):</span>
                    <span class="detail-metric-value">${(soNgayTrongKhoang > 0 ? (soNgayKhoang_NV / soNgayTrongKhoang * 100) : 0).toFixed(1)}%</span>
                </div>
                
                <div class="detail-metric mt-2">
                    <span class="detail-metric-label">📅 Ngày Có Doanh Số (Tháng):</span>
                    <span class="detail-metric-value"><strong>${soNgayThang_NV} / ${soNgayTrongThang} ngày</strong></span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">📊 % Hoạt Động (Tháng):</span>
                    <span class="detail-metric-value">${(soNgayTrongThang > 0 ? (soNgayThang_NV / soNgayTrongThang * 100) : 0).toFixed(1)}%</span>
                </div>
            </div>
        `;
        
        document.getElementById('modalContent').innerHTML = html;
    } catch (e) {
        console.error('Error parsing data:', e);
        document.getElementById('modalContent').innerHTML = '<p class="text-danger"><strong>Lỗi tải dữ liệu:</strong> ' + e.message + '</p>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function exportToCSV() {
    alert('Chức năng xuất CSV sẽ được cập nhật');
}
</script>
</body>
</html>