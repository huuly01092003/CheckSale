<?php
/**
 * FILE 4: MODELS/ORDERMODEL.PHP (FIXED)
 * 
 * ✅ BỎ cột loai_don - Lấy dữ liệu dựa vào KHOẢNG NGÀY
 * 
 * CÔNG THỨC:
 * - Tổng tiền kỳ (LAY) = SUM toàn tháng
 * - Tổng tiền khoảng (XEM) = SUM khoảng ngày chọn
 * - Kết quả chung = Tổng tiền khoảng / Tổng tiền kỳ
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
     * Chỉ import 3 cột: ma_nv, ngay_tao_don, thanh_tien
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
            $start_time = microtime(true);
            
            $handle = fopen($file, 'r');
            if (!$handle) {
                throw new Exception("Không thể mở file CSV");
            }
            
            fgetcsv($handle, 0, ',', '"');
            
            $this->logger->info("CSV Import started", ['file' => basename($file)]);
            
            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                try {
                    // Cột D (3) = ma_nv, Cột R (17) = ngay_tao_don, Cột BG (58) = thanh_tien
                    $ma_nv = isset($row[3]) ? trim($row[3]) : '';
                    $ngay_val = isset($row[17]) ? trim($row[17]) : '';
                    $tien_val = isset($row[58]) ? trim($row[58]) : '';
                    
                    if (empty($ma_nv)) continue;
                    
                    $ngay = $this->parseDate($ngay_val);
                    if (!$ngay) $ngay = date('Y-m-d');
                    
                    $tien = $this->parseMoney($tien_val);
                    
                    // ← Chỉ insert 3 cột (BỎ loai_don)
                    $batch[] = [$ma_nv, $ngay, $tien];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        
                        if ($count % 10000 == 0) {
                            $this->logger->info("CSV Progress", ['rows' => $count]);
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
                    
                    $ngay = $this->parseDate($ngay_val);
                    if (!$ngay) $ngay = date('Y-m-d');
                    
                    $tien = $this->parseMoney($tien_val);
                    
                    // ← Chỉ insert 3 cột (BỎ loai_don)
                    $batch[] = [$ma_nv, $ngay, $tien];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        
                        if ($count % 5000 == 0) {
                            $this->logger->info("Excel Progress", ['rows' => $count]);
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
     * = SUM(thanh_tien) WHERE thang = thang_chon
     * Dùng làm MẦU SỐ
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
     * = SUM(thanh_tien) WHERE ngay >= tu_ngay AND ngay <= den_ngay
     * Dùng làm TỬ SỐ
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
     * = SUM(thanh_tien) WHERE ma_nv = nv AND thang = thang_chon
     * Dùng làm DS_TIM_KIEM
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
     * = SUM(thanh_tien) WHERE ma_nv = nv AND ngay >= tu_ngay AND ngay <= den_ngay
     * Dùng làm DS_TIEN_DO
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
        if (empty($value)) return 0;
        
        try {
            if (is_numeric($value)) {
                $money = floatval($value);
            } else {
                $money_str = preg_replace('/[^\d.]/', '', trim($value));
                $money = floatval($money_str);
            }
            
            if ($money >= 0 && $money < 1000000000) {
                return $money;
            }
        } catch (Exception $e) {
            $this->logger->debug("Money parse error");
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
}