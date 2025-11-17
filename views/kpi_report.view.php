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
                                    <?= htmlspecialchars($prod) ?>
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
                            <strong>📊 Đơn Cao Nhất (Chung):</strong>
                            <span class="badge bg-success"><?= $statistics['max_orders_day'] ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>📊 Đơn Thấp Nhất (Chung):</strong>
                            <span class="badge bg-info"><?= $statistics['min_orders_day'] ?> đơn</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="benchmark-item">
                            <strong>🎯 Tiêu Chuẩn So Sánh:</strong>
                            <span class="badge bg-primary">TBD × 0.5 = Danger | TBD × 0.8 = Warning</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="kpi-legend">
                <div class="legend-item">
                    <span class="legend-badge" style="background: #dc3545;"></span>
                    <strong>Nguy Hiểm (70-100):</strong> Cần kiểm tra ngay
                </div>
                <div class="legend-item">
                    <span class="legend-badge" style="background: #ffc107;"></span>
                    <strong>Cảnh Báo (40-69):</strong> Cần theo dõi
                </div>
                <div class="legend-item">
                    <span class="legend-badge" style="background: #28a745;"></span>
                    <strong>Bình Thường (0-39):</strong> Hoạt động bình thường
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
                            <th class="text-center">Ngày Hoạt Động</th>
                            <th class="text-end">Điểm Nghi Vấn</th>
                            <th>Chi Tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($kpi_data)): ?>
                        <?php foreach ($kpi_data as $item): ?>
                        <?php
                            $badge_class = 'bg-success';
                            $icon = '✅';
                            if ($item['suspicion_level'] === 'warning') {
                                $badge_class = 'bg-warning text-dark';
                                $icon = '⚠️';
                            } elseif ($item['suspicion_level'] === 'danger') {
                                $badge_class = 'bg-danger';
                                $icon = '🚨';
                            }
                            
                            $trend_icon = '→';
                            $trend_text = 'Ổn Định';
                            if ($item['trend'] === 'increasing') {
                                $trend_icon = '📈';
                                $trend_text = 'Tăng';
                            } elseif ($item['trend'] === 'decreasing') {
                                $trend_icon = '📉';
                                $trend_text = 'Giảm';
                            }
                        ?>
                        <tr>
                            <td class="text-center">
                                <span class="badge <?= $badge_class ?>" title="<?= ucfirst($item['suspicion_level']) ?>">
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
                                <span class="badge bg-info"><?= $item['working_days'] ?> ngày</span>
                            </td>
                            <td class="text-end">
                                <span class="kpi-score" style="width: <?= $item['suspicion_score'] ?>%; background: 
                                    <?= ($item['suspicion_level'] === 'danger') ? '#dc3545' : 
                                        (($item['suspicion_level'] === 'warning') ? '#ffc107' : '#28a745') ?>;">
                                    <strong><?= $item['suspicion_score'] ?></strong>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailModal"
                                        onclick="showDetails('<?= htmlspecialchars(json_encode($item)) ?>')">
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
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi Tiết Nghi Vấn - <span id="modalEmpName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/report.js"></script>
<script src="assets/js/kpi.js"></script>
</body>
</html>