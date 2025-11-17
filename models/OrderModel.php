<?php
/**
 * FILE: MODELS/ORDERMODEL.PHP (UPDATED - FIXED)
 * 🔧 Sửa lỗi: Loại bỏ các đơn không có ngày tạo (đơn bị trả)
 * 
 * HƯỚNG DẪN: Thay thế toàn bộ file OrderModel.php cũ bằng file này
 */

class OrderModel {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = Config::getPDO();
        $this->logger = new Logger();
    }

    /**
     * ✅ IMPORT TỪ CSV - SIÊU NHANH
     * 🔧 FIX: Bỏ qua dòng nếu ngay_tao_don rỗng
     */
    public function importFromCSV($file) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO donhang (ma_nv, ngay_tao_don, thanh_tien) 
                 VALUES (?, ?, ?)"
            );
            
            $batch = [];
            $batchSize = Config::$batch_size;
            $count = 0;
            $errors = 0;
            $skipped = 0;
            $start_time = microtime(true);
            
            $handle = fopen($file, 'r');
            if (!$handle) {
                throw new Exception("Không thể mở file CSV");
            }
            
            fgetcsv($handle, 0, ',', '"');
            
            $this->logger->info("CSV Import started", ['file' => basename($file)]);
            
            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                try {
                    $ma_nv = isset($row[3]) ? trim($row[3]) : '';
                    $ngay_val = isset($row[17]) ? trim($row[17]) : '';
                    $tien_val = isset($row[58]) ? trim($row[58]) : '';
                    
                    if (empty($ma_nv)) continue;
                    
                    // 🔧 FIX: Nếu ngay_tao_don rỗng thì để NULL (đơn bị trả)
                    if (empty($ngay_val)) {
                        $ngay = null;
                        $skipped++;
                    } else {
                        $ngay = $this->parseDate($ngay_val);
                        if (!$ngay) {
                            $ngay = null;
                            $skipped++;
                        }
                    }
                    
                    $tien = $this->parseMoney($tien_val);
                    
                    $batch[] = [$ma_nv, $ngay, $tien];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        
                        if ($count % 10000 == 0) {
                            $this->logger->info("CSV Progress", ['rows' => $count, 'skipped' => $skipped]);
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                    continue;
                }
            }
            
            if (!empty($batch)) {
                $this->executeBatch($stmt, $batch);
            }
            
            fclose($handle);
            $elapsed = round(microtime(true) - $start_time, 2);
            
            $this->logger->success("CSV Import completed", [
                'rows' => $count,
                'skipped' => $skipped,
                'errors' => $errors,
                'time_sec' => $elapsed
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("CSV Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * ✅ IMPORT TỪ EXCEL (FALLBACK)
     * 🔧 FIX: Bỏ qua dòng nếu ngay_tao_don rỗng
     */
    public function importFromExcelChunked($file) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file);
            $sheet = $spreadsheet->getActiveSheet();
            
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO donhang (ma_nv, ngay_tao_don, thanh_tien) 
                 VALUES (?, ?, ?)"
            );
            
            $count = 0;
            $errors = 0;
            $skipped = 0;
            $maxRow = $sheet->getHighestRow();
            $startRow = 8;
            $batchSize = Config::$batch_size;
            $batch = [];
            $start_time = microtime(true);
            
            $this->logger->info("Excel Import started", [
                'file' => basename($file),
                'rows' => $maxRow
            ]);
            
            for ($row = $startRow; $row <= $maxRow; $row++) {
                try {
                    $ma_nv = trim($sheet->getCell('D' . $row)->getValue() ?? '');
                    $ngay_val = $sheet->getCell('R' . $row)->getValue();
                    $tien_val = $sheet->getCell('BG' . $row)->getValue();
                    
                    if (empty($ma_nv)) continue;
                    
                    // 🔧 FIX: Nếu ngay_tao_don rỗng thì để NULL (đơn bị trả)
                    if (empty($ngay_val)) {
                        $ngay = null;
                        $skipped++;
                    } else {
                        $ngay = $this->parseDate($ngay_val);
                        if (!$ngay) {
                            $ngay = null;
                            $skipped++;
                        }
                    }
                    
                    $tien = $this->parseMoney($tien_val);
                    
                    $batch[] = [$ma_nv, $ngay, $tien];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        
                        if ($count % 5000 == 0) {
                            $this->logger->info("Excel Progress", ['rows' => $count, 'skipped' => $skipped]);
                        }
                        
                        if ($count % 10000 == 0) {
                            $spreadsheet->disconnectWorksheets();
                            $spreadsheet = $reader->load($file);
                            $sheet = $spreadsheet->getActiveSheet();
                            $stmt = $this->pdo->prepare(
                                "INSERT IGNORE INTO donhang (ma_nv, ngay_tao_don, thanh_tien) 
                                 VALUES (?, ?, ?)"
                            );
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                    continue;
                }
            }
            
            if (!empty($batch)) {
                $this->executeBatch($stmt, $batch);
            }
            
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            $elapsed = round(microtime(true) - $start_time, 2);
            
            $this->logger->success("Excel Import completed", [
                'rows' => $count,
                'skipped' => $skipped,
                'errors' => $errors,
                'time_sec' => $elapsed
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Excel Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * ✅ CÔNG THỨC 1: Tổng tiền KỲ (Toàn tháng)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getTotalByMonth($thang) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang]);
            $result = $stmt->fetch();
            
            return floatval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getTotalByMonth error");
            return 0;
        }
    }

    /**
     * ✅ CÔNG THỨC 2: Tổng tiền KHOẢNG (Khoảng ngày chọn)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getTotalByDateRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return floatval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getTotalByDateRange error");
            return 0;
        }
    }

    /**
     * ✅ CÔNG THỨC 3: Tổng tiền nhân viên TRONG THÁNG
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeTotalByMonth($ma_nv, $thang) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $thang]);
            $result = $stmt->fetch();
            
            return floatval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeTotalByMonth error");
            return 0;
        }
    }

    /**
     * ✅ CÔNG THỨC 4: Tổng tiền nhân viên TRONG KHOẢNG NGÀY
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeTotalByDateRange($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return floatval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeTotalByDateRange error");
            return 0;
        }
    }

    /**
     * Lấy danh sách tháng có dữ liệu
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getAvailableMonths() {
        try {
            $sql = "SELECT DISTINCT DATE_FORMAT(ngay_tao_don, '%Y-%m') as thang 
                    FROM donhang 
                    WHERE ngay_tao_don IS NOT NULL
                    AND YEAR(ngay_tao_don) >= 2000
                    ORDER BY thang DESC
                    LIMIT 24";
            
            $result = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            return $result ?: [];
        } catch (Exception $e) {
            $this->logger->error("getAvailableMonths error");
            return [];
        }
    }

    /**
     * ========== KPI METHODS ==========
     */

    /**
     * 🆕 Lấy số đơn hàng theo từng ngày của nhân viên
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeDailyOrders($ma_nv, $tu_ngay, $den_ngay, $product_filter = '') {
        try {
            $sql = "SELECT 
                        DATE(ngay_tao_don) as order_date,
                        COUNT(*) as order_count
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $params = [$ma_nv, $tu_ngay, $den_ngay];
            
            if (!empty($product_filter)) {
                $sql .= " AND ma_san_pham LIKE ?";
                $params[] = $product_filter . '%';
            }
            
            $sql .= " GROUP BY DATE(ngay_tao_don)
                      ORDER BY DATE(ngay_tao_don) ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeDailyOrders error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 🆕 Lấy danh sách sản phẩm (mã)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getDistinctProducts() {
        try {
            $sql = "SELECT DISTINCT 
                        SUBSTRING(ma_san_pham, 1, 2) as product_prefix
                    FROM donhang 
                    WHERE ma_san_pham IS NOT NULL 
                    AND ma_san_pham != ''
                    AND ngay_tao_don IS NOT NULL
                    ORDER BY product_prefix ASC
                    LIMIT 50";
            
            $result = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            return $result ?: [];
        } catch (Exception $e) {
            $this->logger->error("getDistinctProducts error");
            return [];
        }
    }

    /**
     * 🆕 Lấy chi tiết đơn hàng của nhân viên
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeOrderDetails($ma_nv, $tu_ngay, $den_ngay, $product_filter = '') {
        try {
            $sql = "SELECT 
                        ngay_tao_don,
                        ma_san_pham,
                        thanh_tien,
                        COUNT(*) as order_count
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $params = [$ma_nv, $tu_ngay, $den_ngay];
            
            if (!empty($product_filter)) {
                $sql .= " AND ma_san_pham LIKE ?";
                $params[] = $product_filter . '%';
            }
            
            $sql .= " GROUP BY DATE(ngay_tao_don), ma_san_pham
                      ORDER BY DATE(ngay_tao_don) DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeOrderDetails error");
            return [];
        }
    }

    /**
     * 🆕 So sánh hiệu suất: nhân viên vs chung toàn hệ thống
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemComparison($tu_ngay, $den_ngay, $product_filter = '') {
        try {
            $sql = "SELECT 
                        ma_nv,
                        COUNT(*) as total_orders,
                        AVG(CAST(thanh_tien as DECIMAL(12,2))) as avg_order_value,
                        MAX(CAST(thanh_tien as DECIMAL(12,2))) as max_order_value,
                        MIN(CAST(thanh_tien as DECIMAL(12,2))) as min_order_value
                    FROM donhang 
                    WHERE DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $params = [$tu_ngay, $den_ngay];
            
            if (!empty($product_filter)) {
                $sql .= " AND ma_san_pham LIKE ?";
                $params[] = $product_filter . '%';
            }
            
            $sql .= " GROUP BY ma_nv
                      ORDER BY total_orders DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getSystemComparison error");
            return [];
        }
    }

    /**
     * 🆕 Lấy heatmap dữ liệu (ngày nào có hoạt động nhiều nhất)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getActivityHeatmap($tu_ngay, $den_ngay, $product_filter = '') {
        try {
            $sql = "SELECT 
                        DATE(ngay_tao_don) as order_date,
                        DAYNAME(ngay_tao_don) as day_name,
                        COUNT(*) as total_orders,
                        COUNT(DISTINCT ma_nv) as employee_count
                    FROM donhang 
                    WHERE DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $params = [$tu_ngay, $den_ngay];
            
            if (!empty($product_filter)) {
                $sql .= " AND ma_san_pham LIKE ?";
                $params[] = $product_filter . '%';
            }
            
            $sql .= " GROUP BY DATE(ngay_tao_don)
                      ORDER BY DATE(ngay_tao_don) ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getActivityHeatmap error");
            return [];
        }
    }

    /**
     * === HELPER FUNCTIONS ===
     */

    private function parseDate($value) {
        if (empty($value)) return null;
        
        try {
            if (is_numeric($value)) {
                $date = date('Y-m-d', ($value - 25569) * 86400);
            } else {
                $value = trim($value);
                $date_part = explode(' ', $value)[0];
                $dateObj = DateTime::createFromFormat('d/m/Y', $date_part);
                $date = $dateObj ? $dateObj->format('Y-m-d') : null;
            }
            
            if ($date && strtotime($date) >= strtotime('2000-01-01')) {
                return $date;
            }
        } catch (Exception $e) {
            $this->logger->debug("Date parse error");
        }
        
        return null;
    }

    private function parseMoney($value) {
        // ✅ Cho phép 0.00 - không coi là rỗng
        if ($value === '' || $value === null) return 0;
        
        try {
            $money = 0;
            
            if (is_numeric($value)) {
                // 🔧 Trực tiếp là số
                $money = floatval($value);
            } else {
                $value_str = trim($value);
                
                // 🔧 Kiểm tra xem có phải chuỗi rỗng sau trim không
                if ($value_str === '') {
                    return 0;
                }
                
                // 🔧 FIX: Kiểm tra dấu âm TRƯỚC khi xóa ký tự
                $is_negative = (strpos($value_str, '-') !== false);
                
                // Xóa tất cả ký tự không phải số và dấu chấm, GIỮ lại dấu âm
                $money_str = preg_replace('/[^\d.-]/', '', $value_str);
                
                // 🔧 Nếu sau regex không còn gì (không phải số) → return 0
                if ($money_str === '' || $money_str === '.' || $money_str === '-') {
                    return 0;
                }
                
                // Xóa dấu chấm thừa (ngoài dấu chấm thập phân cuối cùng)
                $parts = explode('.', $money_str);
                if (count($parts) > 2) {
                    // Nếu có nhiều dấu chấm, xóa tất cả trừ cái cuối
                    $money_str = implode('', array_slice($parts, 0, -1)) . '.' . end($parts);
                }
                
                $money = floatval($money_str);
                
                // Nếu detect dấu âm nhưng floatval không xử lý đúng
                if ($is_negative && $money > 0) {
                    $money = -$money;
                }
            }
            
            // ✅ Cho phép số âm, 0.00, và dương
            // Chỉ kiểm tra xem có phải là số hợp lệ không
            if (is_finite($money)) {
                return $money;
            }
        } catch (Exception $e) {
            $this->logger->debug("Money parse error: " . $e->getMessage());
        }
        
        return 0;
    }

    private function executeBatch($stmt, $batch) {
        foreach ($batch as $item) {
            try {
                $stmt->execute($item);
            } catch (Exception $e) {
                $this->logger->debug("Batch insert error");
            }
        }
    }


    /**
     * 🆕 Lấy số ngày nhân viên có doanh số (dùng tính doanh số trung bình/ngày)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeDaysWithOrders($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT DATE(ngay_tao_don)) as days_count
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return intval($result['days_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeDaysWithOrders error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy số nhân viên có doanh số trong khoảng ngày
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeCountWithOrders($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT ma_nv) as emp_count
                    FROM donhang 
                    WHERE DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return intval($result['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountWithOrders error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy dữ liệu chi tiết nhân viên cho modal
     * Trả về: doanh số max/min ngày, số ngày hoạt động, v.v.
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeDetailForModal($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT DATE(ngay_tao_don)) as working_days,
                        MAX(CAST(thanh_tien as DECIMAL(12,2))) as max_daily_amount,
                        MIN(CAST(thanh_tien as DECIMAL(12,2))) as min_daily_amount,
                        AVG(CAST(thanh_tien as DECIMAL(12,2))) as avg_order_value,
                        COALESCE(SUM(thanh_tien), 0) as total_amount
                    FROM donhang 
                    WHERE ma_nv = ? 
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000
                    GROUP BY ma_nv";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'working_days' => 0,
                'max_daily_amount' => 0,
                'min_daily_amount' => 0,
                'avg_order_value' => 0,
                'total_amount' => 0
            ];
        } catch (Exception $e) {
            $this->logger->error("getEmployeeDetailForModal error");
            return [];
        }
    }

    /**
     * 🆕 Lấy doanh số max/min ngày của tất cả nhân viên (benchmark)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemBenchmark($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                        MAX(daily_max) as system_max_daily,
                        MIN(daily_min) as system_min_daily,
                        AVG(daily_avg) as system_avg_daily
                    FROM (
                        SELECT 
                            MAX(CAST(thanh_tien as DECIMAL(12,2))) as daily_max,
                            MIN(CAST(thanh_tien as DECIMAL(12,2))) as daily_min,
                            AVG(CAST(thanh_tien as DECIMAL(12,2))) as daily_avg,
                            DATE(ngay_tao_don) as order_date
                        FROM donhang 
                        WHERE DATE(ngay_tao_don) >= ?
                        AND DATE(ngay_tao_don) <= ?
                        AND ngay_tao_don IS NOT NULL 
                        AND YEAR(ngay_tao_don) >= 2000
                        GROUP BY DATE(ngay_tao_don)
                    ) daily_stats";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'system_max_daily' => 0,
                'system_min_daily' => 0,
                'system_avg_daily' => 0
            ];
        } catch (Exception $e) {
            $this->logger->error("getSystemBenchmark error");
            return [];
        }
    }

    /**
     * 🆕 Lấy doanh số ngày cao nhất của nhân viên trong khoảng
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getMaxDailyAmountByDateRange($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily_amount
                    FROM (
                        SELECT 
                            SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                            DATE(ngay_tao_don) as order_date
                        FROM donhang 
                        WHERE ma_nv = ? 
                        AND DATE(ngay_tao_don) >= ?
                        AND DATE(ngay_tao_don) <= ?
                        AND ngay_tao_don IS NOT NULL 
                        AND YEAR(ngay_tao_don) >= 2000
                        GROUP BY DATE(ngay_tao_don)
                    ) daily_stats";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return floatval($result['max_daily_amount'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getMaxDailyAmountByDateRange error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy doanh số ngày cao nhất của nhân viên trong tháng
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getMaxDailyAmountByMonth($ma_nv, $thang) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily_amount
                    FROM (
                        SELECT 
                            SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                            DATE(ngay_tao_don) as order_date
                        FROM donhang 
                        WHERE ma_nv = ? 
                        AND DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?
                        AND ngay_tao_don IS NOT NULL 
                        AND YEAR(ngay_tao_don) >= 2000
                        GROUP BY DATE(ngay_tao_don)
                    ) daily_stats";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $thang]);
            $result = $stmt->fetch();
            
            return floatval($result['max_daily_amount'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getMaxDailyAmountByMonth error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy trung bình doanh số ngày cao nhất của tất cả nhân viên trong khoảng
     * = (Tổng doanh số ngày cao nhất của từng nhân viên) / (Số nhân viên)
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemMaxDailyAverage($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT AVG(max_daily_amount) as avg_max_daily
                    FROM (
                        SELECT 
                            ma_nv,
                            MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT 
                                ma_nv,
                                SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                DATE(ngay_tao_don) as order_date
                            FROM donhang 
                            WHERE DATE(ngay_tao_don) >= ?
                            AND DATE(ngay_tao_don) <= ?
                            AND ngay_tao_don IS NOT NULL 
                            AND YEAR(ngay_tao_don) >= 2000
                            GROUP BY ma_nv, DATE(ngay_tao_don)
                        ) daily_by_emp
                        GROUP BY ma_nv
                    ) emp_max_daily";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return floatval($result['avg_max_daily'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getSystemMaxDailyAverage error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy trung bình doanh số ngày cao nhất của tất cả nhân viên trong tháng
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemMaxDailyAverageByMonth($thang) {
        try {
            $sql = "SELECT AVG(max_daily_amount) as avg_max_daily
                    FROM (
                        SELECT 
                            ma_nv,
                            MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT 
                                ma_nv,
                                SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                DATE(ngay_tao_don) as order_date
                            FROM donhang 
                            WHERE DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?
                            AND ngay_tao_don IS NOT NULL 
                            AND YEAR(ngay_tao_don) >= 2000
                            GROUP BY ma_nv, DATE(ngay_tao_don)
                        ) daily_by_emp
                        GROUP BY ma_nv
                    ) emp_max_daily";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang]);
            $result = $stmt->fetch();
            
            return floatval($result['avg_max_daily'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getSystemMaxDailyAverageByMonth error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy số nhân viên có hoạt động trong khoảng thời gian
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeCountInRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT ma_nv) as emp_count
                    FROM donhang 
                    WHERE DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            return intval($result['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountInRange error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy số nhân viên có hoạt động trong tháng
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getEmployeeCountInMonth($thang) {
        try {
            $sql = "SELECT COUNT(DISTINCT ma_nv) as emp_count
                    FROM donhang 
                    WHERE DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?
                    AND ngay_tao_don IS NOT NULL 
                    AND YEAR(ngay_tao_don) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang]);
            $result = $stmt->fetch();
            
            return intval($result['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountInMonth error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy doanh số trung bình của tất cả nhân viên trong tháng
     * = Tổng doanh số tháng / Số nhân viên có hoạt động
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemMonthlyAveragePerEmployee($thang) {
        try {
            $total = $this->getTotalByMonth($thang);
            $empCount = $this->getEmployeeCountInMonth($thang);
            
            if ($empCount > 0) {
                return $total / $empCount;
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemMonthlyAveragePerEmployee error");
            return 0;
        }
    }

    /**
     * 🆕 Lấy doanh số trung bình của tất cả nhân viên trong khoảng thời gian
     * = Tổng doanh số khoảng / Số nhân viên có hoạt động
     * 🔧 FIX: Thêm điều kiện ngay_tao_don IS NOT NULL
     */
    public function getSystemRangeAveragePerEmployee($tu_ngay, $den_ngay) {
        try {
            $total = $this->getTotalByDateRange($tu_ngay, $den_ngay);
            $empCount = $this->getEmployeeCountInRange($tu_ngay, $den_ngay);
            
            if ($empCount > 0) {
                return $total / $empCount;
            }
            return 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemRangeAveragePerEmployee error");
            return 0;
        }
    }
}