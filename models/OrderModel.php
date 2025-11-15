<?php
class OrderModel {
    private $pdo;

    public function __construct() {
        $this->pdo = Config::getPDO();
    }

    /**
     * Import đơn hàng từ Excel (xử lý từng chunk để tiết kiệm memory)
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
            $startRow = 8; // Hàng bắt đầu dữ liệu
            $batchSize = 500;
            $batch = [];
            
            error_log("=== Order import started ===");
            error_log("Max row: $maxRow, Start row: $startRow");
            
            for ($row = $startRow; $row <= $maxRow; $row++) {
                try {
                    // Lấy giá trị từ các cell
                    $ma_nv = trim($sheet->getCell('D' . $row)->getValue() ?? '');
                    $ngay_val = $sheet->getCell('R' . $row)->getValue();
                    
                    // === SỬA (1/2): Đổi cột đọc tiền từ 'T' sang 'F' ===
                    // Dựa trên file image_3d08c0.jpg, cột TỔNG TIỀN ĐƠN ĐẶT là cột F
                    $tien_val = $sheet->getCell('BG' . $row)->getValue();
                    // === KẾT THÚC SỬA (1/2) ===
                    
                    if (empty($ma_nv)) {
                        continue;
                    }
                    
                    // ===== XỬ LÝ NGÀY =====
                    $ngay = null;
                    if (!empty($ngay_val)) {
                        try {
                            // Nếu là số Excel
                            if (is_numeric($ngay_val)) {
                                // Excel date serial (từ 1899-12-30)
                                $ngay = date('Y-m-d', ($ngay_val - 25569) * 86400);
                            } else {
                                // === SỬA (2/2): Sửa lỗi parse ngày DD/MM/YYYY ===
                                // Nếu là chuỗi, parse nó
                                // $ngay = date('Y-m-d', strtotime($ngay_val)); // Lỗi: strtotime hiểu là m/d/Y
                                
                                // Lấy phần ngày (trước dấu cách)
                                $date_part = explode(' ', $ngay_val)[0];
                                // Parse theo đúng định dạng d/m/Y
                                $dateObj = DateTime::createFromFormat('d/m/Y', $date_part); 
                                
                                if ($dateObj) {
                                    $ngay = $dateObj->format('Y-m-d'); // Chuyển về Y-m-d cho DB
                                } else {
                                    $ngay = null;
                                }
                                // === KẾT THÚC SỬA (2/2) ===
                            }
                            
                            // Validate ngày hợp lệ (năm >= 2000)
                            if (strtotime($ngay) < strtotime('2000-01-01')) {
                                error_log("Row $row: Invalid date $ngay_val -> $ngay");
                                $ngay = null;
                            }
                        } catch (Exception $e) {
                            error_log("Row $row: Date parse error: " . $e->getMessage());
                            $ngay = null;
                        }
                    }
                    
                    if ($ngay === null) {
                        $ngay = date('Y-m-d'); // Mặc định hôm nay
                    }
                    
                    // ===== XỬ LÝ TIỀN =====
                    $tien = 0;
                    if (!empty($tien_val)) {
                        try {
                            // Nếu là số
                            if (is_numeric($tien_val)) {
                                $tien = floatval($tien_val);
                            } else {
                                // Nếu là chuỗi, bỏ các ký tự không phải số
                                $tien_str = trim($tien_val);
                                // Bỏ: dấu phẩy, khoảng trắng, ký tự đặc biệt
                                $tien_str = preg_replace('/[^\d.]/', '', $tien_str);
                                $tien = floatval($tien_str);
                            }
                            
                            // Validate tiền hợp lệ (> 0 và < 1 tỷ)
                            if ($tien < 0 || $tien > 1000000000) {
                                error_log("Row $row: Invalid price $tien_val -> $tien");
                                $tien = 0;
                            }
                        } catch (Exception $e) {
                            error_log("Row $row: Price parse error: " . $e->getMessage());
                            $tien = 0;
                        }
                    }
                    
                    // Thêm vào batch
                    $batch[] = [$ma_nv, $ngay, $tien];
                    $count++;
                    
                    // Thực thi batch
                    if (count($batch) >= $batchSize) {
                        $this->executeBatch($stmt, $batch);
                        $batch = [];
                        
                        // Log progress
                        if ($count % 5000 == 0) {
                            error_log("Order import progress: $count rows processed");
                        }
                        
                        // Unload để tiết kiệm memory
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
                    if ($errors <= 20) {
                        error_log("Row $row error: " . $e->getMessage());
                    }
                    continue;
                }
            }
            
            // Thực thi batch cuối cùng
            if (!empty($batch)) {
                $this->executeBatch($stmt, $batch);
            }
            
            error_log("Order import completed: $count rows, $errors errors");
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
        } catch (Exception $e) {
            error_log("importFromExcelChunked error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function executeBatch($stmt, $batch) {
        foreach ($batch as $item) {
            try {
                $stmt->execute($item);
            } catch (Exception $e) {
                error_log("Batch insert error: " . $e->getMessage());
            }
        }
    }

    public function getTotalByPeriod($start, $end) {
        try {
            if (!$start || !$end) {
                return 0;
            }
            
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total FROM donhang 
                    WHERE ngay_tao_don IS NOT NULL 
                    AND ngay_tao_don != '0000-00-00'
                    AND YEAR(ngay_tao_don) >= 1990
                    AND ngay_tao_don <= CURDATE()
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$start, $end]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? floatval($result['total']) : 0;
        } catch (Exception $e) {
            error_log("getTotalByPeriod error: " . $e->getMessage());
            return 0;
        }
    }

    public function getByEmployeeAndPeriod($ma_nv, $start, $end) {
        try {
            if (!$ma_nv || !$start || !$end) {
                return 0;
            }
            
            $sql = "SELECT COALESCE(SUM(thanh_tien), 0) as total FROM donhang 
                    WHERE ma_nv = ? 
                    AND ngay_tao_don IS NOT NULL 
                    AND ngay_tao_don != '0000-00-00'
                    AND YEAR(ngay_tao_don) >= 1990
                    AND ngay_tao_don <= CURDATE()
                    AND DATE(ngay_tao_don) >= ?
                    AND DATE(ngay_tao_don) <= ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv, $start, $end]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? floatval($result['total']) : 0;
        } catch (Exception $e) {
            error_log("getByEmployeeAndPeriod error: " . $e->getMessage());
            return 0;
        }
    }

    public function getPeriodRange() {
        try {
            $sql = "SELECT 
                        MIN(ngay_tao_don) AS min, 
                        MAX(ngay_tao_don) AS max 
                    FROM donhang 
                    WHERE ngay_tao_don IS NOT NULL 
                    AND ngay_tao_don != '0000-00-00'
                    AND YEAR(ngay_tao_don) >= 1990
                    AND ngay_tao_don <= CURDATE()";
            
            $result = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['min'] || !$result['max']) {
                return [
                    'min' => date('Y-m-d', strtotime('-30 days')),
                    'max' => date('Y-m-d')
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("getPeriodRange error: " . $e->getMessage());
            return [
                'min' => date('Y-m-d', strtotime('-30 days')),
                'max' => date('Y-m-d')
            ];
        }
    }
}
?>