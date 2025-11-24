<?php
/**
 * FILE: MODELS/EMPLOYEEMODEL.PHP (COMPLETE v4)
 */

class EmployeeModel {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = Config::getPDO();
        $this->logger = new Logger();
    }

    // ========== IMPORT FROM CSV ==========
    public function importFromCSV($file) {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            
            $batch = [];
            $batchSize = Config::$batch_size;
            $count = 0;
            $start_time = microtime(true);
            
            $handle = fopen($file, 'r');
            if (!$handle) {
                throw new Exception("Không thể mở file CSV");
            }
            
            fgetcsv($handle, 0, ',', '"');
            
            $this->logger->info("Employee CSV Import started", ['file' => basename($file)]);
            
            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                try {
                    $tinh = isset($row[1]) ? trim($row[1]) : '';
                    $gs = isset($row[3]) ? trim($row[3]) : '';
                    $ma_nv = isset($row[6]) ? trim($row[6]) : '';
                    $ten_nv = isset($row[7]) ? trim($row[7]) : '';
                    $ngay_val = isset($row[9]) ? $row[9] : '';
                    
                    if (empty($ma_nv)) continue;
                    
                    $ngay = $this->parseDate($ngay_val);
                    
                    $batch[] = [$ma_nv, $ten_nv, $ngay, $tinh, $gs];
                    $count++;
                    
                    if (count($batch) >= $batchSize) {
                        foreach ($batch as $item) {
                            try {
                                $stmt->execute($item);
                            } catch (Exception $e) {
                                $this->logger->debug("Insert error");
                            }
                        }
                        $batch = [];
                        
                        if ($count % 5000 == 0) {
                            $this->logger->info("Employee CSV Progress", ['rows' => $count]);
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            if (!empty($batch)) {
                foreach ($batch as $item) {
                    $stmt->execute($item);
                }
            }
            
            fclose($handle);
            $elapsed = round(microtime(true) - $start_time, 2);
            
            $this->logger->success("Employee CSV Import completed", [
                'rows' => $count,
                'time_sec' => $elapsed
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Employee CSV Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // ========== IMPORT FROM EXCEL ==========
    public function importFromExcelChunked($file) {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file);
            $sheet = $spreadsheet->getActiveSheet();
            
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            
            $count = 0;
            $batch_size = Config::$batch_size;
            $maxRow = $sheet->getHighestRow();
            $start_time = microtime(true);
            
            $this->logger->info("Employee Excel Import started", [
                'file' => basename($file),
                'rows' => $maxRow
            ]);
            
            for ($row = 2; $row <= $maxRow; $row++) {
                try {
                    $tinh = trim($sheet->getCell('B' . $row)->getValue() ?? '');
                    $gs = trim($sheet->getCell('D' . $row)->getValue() ?? '');
                    $ma_nv = trim($sheet->getCell('G' . $row)->getValue() ?? '');
                    $ten_nv = trim($sheet->getCell('H' . $row)->getValue() ?? '');
                    $ngay_val = $sheet->getCell('J' . $row)->getValue();
                    
                    if (empty($ma_nv)) continue;
                    
                    $ngay = $this->parseDate($ngay_val);
                    
                    $stmt->execute([$ma_nv, $ten_nv, $ngay, $tinh, $gs]);
                    $count++;
                    
                    if ($count % 10000 == 0) {
                        $spreadsheet->disconnectWorksheets();
                        $spreadsheet = $reader->load($file);
                        $sheet = $spreadsheet->getActiveSheet();
                        $stmt = $this->pdo->prepare(
                            "INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) 
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $this->logger->info("Employee Excel Progress", ['rows' => $count]);
                    }
                } catch (Exception $e) {
                    $this->logger->debug("Row error: " . $e->getMessage());
                    continue;
                }
            }
            
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
            $elapsed = round(microtime(true) - $start_time, 2);
            
            $this->logger->success("Employee Excel Import completed", [
                'rows' => $count,
                'time_sec' => $elapsed
            ]);
            
        } catch (Exception $e) {
            $this->logger->error("Employee Excel Import error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // ========== GET ALL EMPLOYEES ==========
    public function getAll() {
        try {
            $sql = "SELECT * FROM nhanvien ORDER BY ma_nv ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getAll error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ========== GET EMPLOYEE BY CODE ==========
    public function getByCode($ma_nv) {
        try {
            $sql = "SELECT * FROM nhanvien WHERE ma_nv = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_nv]);
            return $stmt->fetch();
        } catch (Exception $e) {
            $this->logger->error("getByCode error");
            return null;
        }
    }

    // ========== GET EMPLOYEES BY GS ==========
    public function getByGS($gs) {
        try {
            $sql = "SELECT * FROM nhanvien WHERE gs = ? ORDER BY ma_nv ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$gs]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getByGS error");
            return [];
        }
    }

    // ========== GET AVAILABLE GS (GIÁM SÁT) ==========
    public function getAvailableGS() {
        try {
            $sql = "SELECT DISTINCT gs FROM nhanvien WHERE gs IS NOT NULL AND gs != '' ORDER BY gs ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            $this->logger->error("getAvailableGS error");
            return [];
        }
    }

    // ========== GET AVAILABLE PROVINCES ==========
    public function getAvailableProvinces() {
        try {
            $sql = "SELECT DISTINCT tinh FROM nhanvien WHERE tinh IS NOT NULL AND tinh != '' ORDER BY tinh ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            $this->logger->error("getAvailableProvinces error");
            return [];
        }
    }

    // ========== GET STATISTICS BY GS ==========
    public function getStatisticsByGS() {
        try {
            $sql = "SELECT gs, COUNT(*) as total, 
                           MIN(ngay_vao_cong_ty) as oldest_date,
                           MAX(ngay_vao_cong_ty) as newest_date
                    FROM nhanvien 
                    WHERE gs IS NOT NULL AND gs != ''
                    GROUP BY gs 
                    ORDER BY gs ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $this->logger->error("getStatisticsByGS error");
            return [];
        }
    }

    // ========== GET TOTAL COUNT ==========
    public function getTotalCount() {
        try {
            return $this->pdo->query("SELECT COUNT(*) FROM nhanvien")->fetchColumn();
        } catch (Exception $e) {
            $this->logger->error("getTotalCount error");
            return 0;
        }
    }

    // ========== SEARCH EMPLOYEES ==========
    public function search($keyword) {
        try {
            $keyword = '%' . $keyword . '%';
            $sql = "SELECT * FROM nhanvien 
                    WHERE ma_nv LIKE ? OR ten_nv LIKE ? OR tinh LIKE ? OR gs LIKE ?
                    ORDER BY ma_nv ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$keyword, $keyword, $keyword, $keyword]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("search error");
            return [];
        }
    }

    // ========== HELPER: Parse Date ==========
    private function parseDate($value) {
        if (empty($value)) return null;
        
        try {
            if (is_numeric($value)) {
                $date = date('Y-m-d', ($value - 25569) * 86400);
            } else {
                $value = trim($value);
                $dateObj = DateTime::createFromFormat('d/m/Y', $value);
                $date = $dateObj ? $dateObj->format('Y-m-d') : null;
            }
            return $date;
        } catch (Exception $e) {
            $this->logger->debug("Date parse error");
            return null;
        }
    }
}