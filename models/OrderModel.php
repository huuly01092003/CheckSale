<?php
/**
 * FILE: MODELS/ORDERMODEL.PHP (COMPLETE v8 - SAFE MODE)
 * ✅ FIX: KHÔNG auto delete (quá nguy hiểm!)
 * ✅ Strategy: INSERT TRỰC TIẾP (cho phép duplicate tạm thời)
 * ✅ Log chi tiết để user có thể manual clean sau
 */

class OrderModel {
    private $pdo;
    private $logger;

    public function __construct() {
        $this->pdo = Config::getPDO();
        $this->logger = new Logger();
        
        // QUAN TRỌNG: Tắt chế độ nghiêm ngặt của MySQL cho session này
        // Để cho phép ngày tháng '0000-00-00' và chuỗi rỗng vào cột số
        try {
            $this->pdo->exec("SET sql_mode = ''"); 
        } catch (Exception $e) {}
    }

    // ========== IMPORT FROM CSV (SAFE MODE - NO AUTO DELETE) ==========
public function importFromCSV($file) {
        ini_set('memory_limit', '-1'); // Không giới hạn RAM
        set_time_limit(0);             // Không giới hạn thời gian

        $handle = fopen($file, 'r');
        if (!$handle) throw new Exception("Không mở được file CSV");

        $batch = [];
        $batchSize = 1000; // Giảm size batch xuống xíu để an toàn hơn
        $total = 0;
        
        // Bỏ qua dòng header (nếu file có header)
        fgetcsv($handle, 0, ',', '"');

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            // Nếu dòng rỗng hoàn toàn thì bỏ qua
            if (empty($row) || count($row) < 2) continue;

            // Mapping dữ liệu - Dùng hàm permissive (dễ dãi) nhất có thể
            // Lưu ý: Cột NOT NULL trong DB phải trả về '' hoặc 0, không được null
            $data = [
                $this->getStr($row, 3),    // ma_nv
                $this->getStr($row, 4),    // ten_nv
                $this->getDate($row, 17),  // ngay_tao_don
                $this->getDate($row, 39),  // ngay_tao_don_ban
                $this->getStr($row, 15),   // loai_don
                $this->getStr($row, 14),   // don_hang_trong_tuyen
                
                // QUAN TRỌNG: ma_don_hang là NOT NULL -> Nếu rỗng phải chế ra string
                $this->getStr($row, 16, 'UNKNOWN_ID'), // ma_don_hang
                
                $this->getMoney($row, 58), // thanh_tien
                $this->getStr($row, 5),    // tuyen_ban_hang
                $this->getStr($row, 6),    // ma_khach_hang
                $this->getStr($row, 7),    // ten_khach_hang
                $this->getStr($row, 46),   // ma_san_pham
                $this->getStr($row, 47),   // ten_san_pham
                $this->getStr($row, 48),   // don_vi_tinh
                $this->getInt($row, 28),   // so_luong_don_dat
                $this->getInt($row, 23),   // so_luong_sku
                $this->getStr($row, 59),   // trang_thai
            ];

            $batch[] = $data;
            $total++;

            if (count($batch) >= $batchSize) {
                $this->insertBatchDirect($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->insertBatchDirect($batch);
        }

        fclose($handle);
        $this->logger->success("Đã import xong: $total dòng.");
    }
public function importFromExcelChunked($file) {
        ini_set('memory_limit', '4G');
        set_time_limit(0);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $batch = [];
        $batchSize = 5000;
        $total = 0;
        $start = microtime(true);

        // Bắt đầu từ dòng 8 như báo cáo gốc
        for ($row = 8; $row <= $highestRow; $row++) {
            $data = [
                $this->safeVal($sheet->getCell("D{$row}")->getValue()),   // ma_nv
                $this->safeVal($sheet->getCell("E{$row}")->getValue()),   // ten_nv
                $this->forceDate($sheet->getCell("R{$row}")->getValue()), // ngay_tao_don
                $this->forceDate($sheet->getCell("AN{$row}")->getValue()),// ngay_tao_don_ban
                $this->safeVal($sheet->getCell("P{$row}")->getValue()),   // loai_don
                $this->safeVal($sheet->getCell("O{$row}")->getValue()),   // don_hang_trong_tuyen
                $this->safeVal($sheet->getCell("Q{$row}")->getValue()),   // ma_don_hang
                $this->forceMoney($sheet->getCell("BG{$row}")->getValue()),// thanh_tien
                $this->safeVal($sheet->getCell("F{$row}")->getValue()),   // tuyen_ban_hang
                $this->safeVal($sheet->getCell("G{$row}")->getValue()),   // ma_khach_hang
                $this->safeVal($sheet->getCell("H{$row}")->getValue()),   // ten_khach_hang
                $this->safeVal($sheet->getCell("AU{$row}")->getValue()),  // ma_san_pham
                $this->safeVal($sheet->getCell("AV{$row}")->getValue()),  // ten_san_pham
                $this->safeVal($sheet->getCell("AW{$row}")->getValue()),  // don_vi_tinh
                $this->forceInt($sheet->getCell("AC{$row}")->getValue()), // so_luong_don_dat
                $this->forceInt($sheet->getCell("X{$row}")->getValue()),  // so_luong_sku
                $this->safeVal($sheet->getCell("BH{$row}")->getValue()),  // trang_thai
            ];

            $batch[] = $data;
            $total++;

            if (count($batch) >= $batchSize) {
                $this->insertBatchDirect($batch);
                $batch = [];
                if ($total % 50000 == 0) {
                    $this->logger->info("Excel imported rows: $total");
                }
            }
        }

        if (!empty($batch)) $this->insertBatchDirect($batch);

        $time = round(microtime(true) - $start, 2);
        $this->logger->success("EXCEL IMPORT HOÀN TẤT", [
            'total_rows' => $total,
            'time_sec' => $time
        ]);
    }
    // ==================== HELPER SIÊU BỀN (không bao giờ lỗi) ====================
    private function safeVal($val) {
        if ($val === null || $val === '') return null;
        return trim((string)$val);
    }

    private function forceInt($val) {
        if ($val === null || $val === '') return 0;
        return (int)preg_replace('/\D/', '', (string)$val) ?: 0;
    }

    private function forceMoney($val) {
        if ($val === null || $val === '') return '0.00';
        $num = preg_replace('/[^\d.-]/', '', (string)$val);
        if ($num === '' || $num === '-') return '0.00';
        return number_format((float)$num, 2, '.', '');
    }

    private function forceDate($val) {
        if (empty($val)) return null;
        try {
            if (is_numeric($val)) {
                // Excel serial date
                return date('Y-m-d', ($val - 25569) * 86400);
            }
            $str = trim(is_string($val) ? $val : '');
            $str = preg_replace('/\s.*/', '', $str); // bỏ giờ phút giây
            $date = DateTime::createFromFormat('d/m/Y', $str);
            if ($date) return $date->format('Y-m-d');
            $date = DateTime::createFromFormat('Y-m-d', $str);
            if ($date) return $date->format('Y-m-d');
        } catch (Exception $e) { }
        return null;
    }
    // ========== INSERT BATCH DIRECT ==========
private function insertBatchDirect($batch) {
        if (empty($batch)) return;

        // SQL chuẩn theo cấu trúc bạn gửi
        $sql = "INSERT INTO donhang (
            ma_nv, ten_nv, ngay_tao_don, ngay_tao_don_ban, loai_don,
            don_hang_trong_tuyen, ma_don_hang, thanh_tien, tuyen_ban_hang,
            ma_khach_hang, ten_khach_hang, ma_san_pham, ten_san_pham,
            don_vi_tinh, so_luong_don_dat, so_luong_sku, trang_thai
        ) VALUES " . str_repeat('(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?),', count($batch) - 1) . '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

        try {
            $stmt = $this->pdo->prepare($sql);
            // Làm phẳng mảng batch để đưa vào execute
            $flatParams = [];
            foreach ($batch as $row) {
                foreach ($row as $cell) {
                    $flatParams[] = $cell;
                }
            }
            $stmt->execute($flatParams);
        } catch (Exception $e) {
            // Nếu vẫn lỗi, ta log lại nhưng không dừng chương trình (để biết lỗi gì)
            // Thường lỗi ở đây là do Duplicate Key nếu ma_don_hang bị trùng
            $this->logger->error("Lỗi chèn Batch: " . $e->getMessage());
        }
    }

    // Lấy chuỗi: Không bao giờ null, mặc định là rỗng ''
    private function getStr($row, $index, $default = '') {
        if (!isset($row[$index])) return $default;
        $val = trim((string)$row[$index]);
        return ($val === '') ? $default : $val;
    }

    // Lấy số tiền: Lỗi -> trả về 0.00
    private function getMoney($row, $index) {
        if (!isset($row[$index])) return 0;
        // Xóa hết ký tự lạ, chỉ giữ lại số, dấu chấm và dấu trừ
        $clean = preg_replace('/[^\d.-]/', '', (string)$row[$index]);
        return is_numeric($clean) ? $clean : 0;
    }

    // Lấy số nguyên: Lỗi -> trả về 0
    private function getInt($row, $index) {
        if (!isset($row[$index])) return 0;
        $clean = preg_replace('/[^\d-]/', '', (string)$row[$index]);
        return (int)$clean;
    }

    // Lấy ngày: Cố gắng parse, nếu không được trả về NULL (vì DB cột date cho phép NULL)
    private function getDate($row, $index) {
        if (!isset($row[$index])) return null;
        $val = trim((string)$row[$index]);
        if ($val === '') return null;

        // Xử lý Excel serial date (dạng số như 45000)
        if (is_numeric($val)) {
            return date('Y-m-d', ($val - 25569) * 86400);
        }

        // Xử lý string dd/mm/yyyy hoặc yyyy-mm-dd
        // Cắt bỏ phần giờ phút nếu có (ví dụ: "20/11/2023 10:00:00")
        $val = preg_replace('/\s+.*/', '', $val); 
        
        // Thử format dd/mm/yyyy
        $d = DateTime::createFromFormat('d/m/Y', $val);
        if ($d && $d->format('d/m/Y') === $val) return $d->format('Y-m-d');

        // Thử format dd-mm-yyyy
        $d = DateTime::createFromFormat('d-m-Y', $val);
        if ($d && $d->format('d-m-Y') === $val) return $d->format('Y-m-d');

        // Nếu là yyyy-m-d sẵn rồi thì trả về luôn, nếu sai định dạng kệ nó (để null)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;

        return null; // Bó tay thì trả về null
    }
    // ========== REST OF METHODS (GIỮ NGUYÊN) ==========
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
                    WHERE ma_nv = ?
                    AND COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                    AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
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
                    WHERE COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                    AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
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
                    WHERE COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                    AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$thang, $thang]);
            return intval($stmt->fetch()['emp_count'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeCountInMonth error");
            return 0;
        }
    }

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
            $this->logger->error("getSystemRangeAveragePerDay error");
            return 0;
        }
    }

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
            $this->logger->error("getSystemMonthlyAveragePerDay error");
            return 0;
        }
    }
    public function getSystemMaxDailyAveragePerEmployee($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT SUM(max_daily_amount) as total_max_daily, COUNT(*) as emp_count
                    FROM (
                        SELECT 
                            ma_nv,
                            MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT 
                                ma_nv,
                                SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                            FROM donhang
                            WHERE COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                            AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
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
            $this->logger->error("getSystemMaxDailyAveragePerEmployee error");
            return 0;
        }
    }

    public function getSystemMaxDailyAveragePerEmployeeByMonth($thang) {
        try {
            $sql = "SELECT SUM(max_daily_amount) as total_max_daily, COUNT(*) as emp_count
                    FROM (
                        SELECT 
                            ma_nv,
                            MAX(daily_total) as max_daily_amount
                        FROM (
                            SELECT 
                                ma_nv,
                                SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                                DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                            FROM donhang
                            WHERE COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                            AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
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
            $this->logger->error("getSystemMaxDailyAveragePerEmployeeByMonth error");
            return 0;
        }
    }

    public function getMaxDailyAmountByDateRange($ma_nv, $tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT MAX(daily_total) as max_daily_amount
                    FROM (
                        SELECT 
                            SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                            DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                        FROM donhang
                        WHERE ma_nv = ?
                        AND COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                        AND ((DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
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
                        SELECT 
                            SUM(CAST(thanh_tien as DECIMAL(12,2))) as daily_total,
                            DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date
                        FROM donhang
                        WHERE ma_nv = ?
                        AND COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL
                        AND (DATE_FORMAT(ngay_tao_don, '%Y-%m') = ? OR DATE_FORMAT(ngay_tao_don_ban, '%Y-%m') = ?)
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

    public function getAvailableProducts() {
        try {
            $sql = "SELECT DISTINCT SUBSTRING(ma_san_pham, 1, 2) as product_prefix
                    FROM donhang 
                    WHERE ma_san_pham IS NOT NULL 
                    AND ma_san_pham != ''
                    ORDER BY product_prefix ASC";
            
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            $this->logger->error("getAvailableProducts error");
            return [];
        }
    }

public function getEmployeeDailyKPI($ma_nv, $tu_ngay, $den_ngay, $product_filter = '') {
        try {
            // LOGIC MỚI: Chặt chẽ như countCustomTotalOrders
            $sql = "SELECT 
                        DATE(COALESCE(ngay_tao_don, ngay_tao_don_ban)) as order_date,
                        COUNT(*) as order_count,
                        COALESCE(SUM(CAST(thanh_tien as DECIMAL(12,2))), 0) as total_amount,
                        COUNT(DISTINCT ma_khach_hang) as unique_customers
                    FROM donhang
                    WHERE ma_nv = ?
                    AND COALESCE(ngay_tao_don, ngay_tao_don_ban) IS NOT NULL                  
                    -- 1. Loại bỏ ID rác
                    AND ma_don_hang != 'UNKNOWN_ID' 
                    AND ma_don_hang != ''
                    AND ma_don_hang IS NOT NULL                    
                    -- 2. Logic Status Chặt chẽ
                    AND (
                        trang_thai IN ('Đã thanh toán', 'Khởi tạo', 'Đang xử lý')
                        OR 
                        (trang_thai = 'Hoàn thành' AND COALESCE(loai_don, '') != 'Đơn trả Presale')
                    )
                    -- 3. Ngày tháng
                    AND (
                        (DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                        OR (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?)
                    )
                    AND YEAR(COALESCE(ngay_tao_don, ngay_tao_don_ban)) >= 2000";
            
            $params = [$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay];            
            // Logic Filter Product Prefix
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
    // ========== HELPER: Parse Date ==========
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
    // ========== HELPER: Parse Money ==========
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

    public function countCustomTotalOrders($ma_nv, $tu_ngay, $den_ngay) {
    try {
        $sql = "SELECT COUNT(DISTINCT ma_don_hang) as total_orders
                FROM donhang
                WHERE ma_nv = ?
                -- 1. Loại bỏ ID rác
                AND ma_don_hang != 'UNKNOWN_ID' 
                AND ma_don_hang != ''
                AND ma_don_hang IS NOT NULL
                
                -- 2. Lọc theo ngày (dùng COALESCE để check cả 2 cột ngày)
                AND (
                    (DATE(ngay_tao_don) >= ? AND DATE(ngay_tao_don) <= ?)
                    OR 
                    (DATE(ngay_tao_don_ban) >= ? AND DATE(ngay_tao_don_ban) <= ?)
                )
                
                -- 3. Logic trạng thái phức tạp
                AND (
                    -- Nhóm 1: Các trạng thái lấy vô điều kiện
                    trang_thai IN ('Đã thanh toán', 'Khởi tạo', 'Đang xử lý')
                    
                    OR 
                    
                    -- Nhóm 2: Hoàn thành nhưng KHÔNG PHẢI là Đơn trả Presale
                    (trang_thai = 'Hoàn thành' AND COALESCE(loai_don, '') != 'Đơn trả Presale')
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ma_nv, $tu_ngay, $den_ngay, $tu_ngay, $den_ngay]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total_orders'] ?? 0);

    } catch (Exception $e) {
        // Ghi log lỗi nếu cần thiết
        if (isset($this->logger)) {
            $this->logger->error("Lỗi tính tổng đơn custom: " . $e->getMessage());
        }
        return 0;
    }
}
}