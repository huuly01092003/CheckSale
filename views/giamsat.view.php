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
    <style>
        .filter-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .stats-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #667eea; }
        .stat-box strong { color: #667eea; font-size: 18px; display: block; margin-top: 5px; }
        .stat-label { font-size: 12px; color: #999; text-transform: uppercase; }
        .table-responsive { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .daily-table { font-size: 12px; }
        .daily-table thead { background: #f8f9fa; }
        .daily-table tbody tr:hover { background: rgba(102, 126, 234, 0.05); }
        .time-cell { text-align: center; padding: 8px 4px; }
        .time-value { background: #fff; padding: 4px 8px; border-radius: 4px; font-family: monospace; }
        .tab-content { background: white; border: 1px solid #ddd; border-top: none; padding: 20px; }
    </style>
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
                        <select name="ma_nhan_vien" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <?php foreach ($employee_list as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['ma_nhan_vien']) ?>" 
                                        <?= ($emp['ma_nhan_vien'] === $filters['ma_nhan_vien']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['ma_nhan_vien']) ?> - <?= htmlspecialchars($emp['ten_nhan_vien']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-check"></i> Kết Quả</label>
                        <select name="ket_qua" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <?php foreach ($result_list as $res): ?>
                                <option value="<?= htmlspecialchars($res) ?>" 
                                        <?= ($res === $filters['ket_qua']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($res) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold"><i class="fas fa-map"></i> Tỉnh</label>
                        <select name="tinh_thanh" class="form-select">
                            <option value="">-- Tất Cả --</option>
                            <?php foreach ($province_list as $prov): ?>
                                <option value="<?= htmlspecialchars($prov) ?>" 
                                        <?= ($prov === $filters['tinh_thanh']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Lọc
                        </button>
                    </div>
                </div>
            </form>

            <!-- THỐNG KÊ (FIX: Scale theo filter) -->
            <div class="stats-group">
                <div class="stat-box">
                    <span class="stat-label">Tổng Lần Ghé</span>
                    <strong><?= number_format($statistics['total_records']) ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Số Ngày</span>
                    <strong><?= $statistics['total_days'] ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">TG TB (phút)</span>
                    <strong><?= number_format($statistics['avg_call_time'], 1) ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">TG Min-Max</span>
                    <strong><?= $statistics['min_call_time'] ?> - <?= $statistics['max_call_time'] ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Tỷ Lệ Thành Công</span>
                    <strong style="color: #28a745;"><?= number_format($statistics['success_rate'], 1) ?>%</strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Nhân Viên</span>
                    <strong><?= number_format($statistics['total_employees']) ?></strong>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Khách Hàng</span>
                    <strong><?= number_format($statistics['total_customers']) ?></strong>
                </div>
            </div>

            <!-- BIỂU ĐỒ (FIX: Scale theo filter) -->
            <div class="row mt-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>📊 Số Lần Ghé Thăm / Ngày</strong></div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="callsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header"><strong>📈 Kết Quả Ghé Thăm (FIX: Scale)</strong></div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 300px;">
                                <canvas id="resultsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS: Bảng Chính + Bảng Thời Gian -->
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-main">
                        <i class="fas fa-table"></i> Bảng Giám Sát Chính
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-daily">
                        <i class="fas fa-calendar-alt"></i> Tìm Kiếm Thời Gian (Nhân Viên)
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- TAB 1: BẢNG CHÍNH -->
                <div id="tab-main" class="tab-pane fade show active">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Ngày</th>
                                    <th>NV (Mã)</th>
                                    <th>Tên KH</th>
                                    <th>Địa Chỉ</th>
                                    <th class="text-center">Bắt Đầu</th>
                                    <th class="text-center">Kết Thúc</th>
                                    <th class="text-center">Tổng TG (phút)</th>
                                    <th class="text-center">Lần</th>
                                    <th>Kết Quả</th>
                                    <th>Tỉnh</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($giamsat_data)): ?>
                                <?php foreach ($giamsat_data as $item): 
                                    $result_badge = '';
                                    if (stripos($item['ket_qua_ghe_tham'], 'Thành công') !== false || 
                                        stripos($item['ket_qua_ghe_tham'], 'Có') !== false) {
                                        $result_badge = '<span class="badge bg-success">✓</span>';
                                    } elseif (stripos($item['ket_qua_ghe_tham'], 'Thất bại') !== false) {
                                        $result_badge = '<span class="badge bg-danger">✗</span>';
                                    } else {
                                        $result_badge = '<span class="badge bg-warning text-dark">?</span>';
                                    }
                                    
                                    $tong_thoi_gian = intval($item['tong_thoi_gian_ghe_tham'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= !empty($item['ngay']) ? date('d/m/Y', strtotime($item['ngay'])) : '-' ?></td>
                                    <td><strong><?= htmlspecialchars($item['ma_nhan_vien'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars(substr($item['ten_khach_hang'] ?? '', 0, 20)) ?></td>
                                    <td><small><?= htmlspecialchars(substr($item['dia_chi'] ?? '', 0, 30)) ?></small></td>
                                    <td class="text-center"><code><?= htmlspecialchars($item['thoi_gian_bat_dau'] ?? '-') ?></code></td>
                                    <td class="text-center"><code><?= htmlspecialchars($item['thoi_gian_ket_thuc'] ?? '-') ?></code></td>
                                    <td class="text-center">
                                        <?php if ($tong_thoi_gian > 0): ?>
                                            <span class="badge bg-primary"><?= $tong_thoi_gian ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $item['lan_ghe_tham'] ?? '-' ?></td>
                                    <td><?= $result_badge ?> <?= htmlspecialchars(substr($item['ket_qua_ghe_tham'] ?? '', 0, 15)) ?></td>
                                    <td><?= htmlspecialchars($item['tinh_thanh'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-5">Không có dữ liệu</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <?php if (!empty($total_pages) && $total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <small class="text-muted">
                            Trang <strong><?= $page ?></strong> / <strong><?= $total_pages ?></strong> 
                            | Tổng: <strong><?= number_format($total_records) ?></strong>
                        </small>
                        
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=giamsat&tu_ngay=<?= urlencode($filters['tu_ngay']) ?>&den_ngay=<?= urlencode($filters['den_ngay']) ?>&ma_nhan_vien=<?= urlencode($filters['ma_nhan_vien']) ?>&ket_qua=<?= urlencode($filters['ket_qua']) ?>&tinh_thanh=<?= urlencode($filters['tinh_thanh']) ?>&page=<?= $page - 1 ?>">← Trước</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?action=giamsat&tu_ngay=<?= urlencode($filters['tu_ngay']) ?>&den_ngay=<?= urlencode($filters['den_ngay']) ?>&ma_nhan_vien=<?= urlencode($filters['ma_nhan_vien']) ?>&ket_qua=<?= urlencode($filters['ket_qua']) ?>&tinh_thanh=<?= urlencode($filters['tinh_thanh']) ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?action=giamsat&tu_ngay=<?= urlencode($filters['tu_ngay']) ?>&den_ngay=<?= urlencode($filters['den_ngay']) ?>&ma_nhan_vien=<?= urlencode($filters['ma_nhan_vien']) ?>&ket_qua=<?= urlencode($filters['ket_qua']) ?>&tinh_thanh=<?= urlencode($filters['tinh_thanh']) ?>&page=<?= $page + 1 ?>">Sau →</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: BẢNG TÌM KIẾM THỜI GIAN (ẢNH 2) -->
                <div id="tab-daily" class="tab-pane fade">
                    <?php if (!empty($filters['ma_nhan_vien']) && !empty($employee_daily_data)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Tìm kiếm thời gian kết thúc của <strong><?= htmlspecialchars($filters['ma_nhan_vien']) ?></strong> 
                            từ <strong><?= $filters['tu_ngay'] ?></strong> đến <strong><?= $filters['den_ngay'] ?></strong>
                        </div>

                        <!-- Nhóm theo Nhân Viên -->
                        <?php 
                            $grouped_by_emp = [];
                            foreach ($employee_daily_data as $row) {
                                $key = $row['ma_nhan_vien'];
                                if (!isset($grouped_by_emp[$key])) {
                                    $grouped_by_emp[$key] = [
                                        'info' => $row,
                                        'days' => []
                                    ];
                                }
                                $day = $row['order_date'];
                                if (!isset($grouped_by_emp[$key]['days'][$day])) {
                                    $grouped_by_emp[$key]['days'][$day] = [];
                                }
                                $grouped_by_emp[$key]['days'][$day][] = $row;
                            }
                        ?>

                        <?php foreach ($grouped_by_emp as $ma_nv => $emp_data): ?>
                        <div class="card mb-3">
                            <div class="card-header" style="background: #f8f9fa;">
                                <strong>
                                    <?= htmlspecialchars($emp_data['info']['ten_nhan_vien'] ?? $ma_nv) ?> 
                                    (<?= htmlspecialchars($ma_nv) ?>)
                                </strong> - 
                                GS: <?= htmlspecialchars($emp_data['info']['gs'] ?? '') ?> | 
                                Tỉnh: <?= htmlspecialchars($emp_data['info']['tinh_thanh'] ?? '') ?>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <div class="table-responsive">
                                    <table class="table table-sm daily-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>GS</th>
                                                <th>Tỉnh</th>
                                                <th>Mã NV</th>
                                                <th>Tên NV</th>
                                                <th class="text-center">Tổng Lần Ghé</th>
                                                <?php 
                                                    $all_dates = [];
                                                    foreach ($emp_data['days'] as $day => $calls) {
                                                        $all_dates[] = $day;
                                                    }
                                                    sort($all_dates);
                                                    foreach ($all_dates as $date): 
                                                        $day_obj = new DateTime($date);
                                                        $day_name = ['Chủ', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][intval($day_obj->format('w'))];
                                                ?>
                                                    <th class="text-center time-cell">
                                                        <small><?= $day_name ?></small><br>
                                                        <small><?= date('d-M', strtotime($date)) ?></small>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= htmlspecialchars($emp_data['info']['gs'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($emp_data['info']['tinh_thanh'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($ma_nv) ?></td>
                                                <td><?= htmlspecialchars($emp_data['info']['ten_nhan_vien'] ?? '') ?></td>
                                                <td class="text-center"><strong><?= count($employee_daily_data) ?></strong></td>
                                                <?php foreach ($all_dates as $date): ?>
                                                    <td class="time-cell">
                                                        <?php if (isset($emp_data['days'][$date])): ?>
                                                            <?php $times = array_map(function($c) { return $c['thoi_gian_ket_thuc']; }, $emp_data['days'][$date]); ?>
                                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                                <?php foreach ($times as $t): ?>
                                                                    <div class="time-value"><?= htmlspecialchars($t) ?></div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="alert alert-warning" style="margin: 20px 0;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Chọn mã nhân viên từ bảng chính để xem chi tiết thời gian kết thúc theo ngày.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="btn-group-custom" style="margin-top: 20px;">
                <a href="index.php?action=giamsat_upload" class="btn btn-success">
                    <i class="fas fa-upload"></i> Import CSV
                </a>
                <a href="index.php?action=giamsat" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Làm Mới
                </a>
                <button class="btn btn-info" onclick="exportTableToCSV()">
                    <i class="fas fa-download"></i> Xuất CSV
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
                    label: 'Số Lần Ghé',
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

// Biểu đồ kết quả (FIX: Scale)
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

function exportTableToCSV() {
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
    link.download = `giamsat_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
}
</script>
</body>
</html>