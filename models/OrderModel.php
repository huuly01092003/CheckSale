<?php
/**
 * FILE: MODELS/ORDERMODEL.PHP (COMPLETE v3)
 * 
 * ✅ CÔNG THỨC CHI TIẾT CHO MODAL:
 * - DS TB/Ngày (NV) = Tổng DS NV trong khoảng
 * - DS TB/Ngày (Chung) = Tổng DS tất cả NV / Số ngày / Số NV
 * - DS Ngày Cao Nhất TB (Chung) = SUM(Max daily từng NV) / Số NV
 */

class OrderModel {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = Config::getPDO();
        $this->logger = new Logger();
    }

    public function importFromCSV($file) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO donhang 
                 (ma_nv, ten_nv, ngay_tao_don, ngay_tao_don_ban, loai_don, 
                  don_hang_trong_tuyen, ma_don_dat_hang, thanh_tien, tuyen_ban_hang, 
                  ma_khach_hang, ten_khach_hang, ma_san_pham, ten_san_pham, 
                  don_vi_tinh, so_luong_don_dat, so_luong_sku) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
                    $ten_nv = isset($row[4]) ? trim($row[4]) : '';
                    $tuyen_ban_hang = isset($row[5]) ? trim($row[5]) : '';
                    $ma_khach_hang = isset($row[6]) ? trim($row[6]) : '';
                    $ten_khach_hang = isset($row[7]) ? trim($row[7]) : '';
                    $don_hang_trong_tuyen = isset($row[14]) ? trim($row[14]) : '';
                    $loai_don = isset($row[15]) ? trim($row[15]) : '';
                    $ma_don_dat_hang = isset($row[16]) ? trim($row[16]) : '';
                    $ngay_val = isset($row[17]) ? trim($row[17]) : '';
                    $so_luong_sku = isset($row[23]) ? $row[23] : 0;
                    $so_luong_don_dat = isset($row[28]) ? $row[28] : 0;
                    $ngay_tao_don_ban = isset($row[39]) ? trim($row[39]) : '';
                    $ma_san_pham = isset($row[46]) ? trim($row[46]) : '';
                    $ten_san_pham = isset($row[47]) ? trim($row[47]) : '';
                    $don_vi_tinh = isset($row[48]) ? trim($row[48]) : '';
                    $tien_val = isset($row[58]) ? trim($row[58]) : '';
                    
                    if (empty($ma_nv)) {
                        $skipped++;
                        continue;
                    }
                    
                    $ngay = null;
                    if (!empty($ngay_val)) {
                        $ngay = $this->parseDate($ngay_val);
                    }
                    
                    $ngay_ban = null;
                    if (!empty($ngay_tao_don_ban)) {
                        $ngay_ban = $this->parseDate($ngay_tao_don_ban);
                    }
                    
                    $tien = $this->parseMoney($tien_val);
                    $so_luong = is_numeric($so_luong_don_dat) ? intval($so_luong_don_dat) : 0;
                    $so_luong_sku_val = is_numeric($so_luong_sku) ? intval($so_luong_sku) : 0;
                    
                    $batch[] = [
                        $ma_nv, $ten_nv, $ngay, $ngay_ban, $loai_don,
                        $don_hang_trong_tuyen, $ma_don_dat_hang, $tien, $tuyen_ban_hang,
                        $ma_khach_hang, $ten_khach_hang, $ma_san_pham, $ten_san_pham,
                        $don_vi_tinh, $so_luong, $so_luong_sku_val
                    ];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        if ($count % 10000 == 0) {
                            $this->logger->info("CSV Progress", ['rows' => $count, 'skipped' => $skipped, 'errors' => $errors]);
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
                'rows' => $count, 'skipped' => $skipped, 'errors' => $errors, 'time_sec' => $elapsed
            ]);
        } catch (Exception $e) {
            $this->logger->error("CSV Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function importFromExcelChunked($file) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file);
            $sheet = $spreadsheet->getActiveSheet();
            
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO donhang 
                 (ma_nv, ten_nv, ngay_tao_don, ngay_tao_don_ban, loai_don, 
                  don_hang_trong_tuyen, ma_don_dat_hang, thanh_tien, tuyen_ban_hang, 
                  ma_khach_hang, ten_khach_hang, ma_san_pham, ten_san_pham, 
                  don_vi_tinh, so_luong_don_dat, so_luong_sku) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $count = 0;
            $errors = 0;
            $skipped = 0;
            $maxRow = $sheet->getHighestRow();
            $startRow = 8;
            $batchSize = Config::$batch_size;
            $batch = [];
            $start_time = microtime(true);
            
            $this->logger->info("Excel Import started", ['file' => basename($file), 'rows' => $maxRow]);
            
            for ($row = $startRow; $row <= $maxRow; $row++) {
                try {
                    $ma_nv = trim($sheet->getCell('D' . $row)->getValue() ?? '');
                    $ten_nv = trim($sheet->getCell('E' . $row)->getValue() ?? '');
                    $tuyen_ban_hang = trim($sheet->getCell('F' . $row)->getValue() ?? '');
                    $ma_khach_hang = trim($sheet->getCell('G' . $row)->getValue() ?? '');
                    $ten_khach_hang = trim($sheet->getCell('H' . $row)->getValue() ?? '');
                    $don_hang_trong_tuyen = trim($sheet->getCell('O' . $row)->getValue() ?? '');
                    $loai_don = trim($sheet->getCell('P' . $row)->getValue() ?? '');
                    $ma_don_dat_hang = trim($sheet->getCell('Q' . $row)->getValue() ?? '');
                    $ngay_val = $sheet->getCell('R' . $row)->getValue();
                    $so_luong_sku = $sheet->getCell('X' . $row)->getValue();
                    $so_luong_don_dat = $sheet->getCell('AC' . $row)->getValue();
                    $ngay_tao_don_ban = $sheet->getCell('AN' . $row)->getValue();
                    $ma_san_pham = trim($sheet->getCell('AU' . $row)->getValue() ?? '');
                    $ten_san_pham = trim($sheet->getCell('AV' . $row)->getValue() ?? '');
                    $don_vi_tinh = trim($sheet->getCell('AW' . $row)->getValue() ?? '');
                    $tien_val = $sheet->getCell('BG' . $row)->getValue();
                    
                    if (empty($ma_nv)) {
                        $skipped++;
                        continue;
                    }
                    
                    $ngay = null;
                    if (!empty($ngay_val)) {
                        $ngay = $this->parseDate($ngay_val);
                    }
                    
                    $ngay_ban = null;
                    if (!empty($ngay_tao_don_ban)) {
                        $ngay_ban = $this->parseDate($ngay_tao_don_ban);
                    }
                    
                    $tien = $this->parseMoney($tien_val);
                    $so_luong = is_numeric($so_luong_don_dat) ? intval($so_luong_don_dat) : 0;
                    $so_luong_sku_val = is_numeric($so_luong_sku) ? intval($so_luong_sku) : 0;
                    
                    $batch[] = [
                        $ma_nv, $ten_nv, $ngay, $ngay_ban, $loai_don,
                        $don_hang_trong_tuyen, $ma_don_dat_hang, $tien, $tuyen_ban_hang,
                        $ma_khach_hang, $ten_khach_hang, $ma_san_pham, $ten_san_pham,
                        $don_vi_tinh, $so_luong, $so_luong_sku_val
                    ];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        if ($count % 5000 == 0) {
                            $this->logger->info("Excel Progress", ['rows' => $count, 'skipped' => $skipped, 'errors' => $errors]);
                        }
                        if ($count % 10000 == 0) {
                            $spreadsheet->disconnectWorksheets();
                            $spreadsheet = $reader->load($file);
                            $sheet = $spreadsheet->getActiveSheet();
                            $stmt = $this->pdo->prepare(
                                "INSERT IGNORE INTO donhang 
                                 (ma_nv, ten_nv, ngay_tao_don, ngay_tao_don_ban, loai_don, 
                                  don_hang_trong_tuyen, ma_don_dat_hang, thanh_tien, tuyen_ban_hang, 
                                  ma_khach_hang, ten_khach_hang, ma_san_pham, ten_san_pham, 
                                  don_vi_tinh, so_luong_don_dat, so_luong_sku) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                        }
                    }
                } catch (Exception $e) {
                    $errors++;
                    $this->logger->debug("Row $row error: " . $e->getMessage());
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
                'rows' => $count, 'skipped' => $skipped, 'errors' => $errors, 'time_sec' => $elapsed
            ]);
        } catch (Exception $e) {
            $this->logger->error("Excel Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getTotalByMonth($thang) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total
                    FROM donhang 
                    WHERE (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang, $thang]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getTotalByMonth error");
            return 0;
        }
    }

    public function getTotalByDateRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                        OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getTotalByDateRange error");
            return 0;
        }
    }

    public function getEmployeeTotalByMonth($ma_nv, $thang) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE ma_nv = ? AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $thang, $thang]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeTotalByMonth error");
            return 0;
        }
    }

    public function getEmployeeTotalByDateRange($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total 
                    FROM donhang 
                    WHERE ma_nv = ? AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                        OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeTotalByDateRange error");
            return 0;
        }
    }

    public function getAvailableMonths() {
        try {
            $sql = "SELECT DISTINCT DATE_FORMAT(COALESCE(ngay_tao_don, ngay_tao_don_ban), '%Y-%m') as thang 
                    FROM donhang 
                    WHERE COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                    ORDER BY thang DESC LIMIT 24";
            
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            $this->logger->error("getAvailableMonths error");
            return [];
        }
    }

    public function getEmployeeDaysWithOrders($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))) as days_count
                    FROM donhang 
                    WHERE ma_nv = ? AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                        OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            return intval($stmt->fetch()['days_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeDaysWithOrders error");
            return 0;
        }
    }

    public function getEmployeeCountInRange($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT COUNT(DISTINCT ma_nv) as emp_count
                    FROM donhang 
                    WHERE ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                        OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            return intval($stmt->fetch()['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountInRange error");
            return 0;
        }
    }

    public function getEmployeeCountInMonth($thang) {
        try {
            $sql = "SELECT COUNT(DISTINCT ma_nv) as emp_count
                    FROM donhang 
                    WHERE (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang, $thang]);
            return intval($stmt->fetch()['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountInMonth error");
            return 0;
        }
    }

    /**
     * 🆕 DS TB/Ngày (Chung) trong KHOẢNG
     * Formula: SUM(tất cả NV) / SoNgay / SoNhanVien
     */
    public function getSystemRangeAveragePerDay($tu_ngay, $den_ngay) {
        try {
            $total_amount = floatval($this->getTotalByDateRange($tu_ngay, $den_ngay));
            $so_nhan_vien = $this->getEmployeeCountInRange($tu_ngay, $den_ngay);
            
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);
            
            if ($so_nhan_vien > 0 && $so_ngay > 0) {
                return floatval($total_amount / $so_ngay / $so_nhan_vien);
            }
            
            return 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemRangeAveragePerDay error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * 🆕 DS TB/Ngày (Chung) trong THÁNG
     * Formula: SUM(tất cả NV tháng) / SoNgayTrongThang / SoNhanVien
     */
    public function getSystemMonthlyAveragePerDay($thang) {
        try {
            $total_amount = floatval($this->getTotalByMonth($thang));
            $so_nhan_vien = $this->getEmployeeCountInMonth($thang);
            
            $thang_start = $thang . '-01';
            $so_ngay = intval(date('t', strtotime($thang_start)));
            
            if ($so_nhan_vien > 0 && $so_ngay > 0) {
                return floatval($total_amount / $so_ngay / $so_nhan_vien);
            }
            
            return 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemMonthlyAveragePerDay error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * 🆕 DS Ngày Cao Nhất TB (Chung) trong KHOẢNG
     * Formula: SUM(Max daily của tất cả NV) / SoNhanVien
     */
    public function getSystemMaxDailyAveragePerEmployee($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT SUM(max_daily_amount) as total_max_daily, COUNT(*) as emp_count
                    FROM (
                        SELECT ma_nv, MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT ma_nv, SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                   DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                            FROM donhang 
                            WHERE ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                                OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                            AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                            GROUP BY ma_nv, DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                        ) daily_by_emp
                        GROUP BY ma_nv
                    ) emp_max_daily";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            $result = $stmt->fetch();
            
            $total_max = floatval($result['total_max_daily'] ?? 0);
            $emp_count = intval($result['emp_count'] ?? 0);
            
            return ($emp_count > 0) ? floatval($total_max / $emp_count) : 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemMaxDailyAveragePerEmployee error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * 🆕 DS Ngày Cao Nhất TB (Chung) trong THÁNG
     * Formula: SUM(Max daily của tất cả NV tháng) / SoNhanVien
     */
    public function getSystemMaxDailyAveragePerEmployeeByMonth($thang) {
        try {
            $sql = "SELECT SUM(max_daily_amount) as total_max_daily, COUNT(*) as emp_count
                    FROM (
                        SELECT ma_nv, MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT ma_nv, SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                   DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                            FROM donhang 
                            WHERE (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                            AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                            GROUP BY ma_nv, DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                        ) daily_by_emp
                        GROUP BY ma_nv
                    ) emp_max_daily";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang, $thang]);
            $result = $stmt->fetch();
            
            $total_max = floatval($result['total_max_daily'] ?? 0);
            $emp_count = intval($result['emp_count'] ?? 0);
            
            return ($emp_count > 0) ? floatval($total_max / $emp_count) : 0;
        } catch (Exception $e) {
            $this->logger->error("getSystemMaxDailyAveragePerEmployeeByMonth error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function getMaxDailyAmountByDateRange($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily_amount
                    FROM (
                        SELECT SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                               DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                        FROM donhang 
                        WHERE ma_nv = ? AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                            OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                        AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                        GROUP BY DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                    ) daily_stats";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
            return floatval($stmt->fetch()['max_daily_amount'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getMaxDailyAmountByDateRange error");
            return 0;
        }
    }

    public function getMaxDailyAmountByMonth($ma_nv, $thang) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily_amount
                    FROM (
                        SELECT SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                               DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                        FROM donhang 
                        WHERE ma_nv = ? AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                        AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                        GROUP BY DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                    ) daily_stats";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $thang, $thang]);
            return floatval($stmt->fetch()['max_daily_amount'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getMaxDailyAmountByMonth error");
            return 0;
        }
    }

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
            $this->logger->debug("Date parse error: " . $e->getMessage());
        }
        
        return null;
    }

    private function parseMoney($value) {
        if ($value === '' || $value === null) return 0;
        
        try {
            if (is_numeric($value)) {
                $money = floatval($value);
                return is_finite($money) ? $money : 0;
            }
            
            $value_str = trim($value);
            
            if ($value_str === '') {
                return 0;
            }
            
            $is_negative_accounting = (strpos($value_str, '(') !== false && strpos($value_str, ')') !== false);
            $is_negative_dash = (strpos($value_str, '-') !== false);
            $is_negative = $is_negative_accounting || $is_negative_dash;
            
            $money_str = preg_replace('/[^\d.]/', '', $value_str);
            
            if ($money_str === '' || $money_str === '.') {
                return 0;
            }
            
            $parts = explode('.', $money_str);
            if (count($parts) > 2) {
                $money_str = implode('', array_slice($parts, 0, -1)) . '.' . end($parts);
            }
            
            $money = floatval($money_str);
            
            if ($is_negative && $money > 0) {
                $money = -$money;
            }
            
            return is_finite($money) ? $money : 0;
        } catch (Exception $e) {
            $this->logger->debug("Money parse error: " . $e->getMessage());
            return 0;
        }
    }

    private function executeBatch($stmt, $batch) {
        foreach ($batch as $item) {
            try {
                $stmt->execute($item);
            } catch (Exception $e) {
                $this->logger->debug("Batch insert error: " . $e->getMessage());
            }
        }
    }

/**
 * ADD THESE METHODS TO OrderModel CLASS
 * Thêm vào cuối class OrderModel, trước dấu }
 */

/**
 * Lấy dữ liệu KPI hàng ngày cho nhân viên
 * Trả về: order_count, total_amount, order_date
 */
public function getEmployeeDailyKPI($ma_nv, $tu_ngay, $den_ngay, $product_filter = '') {
    try {
        $sql = "SELECT 
                    DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date,
                    COUNT(*) as order_count,
                    COALESCE(SUM(CAST(thanh_tien as DECIMAL(12,2))), 0) as total_amount,
                    COUNT(DISTINCT ma_khach_hang) as unique_customers,
                    COUNT(DISTINCT ma_san_pham) as unique_products
                FROM donhang 
                WHERE ma_nv = ? 
                AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                    OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
        
        $params = [$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay];
        
        if (!empty($product_filter)) {
            $sql .= " AND ma_san_pham LIKE ?";
            $params[] = $product_filter . '%';
        }
        
        $sql .= " GROUP BY DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                  ORDER BY order_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $this->logger->error("getEmployeeDailyKPI error", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Lấy dữ liệu KPI hàng ngày cho toàn hệ thống
 * Dùng để tính benchmark
 */
public function getSystemDailyKPI($tu_ngay, $den_ngay, $product_filter = '') {
    try {
        $sql = "SELECT 
                    DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date,
                    COUNT(*) as total_orders,
                    COALESCE(SUM(CAST(thanh_tien as DECIMAL(12,2))), 0) as total_amount,
                    COUNT(DISTINCT ma_nv) as emp_count,
                    COUNT(DISTINCT ma_khach_hang) as customer_count
                FROM donhang 
                WHERE ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                    OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?))
                AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
        
        $params = [$tu_ngay, $den_ngay, $tu_ngay, $den_ngay];
        
        if (!empty($product_filter)) {
            $sql .= " AND ma_san_pham LIKE ?";
            $params[] = $product_filter . '%';
        }
        
        $sql .= " GROUP BY DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))
                  ORDER BY order_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $this->logger->error("getSystemDailyKPI error", ['error' => $e->getMessage()]);
        return [];
    }
}

/**
 * Lấy thống kê chi tiết nhân viên theo tháng
 */
public function getEmployeeKPIStats($ma_nv, $thang) {
    try {
        $sql = "SELECT 
                    ma_nv,
                    COUNT(*) as total_orders,
                    COUNT(DISTINCT DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban))) as working_days,
                    COALESCE(SUM(CAST(thanh_tien as DECIMAL(12,2))), 0) as total_amount,
                    COALESCE(AVG(CAST(thanh_tien as DECIMAL(12,2))), 0) as avg_amount,
                    COUNT(DISTINCT ma_khach_hang) as customer_count,
                    COUNT(DISTINCT ma_san_pham) as product_count,
                    COALESCE(MAX(CAST(thanh_tien as DECIMAL(12,2))), 0) as max_transaction,
                    COALESCE(MIN(CAST(thanh_tien as DECIMAL(12,2))), 0) as min_transaction
                FROM donhang 
                WHERE ma_nv = ? 
                AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000
                GROUP BY ma_nv";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ma_nv, $thang, $thang]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $this->logger->error("getEmployeeKPIStats error", ['error' => $e->getMessage()]);
        return [];
    }
}
    public function getAvailableProducts() {
    try {
        $sql = "SELECT DISTINCT SUBSTRING(ma_san_pham, 1, 2) as product_prefix
                FROM donhang 
                WHERE ma_san_pham IS NOT NULL 
                AND ma_san_pham != ''
                ORDER BY product_prefix ASC";
        
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        $this->logger->error("getAvailableProducts error", ['error' => $e->getMessage()]);
        return [];
    }
}
}