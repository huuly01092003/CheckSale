<?php
class EmployeeModel {
    private $pdo;

    public function __construct() {
        $this->pdo = Config::getPDO();
    }

    public function importFromExcelChunked($file) {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();
        
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) VALUES (?, ?, ?, ?, ?)");
        
        $count = 0;
        $batch_size = 1000;
        $maxRow = $sheet->getHighestRow();
        
        for ($row = 2; $row <= $maxRow; $row++) {
            $tinh = trim($sheet->getCell('B' . $row)->getValue() ?? '');
            $gs = trim($sheet->getCell('D' . $row)->getValue() ?? '');
            $ma_nv = trim($sheet->getCell('G' . $row)->getValue() ?? '');
            $ten_nv = trim($sheet->getCell('H' . $row)->getValue() ?? '');
            $ngay_val = $sheet->getCell('J' . $row)->getValue();
            
            if (empty($ma_nv)) continue;
            
            try {
                $ngay = null;
                if (!empty($ngay_val)) {
                    if (is_numeric($ngay_val)) {
                        $ngay = date('Y-m-d', ($ngay_val - 25569) * 86400);
                    } else {
                        $ngay = date('Y-m-d', strtotime($ngay_val));
                    }
                }
                
                $stmt->execute([$ma_nv, $ten_nv, $ngay, $tinh, $gs]);
                $count++;
                
                // Unload memory mỗi batch
                if ($count % $batch_size == 0) {
                    $spreadsheet->disconnectWorksheets();
                    $spreadsheet = $reader->load($file);
                    $sheet = $spreadsheet->getActiveSheet();
                    $stmt = $this->pdo->prepare("INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) VALUES (?, ?, ?, ?, ?)");
                }
            } catch (Exception $e) {
                error_log("Import employee error row $row: " . $e->getMessage());
                continue;
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function importFromExcel($data) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) VALUES (?, ?, ?, ?, ?)");
        
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            
            if (empty($row) || !isset($row[6])) continue;
            
            $ma_nv = trim($row[6] ?? '');
            $ten_nv = trim($row[7] ?? '');
            $tinh = trim($row[1] ?? '');
            $gs = trim($row[3] ?? '');
            
            if (empty($ma_nv)) continue;
            
            $ngay = null;
            if (!empty($row[9])) {
                try {
                    if (is_numeric($row[9])) {
                        $ngay = date('Y-m-d', ($row[9] - 25569) * 86400);
                    } else {
                        $ngay = date('Y-m-d', strtotime($row[9]));
                    }
                } catch (Exception $e) {
                    $ngay = null;
                }
            }
            
            try {
                $stmt->execute([$ma_nv, $ten_nv, $ngay, $tinh, $gs]);
            } catch (Exception $e) {
                error_log("Import employee error: " . $e->getMessage());
            }
        }
    }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM nhanvien ORDER BY ma_nv")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>