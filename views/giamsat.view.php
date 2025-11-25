<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giám Sát Ghé Thăm Khách Hàng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link rel="stylesheet" href="assets/css/giamsat.css">
    <link rel="stylesheet" href="assets/css/report.css">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px;">
<div class="container-fluid">
    <div class="card mt-4 mb-4">
        <div class="card-header">
            <h2><i class="fas fa-map-marker-alt"></i> GIÁM SÁT GHÉ THĂM KHÁCH HÀNG</h2>
        </div>

        <div class="card-body">
            <!-- MESSAGE ALERT -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- FORM LỌC -->
            <form id="filterForm" method="get" class="filter-section">
                <input type="hidden" name="action" value="giamsat">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Từ Ngày</label>
                        <input type="date" name="tu_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['tu_ngay']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-calendar"></i> Đến Ngày</label>
                        <input type="date" name="den_ngay" class="form-control" 
                               value="<?= htmlspecialchars($filters['den_ngay']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-user"></i> Mã NV</label>
                        <input type="text" name="ma_nhan_vien" class="form-control" 
                               placeholder="VD: HL00068" value="<?= htmlspecialchars($filters['ma_nhan_vien']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-check"></i> Kết Quả</label>
                        <select name="ket_qua" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <option value="Thành công" <?= ($filters['ket_qua'] === 'Thành công') ? 'selected' : '' ?>>Thành công</option>
                            <option value="Thất bại" <?= ($filters['ket_qua'] === 'Thất bại') ? 'selected' : '' ?>>Thất bại</option>
                            <option value="Không gặp" <?= ($filters['ket_qua'] === 'Không gặp') ? 'selected' : '' ?>>Không gặp</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-map"></i> Tỉnh</label>
                        <input type="text" name="tinh_thanh" class="form-control" 
                               placeholder="VD: Đồng Nai" value="<?= htmlspecialchars($filters['tinh_thanh']) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                </div>
            </form>

            <!-- THỐNG KÊ -->
            <div class="row mt-4 mb-4">
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">Tổng Lần Ghé Thăm</div>
                        <div class="stats-value"><?= number_format($statistics['total_records']) ?></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">Số Ngày</div>
                        <div class="stats-value"><?= $statistics['total_days'] ?></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">TG Ghé TB (phút)</div>
                        <div class="stats-value"><?= number_format($statistics['avg_call_time'], 1) ?></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">Tỷ Lệ Thành Công</div>
                        <div class="stats-value" style="color: #28a745;"><?= number_format($statistics['success_rate'], 1) ?>%</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">Nhân Viên</div>
                        <div class="stats-value"><?= number_format($statistics['total_employees']) ?></div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-card">
                        <div class="stats-label">Khách Hàng</div>
                        <div class="stats-value"><?= number_format($statistics['total_customers']) ?></div>
                    </div>
                </div>
            </div>

            <!-- BIỂU ĐỒ -->
            <div class="row mt-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>📊 Số Lần Ghé Thăm / Ngày</strong></div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="callsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>📈 Kết Quả Ghé Thăm</strong></div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="resultsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BẢNG DỮ LIỆU -->
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Ngày</th>
                            <th>NV (Mã)</th>
                            <th>Tên KH</th>
                            <th>Địa Chỉ</th>
                            <th>Thời Gian</th>
                            <th class="text-center">Thứ Tự</th>
                            <th class="text-center">Lần</th>
                            <th class="text-center">Tg (phút)</th>
                            <th>Kết Quả</th>
                            <th>Tỉnh</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($giamsat_data)): ?>
                        <?php foreach ($giamsat_data as $item): 
                            $result_badge = '';
                            if (stripos($item['ket_qua_ghe_tham'], 'Thành công') !== false || stripos($item['ket_qua_ghe_tham'], 'Có') !== false) {
                                $result_badge = '<span class="badge badge-success">✓ Thành công</span>';
                            } elseif (stripos($item['ket_qua_ghe_tham'], 'Thất bại') !== false || stripos($item['ket_qua_ghe_tham'], 'Không') !== false) {
                                $result_badge = '<span class="badge badge-danger">✗ Thất bại</span>';
                            } else {
                                $result_badge = '<span class="badge badge-warning">? ' . htmlspecialchars(substr($item['ket_qua_ghe_tham'], 0, 15)) . '</span>';
                            }
                        ?>
                        <tr>
                            <td><?= !empty($item['ngay']) ? date('d/m/Y', strtotime($item['ngay'])) : '-' ?></td>
                            <td><strong><?= htmlspecialchars($item['ma_nhan_vien'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars(substr($item['ten_khach_hang'] ?? '', 0, 20)) ?></td>
                            <td><small><?= htmlspecialchars(substr($item['dia_chi'] ?? '', 0, 30)) ?></small></td>
                            <td><?= htmlspecialchars($item['thoi_gian_bat_dau'] ?? '-') ?></td>
                            <td class="text-center"><?= $item['thu_tu_ghe_tham'] ?? '-' ?></td>
                            <td class="text-center"><?= $item['lan_ghe_tham'] ?? '-' ?></td>
                            <td class="text-center"><?= $item['tong_thoi_gian_ghe_tham'] ?? '-' ?></td>
                            <td><?= $result_badge ?></td>
                            <td><?= htmlspecialchars($item['tinh_thanh'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- NÚT HÀNH ĐỘNG -->
            <div class="btn-group-custom">
                <a href="index.php?action=giamsat_upload" class="btn btn-success">
                    <i class="fas fa-upload"></i> Import CSV
                </a>
                <a href="index.php?action=giamsat" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Làm Mới
                </a>
                <button class="btn btn-info" onclick="exportTableToCSV('giamsat_<?= date('Y-m-d') ?>.csv')">
                    <i class="fas fa-download"></i> Xuất Excel
                </button>
                <a href="index.php?action=report" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Báo Cáo Doanh Số
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dữ liệu biểu đồ
const chartData = <?= json_encode($chart_data) ?>;
const resultStats = <?= json_encode($statistics['by_result'] ?? []) ?>;

// Biểu đồ số lần ghé thăm
if (chartData && chartData.length > 0) {
    const callsCtx = document.getElementById('callsChart');
    if (callsCtx) {
        new Chart(callsCtx, {
            type: 'line',
            data: {
                labels: chartData.map(d => {
                    const date = new Date(d.ngay + 'T00:00:00');
                    return date.toLocaleDateString('vi-VN');
                }),
                datasets: [{
                    label: 'Số Lần Ghé Thăm',
                    data: chartData.map(d => parseInt(d.so_lang_ghe_tham) || 0),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } }
            }
        });
    }
}

// Biểu đồ kết quả
if (resultStats && Object.keys(resultStats).length > 0) {
    const resultsCtx = document.getElementById('resultsChart');
    if (resultsCtx) {
        const colors = ['#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6c757d'];
        new Chart(resultsCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(resultStats),
                datasets: [{
                    data: Object.values(resultStats),
                    backgroundColor: colors,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
}

// Export to CSV
function exportTableToCSV(filename) {
    const table = document.querySelector('.table');
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cells = [];
        row.querySelectorAll('th, td').forEach(cell => {
            cells.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(cells.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}
</script>
</body>
</html>