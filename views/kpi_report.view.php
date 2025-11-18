<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo KPI - Phát Hiện Gian Lận</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/report.css">
    <link rel="stylesheet" href="assets/css/kpi.css">
</head>
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

.kpi-table thead.table-light th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    background-color: transparent !important;
}

.kpi-table thead tr {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
</style>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px;">
<div class="container-fluid">
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> PHÂN TÍCH KPI - PHÁT HIỆN GIAN LẬN</h2>
        </div>
        
        <div class="card-body">
            <!-- Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <form id="kpiFilterForm" method="get" class="filter-section">
                <input type="hidden" name="action" value="kpi_report">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select id="thang" name="thang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === $filters['thang']) ? 'selected' : '' ?>>
                                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" id="tuNgay" name="tu_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['tu_ngay']) ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" id="denNgay" name="den_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['den_ngay']) ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-cube"></i> Lọc Sản Phẩm (2 ký tự)</label>
                        <select id="productFilter" name="product_filter" class="form-select">
                            <option value="">-- Tất Cả Sản Phẩm --</option>
                            <?php 
                            if (!isset($available_products)) $available_products = [];
                            foreach ($available_products as $prod): 
                            ?>
                                <option value="<?= htmlspecialchars($prod) ?>" 
                                        <?= ($prod === $filters['product_filter']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prod) ?> - Sản phẩm
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc Dữ Liệu
                        </button>
                        <a href="index.php?action=kpi_report" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Statistics Cards -->
            <div class="row mt-4 mb-4">
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-info">
                        <div class="kpi-icon"><i class="fas fa-users"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Nhân Viên Có ĐH</div>
                            <div class="kpi-value"><?= $statistics['employees_with_orders'] ?></div>
                            <div class="kpi-subtext">/ <?= $statistics['total_employees'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="kpi-card kpi-card-primary">
                        <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Tổng Đơn Hàng</div>
                            <div class="kpi-value"><?= number_format($statistics['total_orders']) ?></div>
                            <div class="kpi-subtext">đơn</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="kpi-card kpi-card-success">
                        <div class="kpi-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">TBD Chung</div>
                            <div class="kpi-value"><?= number_format($statistics['avg_orders_per_emp'], 1) ?></div>
                            <div class="kpi-subtext">đơn/NV</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="kpi-card kpi-card-warning">
                        <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Cảnh Báo</div>
                            <div class="kpi-value"><?= $statistics['warning_count'] ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="kpi-card kpi-card-danger">
                        <div class="kpi-icon"><i class="fas fa-virus"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Nguy Hiểm</div>
                            <div class="kpi-value"><?= $statistics['danger_count'] ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-2">
                    <div class="kpi-card kpi-card-light">
                        <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Bình Thường</div>
                            <div class="kpi-value"><?= $statistics['normal_count'] ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Benchmark Info -->
            <div class="benchmark-box">
                <div class="row">
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📊 TBD Cao Nhất (Chung):</strong>
                            <span class="badge bg-success"><?= number_format($statistics['max_daily_orders'], 1) ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📊 TBD Chung:</strong>
                            <span class="badge bg-info"><?= number_format($statistics['avg_daily_orders'], 2) ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📈 Độ Biến Động (Std Dev):</strong>
                            <span class="badge bg-warning text-dark"><?= number_format($statistics['std_dev_orders'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="kpi-legend">
                <div class="legend-item">
                    <span class="legend-badge" style="background: #dc3545;"></span>
                    <strong>🚨 Nguy Hiểm (70-100):</strong> Cần kiểm tra ngay
                </div>
                <div class="legend-item">
                    <span class="legend-badge" style="background: #ffc107;"></span>
                    <strong>⚠️ Cảnh Báo (40-69):</strong> Cần theo dõi
                </div>
                <div class="legend-item">
                    <span class="legend-badge" style="background: #28a745;"></span>
                    <strong>✅ Bình Thường (0-39):</strong> Hoạt động bình thường
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-responsive mt-4">
                <table class="table table-hover kpi-table">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 60px;">Mức Độ</th>
                            <th style="width: 100px;">Mã NV</th>
                            <th>Tên Nhân Viên</th>
                            <th class="text-end">Tổng ĐH</th>
                            <th class="text-end">TBD/Ngày</th>
                            <th class="text-end">Max/Ngày</th>
                            <th class="text-end">Min/Ngày</th>
                            <th class="text-center">Xu Hướng</th>
                            <th class="text-center">Consistency</th>
                            <th class="text-end">Điểm KPI</th>
                            <th class="text-center">Chi Tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($kpi_data)): ?>
                        <?php foreach ($kpi_data as $item): ?>
                        <?php
                            $badge_class = 'bg-success';
                            $icon = '✅';
                            if ($item['kpi_level'] === 'warning') {
                                $badge_class = 'bg-warning text-dark';
                                $icon = '⚠️';
                            } elseif ($item['kpi_level'] === 'danger') {
                                $badge_class = 'bg-danger';
                                $icon = '🚨';
                            }
                            
                            $trend_icon = '→';
                            $trend_text = 'ổn định';
                            if ($item['trend'] === 'increasing') {
                                $trend_icon = '📈';
                                $trend_text = 'tăng';
                            } elseif ($item['trend'] === 'decreasing') {
                                $trend_icon = '📉';
                                $trend_text = 'giảm';
                            }
                        ?>
                        <tr>
                            <td class="text-center">
                                <span class="badge <?= $badge_class ?>" title="<?= ucfirst($item['kpi_level']) ?>">
                                    <?= $icon ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($item['ma_nv']) ?></strong></td>
                            <td><?= htmlspecialchars($item['ten_nv']) ?></td>
                            <td class="text-end fw-bold"><?= number_format($item['total_orders']) ?></td>
                            <td class="text-end"><?= number_format($item['avg_daily_orders'], 1) ?></td>
                            <td class="text-end text-success"><strong><?= $item['max_day_orders'] ?></strong></td>
                            <td class="text-end text-muted"><?= $item['min_day_orders'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark" title="<?= $trend_text ?>">
                                    <?= $trend_icon ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info"><?= round($item['consistency_score'], 0) ?>%</span>
                            </td>
                            <td class="text-end">
                                <span style="display: inline-block; padding: 6px 12px; border-radius: 4px; color: white; font-weight: bold; background: 
                                    <?= ($item['kpi_level'] === 'danger') ? '#dc3545' : 
                                        (($item['kpi_level'] === 'warning') ? '#ffc107' : '#28a745') ?>;">
                                    <?= $item['kpi_score'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailModal"
                                        onclick="showKPIDetails('<?= htmlspecialchars(json_encode($item)) ?>')">
                                    <i class="fas fa-eye"></i> Chi Tiết
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Export Buttons -->
            <div class="btn-group-custom">
                <a href="index.php?action=report" class="btn btn-info">
                    <i class="fas fa-chart-bar"></i> Báo Cáo Bán Hàng
                </a>
                <a href="index.php?action=upload" class="btn btn-success">
                    <i class="fas fa-upload"></i> Upload File
                </a>
                <button type="button" class="btn btn-warning" onclick="exportKPIToCSV()">
                    <i class="fas fa-download"></i> Xuất CSV
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> In Báo Cáo
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">
                    <i class="fas fa-chart-pie"></i> Chi Tiết KPI - <span id="modalEmpName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalContent" style="max-height: 700px; overflow-y: auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/report.js"></script>
<script src="assets/js/kpi.js"></script>

<script>
/**
 * Hiển thị chi tiết KPI trong modal
 * FIX: Xử lý đúng Consistency, KPI Score, và các tiêu chí
 */
function showKPIDetails(jsonData) {
    try {
        const data = JSON.parse(jsonData);
        const breakdown = data.score_breakdown || {};
        const reasons = data.kpi_reasons || [];
        
        document.getElementById('modalEmpName').textContent = data.ten_nv + ' (' + data.ma_nv + ')';
        
        const getTrendIcon = (trend) => {
            return trend === 'increasing' ? '📈 Tăng' : 
                   trend === 'decreasing' ? '📉 Giảm' : 
                   '→ Ổn định';
        };
        
        const getScoreColor = (score) => {
            if (score >= 70) return '#dc3545';
            if (score >= 40) return '#ffc107';
            return '#28a745';
        };
        
        let html = `
            <!-- KPI Score Overview -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <div style="font-size: 12px; opacity: 0.9; margin-bottom: 10px;">ĐIỂM KPI TỔNG HỢP (Mức Độ Nghi Vấn)</div>
                <div style="font-size: 48px; font-weight: bold; margin: 10px 0;">${data.kpi_score}</div>
                <div style="font-size: 14px; font-weight: 600;">
                    ${data.kpi_level === 'danger' ? '🚨 NGUY HIỂM' : 
                      data.kpi_level === 'warning' ? '⚠️ CẢNH BÁO' : 
                      '✅ BÌNH THƯỜNG'}
                </div>
            </div>

            <!-- Thông Tin Cơ Bản -->
            <div class="suspicion-detail">
                <h6><i class="fas fa-user-circle"></i> Thông Tin Nhân Viên</h6>
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
                <div class="detail-metric">
                    <span class="detail-metric-label">Ngày Vào Công Ty:</span>
                    <span class="detail-metric-value">${escapeHtml(data.ngay_vao_cong_ty || 'N/A')}</span>
                </div>
            </div>

            <hr>

            <!-- Breakdown Điểm 5 Tiêu Chí -->
            <div style="background: linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%); border: 2px solid #667eea; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                <h6 style="color: #667eea; margin-bottom: 15px; font-weight: 700;">
                    <i class="fas fa-star"></i> Phân Tích 5 Tiêu Chí KPI
                </h6>
                
                <div class="kpi-score-item" style="border-left-color: ${getScoreColor(breakdown.performance)};">
                    <span class="kpi-score-label">📊 Hiệu Suất Bán Hàng (30%)</span>
                    <div class="kpi-score-bar">
                        <div class="kpi-score-bar-bg">
                            <div class="kpi-score-bar-fill" style="width: ${breakdown.performance}%; background: ${getScoreColor(breakdown.performance)};"></div>
                        </div>
                    </div>
                    <span class="kpi-score-value">${breakdown.performance}</span>
                </div>

                <div class="kpi-score-item" style="border-left-color: ${getScoreColor(breakdown.consistency)};">
                    <span class="kpi-score-label">🎯 Tính Nhất Quán (25%) - Consistency: ${data.consistency_score.toFixed(1)}%</span>
                    <div class="kpi-score-bar">
                        <div class="kpi-score-bar-bg">
                            <div class="kpi-score-bar-fill" style="width: ${breakdown.consistency}%; background: ${getScoreColor(breakdown.consistency)};"></div>
                        </div>
                    </div>
                    <span class="kpi-score-value">${breakdown.consistency}</span>
                </div>

                <div class="kpi-score-item" style="border-left-color: ${getScoreColor(breakdown.trend)};">
                    <span class="kpi-score-label">📈 Xu Hướng (15%)</span>
                    <div class="kpi-score-bar">
                        <div class="kpi-score-bar-bg">
                            <div class="kpi-score-bar-fill" style="width: ${breakdown.trend}%; background: ${getScoreColor(breakdown.trend)};"></div>
                        </div>
                    </div>
                    <span class="kpi-score-value">${breakdown.trend}</span>
                </div>

                <div class="kpi-score-item" style="border-left-color: ${getScoreColor(breakdown.volatility)};">
                    <span class="kpi-score-label">⚡ Độ Biến Động (20%) - Std Dev: ${data.volatility.toFixed(2)}</span>
                    <div class="kpi-score-bar">
                        <div class="kpi-score-bar-bg">
                            <div class="kpi-score-bar-fill" style="width: ${breakdown.volatility}%; background: ${getScoreColor(breakdown.volatility)};"></div>
                        </div>
                    </div>
                    <span class="kpi-score-value">${breakdown.volatility}</span>
                </div>

                <div class="kpi-score-item" style="border-left-color: ${getScoreColor(breakdown.working_days)};">
                    <span class="kpi-score-label">⏰ Thời Gian Hoạt Động (10%)</span>
                    <div class="kpi-score-bar">
                        <div class="kpi-score-bar-bg">
                            <div class="kpi-score-bar-fill" style="width: ${breakdown.working_days}%; background: ${getScoreColor(breakdown.working_days)};"></div>
                        </div>
                    </div>
                    <span class="kpi-score-value">${breakdown.working_days}</span>
                </div>
            </div>

            <!-- Thống Kê Chi Tiết -->
            <div class="suspicion-detail">
                <h6><i class="fas fa-chart-bar"></i> Chỉ Số KPI Chi Tiết</h6>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tổng Đơn Hàng:</span>
                    <span class="detail-metric-value">${data.total_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tổng Tiền:</span>
                    <span class="detail-metric-value">${(data.total_amount / 1000000).toFixed(2)}M đ</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">TBD/Ngày:</span>
                    <span class="detail-metric-value">${data.avg_daily_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Max/Ngày:</span>
                    <span class="detail-metric-value">${data.max_day_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Min/Ngày:</span>
                    <span class="detail-metric-value">${data.min_day_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Ngày Hoạt Động:</span>
                    <span class="detail-metric-value">${data.working_days} ngày</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Xu Hướng:</span>
                    <span class="detail-metric-value">${getTrendIcon(data.trend)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tính Nhất Quán (Consistency):</span>
                    <span class="detail-metric-value">${data.consistency_score.toFixed(1)}%</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Độ Biến Động (Std Dev):</span>
                    <span class="detail-metric-value">${data.volatility.toFixed(2)}</span>
                </div>
            </div>

            <hr>

            <!-- Lý Do Đánh Giá -->
            <div class="suspicion-detail">
                <h6><i class="fas fa-clipboard-list"></i> Lý Do Đánh Giá</h6>
                ${reasons.length > 0 ? reasons.map(r => 
                    \`<div class="suspicion-reason">✓ \${escapeHtml(r)}</div>\`
                ).join('') : '<div class="suspicion-reason">✓ Hoạt động bình thường</div>'}
            </div>
        `;
        
        document.getElementById('modalContent').innerHTML = html;
    } catch (e) {
        console.error('Error parsing data:', e);
        document.getElementById('modalContent').innerHTML = '<p class="text-danger">Lỗi tải dữ liệu</p>';
    }
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Export KPI Report to CSV
 */
function exportKPIToCSV() {
    const table = document.querySelector('.kpi-table');
    if (!table) return;

    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cells = [];
        row.querySelectorAll('th, td').forEach(cell => {
            const text = cell.textContent.trim()
                .replace(/\n/g, ' ')
                .replace(/\s+/g, ' ');
            cells.push('"' + text.replace(/"/g, '""') + '"');
        });
        csv.push(cells.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `KPI_Report_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>
</body>
</html>