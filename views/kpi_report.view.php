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
    <link rel="stylesheet" href="assets/css/report-kpi.css">
</head>
<style>
    /* CSS ĐỂ CỐ ĐỊNH HEADER KHI CUỘN */
    .kpi-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white !important;
        font-weight: 700;
        border: none;
        padding: 15px;
        text-align: center;
        position: sticky; /* Quan trọng: dính header */
        top: 0;
        z-index: 10;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Style cho Footer giống trang doanh số */
    .btn-group-custom {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .debug-info {
        background: #f8f9fa;
        border-left: 4px solid #667eea;
        padding: 10px 15px;
        margin-top: 20px;
        border-radius: 4px;
        font-size: 0.9rem;
        color: #555;
    }
</style>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px;">
<div class="container-fluid">
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> PHÂN TÍCH KPI - PHÁT HIỆN GIAN LẬN</h2>
        </div>
        
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form id="kpiFilterForm" method="get" class="filter-section">
                <input type="hidden" name="action" value="kpi_report">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar-alt"></i> Tháng</label>
                        <select id="thang" name="thang" class="form-select" required>
                            <?php foreach ($available_months as $m): ?>
                                <option value="<?= htmlspecialchars($m) ?>" <?= ($m === ($filters['thang'] ?? '')) ? 'selected' : '' ?>>
                                    Tháng <?= date('m/Y', strtotime($m . '-01')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" id="tuNgay" name="tu_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['tu_ngay'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" id="denNgay" name="den_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['den_ngay'] ?? '') ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-building"></i> Giám Sát (GS)</label>
                        <select id="gsFilter" name="gs_filter" class="form-select">
                            <option value="">-- Tất Cả GS --</option>
                            <?php if (!empty($available_gs)): foreach ($available_gs as $gs): ?>
                                <option value="<?= htmlspecialchars($gs) ?>" 
                                        <?= ($gs === ($filters['gs_filter'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gs) ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold"><i class="fas fa-cube"></i> Nhóm Sản Phẩm</label>
                        <select id="productFilter" name="product_filter" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <?php if (!empty($available_products)): foreach ($available_products as $prod): ?>
                                <option value="<?= htmlspecialchars($prod) ?>" 
                                        <?= ($prod === ($filters['product_filter'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prod) ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row g-2 mt-3">
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Lọc Dữ Liệu
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="index.php?action=kpi_report" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <div class="row g-3 mt-2 border-top pt-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="fas fa-shopping-cart"></i> Lọc Số ĐH Tối Thiểu</label>
                    <div class="input-group">
                        <input type="number" id="orderCountMin" class="form-control" placeholder="VD: 5" min="0">
                        <button type="button" class="btn btn-success" id="applyClientFilter">
                            <i class="fas fa-check"></i> Áp Dụng
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-warning" id="resetClientFilter" style="display:none;">
                        <i class="fas fa-redo"></i> Hủy
                    </button>
                </div>
            </div>

            <div class="row mt-4 mb-4">
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-info">
                        <div class="kpi-icon"><i class="fas fa-users"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Nhân Viên Có ĐH</div>
                            <div class="kpi-value"><?= $statistics['employees_with_orders'] ?? 0 ?></div>
                            <div class="kpi-subtext">/ <?= $statistics['total_employees'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-primary">
                        <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Tổng Đơn Hàng</div>
                            <div class="kpi-value"><?= number_format($statistics['total_orders'] ?? 0) ?></div>
                            <div class="kpi-subtext">đơn</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-success">
                        <div class="kpi-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">TBD Chung</div>
                            <div class="kpi-value"><?= number_format($statistics['avg_orders_per_emp'] ?? 0, 1) ?></div>
                            <div class="kpi-subtext">đơn/NV</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-warning">
                        <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Cảnh Báo</div>
                            <div class="kpi-value"><?= $statistics['warning_count'] ?? 0 ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-danger">
                        <div class="kpi-icon"><i class="fas fa-virus"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Nguy Hiểm</div>
                            <div class="kpi-value"><?= $statistics['danger_count'] ?? 0 ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="kpi-card kpi-card-light">
                        <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="kpi-content">
                            <div class="kpi-label">Bình Thường</div>
                            <div class="kpi-value"><?= $statistics['normal_count'] ?? 0 ?></div>
                            <div class="kpi-subtext">người</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="benchmark-box">
                <div class="row">
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📊 TBD Cao Nhất (Chung):</strong>
                            <span class="badge bg-success"><?= number_format($statistics['max_daily_orders'] ?? 0, 1) ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📊 TBD Chung:</strong>
                            <span class="badge bg-info"><?= number_format($statistics['avg_daily_orders'] ?? 0, 2) ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📈 Độ Biến Động (Std Dev):</strong>
                            <span class="badge bg-warning text-dark"><?= number_format($statistics['std_dev_orders'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kpi-legend">
                <div class="legend-item"><span class="legend-badge" style="background: #ff6b6b;"></span> <strong>Critical (75-100)</strong></div>
                <div class="legend-item"><span class="legend-badge" style="background: #ffc107;"></span> <strong>Warning (50-74)</strong></div>
                <div class="legend-item"><span class="legend-badge" style="background: #28a745;"></span> <strong>Normal (0-49)</strong></div>
            </div>

            <div class="table-responsive mt-4" style="max-height: 600px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 5px;">
                <table class="table table-hover kpi-table" style="margin-bottom: 0;">
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
                            <th class="text-center">Nhất Quán %</th>
                            <th class="text-end">Risk Score</th>
                            <th class="text-center">Chi Tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($kpi_data)): ?>
                        <?php foreach ($kpi_data as $item): ?>
                        <?php
                            $risk_level = isset($item['risk_level']) ? $item['risk_level'] : 'normal';
                            $badge_class = ($risk_level === 'critical') ? 'bg-danger' : (($risk_level === 'warning') ? 'bg-warning text-dark' : 'bg-success');
                            $icon = ($risk_level === 'critical') ? '🚨' : (($risk_level === 'warning') ? '⚠️' : '✅');
                            
                            $trend_icon = '→';
                            if (isset($item['trend'])) {
                                if ($item['trend'] === 'increasing') $trend_icon = '📈';
                                elseif ($item['trend'] === 'decreasing') $trend_icon = '📉';
                            }
                        ?>
                        <tr>
                            <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $icon ?></span></td>
                            <td><strong><?= htmlspecialchars($item['ma_nv'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($item['ten_nv'] ?? '') ?></td>
                            <td class="text-end fw-bold"><?= number_format($item['total_orders'] ?? 0) ?></td>
                            <td class="text-end"><?= number_format($item['avg_daily_orders'] ?? 0, 1) ?></td>
                            <td class="text-end text-success"><strong><?= $item['max_day_orders'] ?? 0 ?></strong></td>
                            <td class="text-end text-muted"><?= $item['min_day_orders'] ?? 0 ?></td>
                            <td class="text-center"><span class="badge bg-light text-dark"><?= $trend_icon ?></span></td>
                            <td class="text-center"><span class="badge bg-info"><?= round($item['consistency_score'] ?? 0, 0) ?>%</span></td>
                            <td class="text-end">
                                <span style="padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; background: <?= ($risk_level === 'critical') ? '#ff6b6b' : (($risk_level === 'warning') ? '#ffc107' : '#28a745') ?>;">
                                    <?= $item['risk_score'] ?? 0 ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary btn-detail" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailModal"
                                        data-json="<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>">
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

            <div class="btn-group-custom">
                <a href="index.php?action=report" class="btn btn-info">
                    <i class="fas fa-chart-line"></i> Báo Cáo Doanh Số
                </a>
                <a href="index.php?action=upload" class="btn btn-success">
                    <i class="fas fa-upload"></i> Upload File Mới
                </a>
                <a href="index.php?action=kpi_report" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Làm Mới
                </a>
                <button type="button" class="btn btn-info" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Xuất CSV
                </button>
            </div>

            <div class="debug-info">
                <strong>📊 Thông Tin:</strong> 
                Tháng: <?= htmlspecialchars($filters['thang']) ?> | 
                Đơn hàng: <?= $statistics['total_orders'] ?? 0 ?> | 
                Nhân viên: <?= $statistics['employees_with_orders'] ?? 0 ?> | 
                Cảnh báo: <?= $statistics['warning_count'] ?? 0 ?> | 
                Nguy hiểm: <?= $statistics['danger_count'] ?? 0 ?>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title"><i class="fas fa-chart-pie"></i> Chi Tiết KPI - <span id="modalEmpName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent" style="max-height: 700px; overflow-y: auto;"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý click nút Chi Tiết
    const detailButtons = document.querySelectorAll('.btn-detail');
    detailButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const jsonStr = this.getAttribute('data-json');
            if (jsonStr) showKPIDetails(jsonStr);
        });
    });

    // Xử lý Client-side Filter (Lọc số đơn tối thiểu)
    const tableBody = document.querySelector('.kpi-table tbody');
    if (tableBody) {
        const allRows = Array.from(tableBody.querySelectorAll('tr')).filter(row => 
            row.querySelector('td') && row.querySelector('td').textContent.trim() !== 'Không có dữ liệu'
        );
        const originalRows = allRows.map(row => ({
            element: row.cloneNode(true),
            totalOrders: parseInt(row.querySelector('td:nth-child(4)')?.textContent.replace(/\D/g, '') || 0)
        }));

        const applyBtn = document.getElementById('applyClientFilter');
        const resetBtn = document.getElementById('resetClientFilter');
        const minInput = document.getElementById('orderCountMin');

        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                const minVal = minInput.value ? parseInt(minInput.value) : null;
                if (minVal === null) { alert('⚠️ Vui lòng nhập số đơn hàng'); return; }
                
                tableBody.innerHTML = '';
                const filtered = originalRows.filter(item => item.totalOrders >= minVal);
                
                if (filtered.length > 0) {
                    filtered.forEach(item => tableBody.appendChild(item.element));
                    resetBtn.style.display = 'block';
                } else {
                    tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-5">Không có nhân viên nào đạt điều kiện</td></tr>';
                    resetBtn.style.display = 'block';
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                tableBody.innerHTML = '';
                originalRows.forEach(item => tableBody.appendChild(item.element.cloneNode(true)));
                minInput.value = '';
                this.style.display = 'none';
            });
        }
    }
});

function showKPIDetails(jsonData) {
    try {
        const data = JSON.parse(jsonData);
        const breakdown = data.score_breakdown || {};
        const reasons = data.risk_reasons || [];
        
        document.getElementById('modalEmpName').textContent = (data.ten_nv || 'N/A');
        
        const getScoreColor = (score) => score >= 75 ? '#ff6b6b' : (score >= 50 ? '#ffc107' : '#28a745');
        const getRiskLevel = (score) => score >= 75 ? '🚨 CRITICAL' : (score >= 50 ? '⚠️ WARNING' : '✅ NORMAL');

        let html = `
            <div style="text-align: center; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <div style="font-size: 42px; font-weight: bold; color: ${getScoreColor(data.risk_score)};">${data.risk_score || 0}</div>
                <div style="font-weight: bold; color: ${getScoreColor(data.risk_score)};">${getRiskLevel(data.risk_score)}</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-6"><strong>Mã NV:</strong> ${data.ma_nv}</div>
                <div class="col-6"><strong>Tổng Đơn:</strong> ${data.total_orders}</div>
                <div class="col-6"><strong>TBD/Ngày:</strong> ${data.avg_daily_orders}</div>
                <div class="col-6"><strong>Nhất Quán:</strong> ${data.consistency_score}%</div>
            </div>

            <h6 class="border-bottom pb-2">Phân Tích Rủi Ro:</h6>
            <ul class="list-group mb-3">
        `;
        
        // Render bars
        const metrics = [
            {label: 'Hiệu suất', val: breakdown.performance},
            {label: 'Nhất quán', val: breakdown.consistency},
            {label: 'Khách hàng', val: breakdown.customer},
            {label: 'Xu hướng', val: breakdown.trend},
            {label: 'Biến động', val: breakdown.volatility},
            {label: 'Hoạt động', val: breakdown.activity}
        ];

        metrics.forEach(m => {
            html += `
                <li class="list-group-item border-0 px-0">
                    <div class="d-flex justify-content-between">
                        <small>${m.label}</small>
                        <small><strong>${m.val || 0}</strong></small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" style="width: ${(m.val || 0)}%; background-color: ${getScoreColor(m.val)}"></div>
                    </div>
                </li>
            `;
        });

        html += `</ul><h6>Lý Do Cụ Thể:</h6><ul class="text-danger small">`;
        if (reasons.length > 0) {
            reasons.forEach(r => html += `<li>${r}</li>`);
        } else {
            html += `<li class="text-success">Hoạt động bình thường</li>`;
        }
        html += `</ul>`;

        document.getElementById('modalContent').innerHTML = html;
    } catch (e) {
        console.error('Detail Error:', e);
        document.getElementById('modalContent').innerHTML = '<div class="alert alert-danger">Lỗi hiển thị dữ liệu: ' + e.message + '</div>';
    }
}

function exportToCSV() {
    alert('Chức năng xuất CSV sẽ được cập nhật trong phiên bản tiếp theo.');
}
</script>
</body>
</html>