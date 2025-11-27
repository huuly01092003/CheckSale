<?php
/**
 * FILE: MODELS/GIAMSATMODEL.PHP (FIX v10)
 * ✅ FIX: Lọc theo kết quả, thống kê chính xác, hỗ trợ tìm kiếm thời gian theo ngày
 */

class GiamSatModel {
    private $pdo;
    private $logger;
    private $table = 'giamsat';

    public function __construct() {
        $this->pdo = Config::getPDO();
        $this->logger = new Logger();
    }

    /**
     * ========== IMPORT TỪ CSV ==========
     */
    public function importFromCSV($file) {
        $handle = null;
        try {
            ini_set('memory_limit', '-1');
            set_time_limit(0);

            $handle = fopen($file, 'r');
            if (!$handle) throw new Exception("Không thể mở file CSV");

            $batch = [];
            $batchSize = 500;
            $total = 0;
            $inserted = 0;

            fgetcsv($handle, 0, ',', '"');

            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                if (empty($row) || count($row) < 10) continue;

                try {
                    $time_bat_dau = $this->parseTime(isset($row[19]) ? $row[19] : null);
                    $time_ket_thuc = $this->parseTime(isset($row[20]) ? $row[20] : null);
                    
                    $tong_thoi_gian = 0;
                    if ($time_bat_dau && $time_ket_thuc) {
                        $tong_thoi_gian = $this->calculateTimeDiffMinutes($time_bat_dau, $time_ket_thuc);
                    } else {
                        $tong_thoi_gian = $this->parseInt(isset($row[21]) ? $row[21] : 0);
                    }

                    $data = [
                        isset($row[1]) ? trim($row[1]) : null,
                        isset($row[2]) ? trim($row[2]) : null,
                        isset($row[5]) ? trim($row[5]) : null,
                        isset($row[6]) ? trim($row[6]) : null,
                        isset($row[7]) ? trim($row[7]) : null,
                        isset($row[8]) ? trim($row[8]) : null,
                        isset($row[9]) ? trim($row[9]) : null,
                        $this->parseDate(isset($row[10]) ? $row[10] : null),
                        isset($row[11]) ? trim($row[11]) : null,
                        $this->parseInt(isset($row[12]) ? $row[12] : 0),
                        isset($row[13]) ? trim($row[13]) : null,
                        isset($row[14]) ? trim($row[14]) : null,
                        isset($row[15]) ? trim($row[15]) : null,
                        isset($row[16]) ? trim($row[16]) : null,
                        $this->parseInt(isset($row[17]) ? $row[17] : 0),
                        isset($row[18]) ? trim($row[18]) : null,
                        $time_bat_dau,
                        $time_ket_thuc,
                        $tong_thoi_gian,
                        $this->parseCoord(isset($row[28]) ? $row[28] : 0),
                        $this->parseCoord(isset($row[29]) ? $row[29] : 0),
                        $this->parseCoord(isset($row[30]) ? $row[30] : 0),
                        $this->parseCoord(isset($row[31]) ? $row[31] : 0),
                        isset($row[34]) ? trim($row[34]) : null,
                    ];

                    $batch[] = $data;
                    $total++;

                    if (count($batch) >= $batchSize) {
                        $inserted += $this->insertBatch($batch);
                        $batch = [];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!empty($batch)) {
                $inserted += $this->insertBatch($batch);
            }

            $this->logger->success("Import hoàn tất", ['total_rows' => $total, 'inserted' => $inserted]);
            return $inserted;

        } catch (Exception $e) {
            $this->logger->error("Import error: " . $e->getMessage());
            throw $e;
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
    }

    /**
     * ========== INSERT BATCH ==========
     */
    public function insertBatch($data_array) {
        if (empty($data_array)) return 0;

        try {
            $sql = "INSERT INTO {$this->table} (
                ma_don_vi_phan_phoi, ten_don_vi_phan_phoi, ma_nhan_vien, ten_nhan_vien, chuc_vu,
                ma_tuyen_ban_hang, ten_tuyen_ban_hang, ngay, thu, thu_tu_ghe_tham, lo_trinh,
                ma_khach_hang, ten_khach_hang, dia_chi, lan_ghe_tham, ket_qua_ghe_tham,
                thoi_gian_bat_dau, thoi_gian_ket_thuc, tong_thoi_gian_ghe_tham,
                toa_do_ghe_tham_lat, toa_do_ghe_tham_lng, toa_do_ket_thuc_lat, toa_do_ket_thuc_lng,
                tinh_thanh
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                thoi_gian_bat_dau = VALUES(thoi_gian_bat_dau),
                thoi_gian_ket_thuc = VALUES(thoi_gian_ket_thuc),
                tong_thoi_gian_ghe_tham = VALUES(tong_thoi_gian_ghe_tham),
                updated_at = NOW()";

            $stmt = $this->pdo->prepare($sql);
            $inserted = 0;
            
            foreach ($data_array as $data) {
                try {
                    if ($stmt->execute($data)) $inserted++;
                } catch (PDOException $e) {
                    continue;
                }
            }
            
            return $inserted;
        } catch (Exception $e) {
            $this->logger->error("insertBatch error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ========== SEARCH PAGINATED (FIX LỌC) ==========
     */
    public function searchPaginated($filters = [], $offset = 0, $limit = 500) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['tu_ngay']) && !empty($filters['den_ngay'])) {
                $sql .= " AND DATE(ngay) >= ? AND DATE(ngay) <= ?";
                $params[] = $filters['tu_ngay'];
                $params[] = $filters['den_ngay'];
            }

            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }

            // ✅ FIX LỖI: Lọc theo kết quả chính xác
            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham = ?";
                $params[] = $filters['ket_qua'];
            }

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " ORDER BY COALESCE(ngay, '0000-00-00') DESC, 
                      COALESCE(thoi_gian_bat_dau, '00:00:00') DESC 
                      LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($result as &$row) {
                $row['tong_thoi_gian_ghe_tham'] = intval($row['tong_thoi_gian_ghe_tham'] ?? 0);
            }
            unset($row);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("searchPaginated error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== COUNT FILTERED ==========
     */
    public function countFiltered($filters = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['tu_ngay']) && !empty($filters['den_ngay'])) {
                $sql .= " AND DATE(ngay) >= ? AND DATE(ngay) <= ?";
                $params[] = $filters['tu_ngay'];
                $params[] = $filters['den_ngay'];
            }

            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }

            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham = ?";
                $params[] = $filters['ket_qua'];
            }

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("countFiltered error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * ========== TÍNH THỐNG KÊ (FIX CHÍNH XÁC) ==========
     * ✅ Tính đúng: Số phút nghi vấn, TG TB, Tỷ lệ thành công
     */
    public function getStatistics($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT DATE(ngay)) as total_days,
                    COUNT(DISTINCT ma_nhan_vien) as total_employees,
                    COUNT(DISTINCT ma_khach_hang) as total_customers,
                    MIN(COALESCE(tong_thoi_gian_ghe_tham, 0)) as min_call_time,
                    MAX(COALESCE(tong_thoi_gian_ghe_tham, 0)) as max_call_time,
                    ROUND(AVG(COALESCE(tong_thoi_gian_ghe_tham, 0)), 1) as avg_call_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' 
                             OR ket_qua_ghe_tham LIKE '%Có%' 
                             OR ket_qua_ghe_tham LIKE '%Đúng%' THEN 1 ELSE 0 END) as success_count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_records = intval($result['total_records'] ?? 0);
            $success_count = intval($result['success_count'] ?? 0);
            $success_rate = ($total_records > 0) ? round(($success_count / $total_records) * 100, 2) : 0;
            
            return [
                'total_records' => $total_records,
                'total_days' => intval($result['total_days'] ?? 0),
                'total_employees' => intval($result['total_employees'] ?? 0),
                'total_customers' => intval($result['total_customers'] ?? 0),
                'min_call_time' => intval($result['min_call_time'] ?? 0),
                'max_call_time' => intval($result['max_call_time'] ?? 0),
                'avg_call_time' => floatval($result['avg_call_time'] ?? 0),
                'success_count' => $success_count,
                'success_rate' => $success_rate
            ];
        } catch (Exception $e) {
            $this->logger->error("getStatistics error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== THỐNG KÊ THEO KẾT QUẢ (FIX: SCALE THEO FILTER) ==========
     */
    public function getResultStats($tu_ngay, $den_ngay, $filters = []) {
        try {
            $sql = "SELECT 
                    COALESCE(ket_qua_ghe_tham, 'Không xác định') as ket_qua,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $params = [$tu_ngay, $den_ngay];

            // Áp dụng cùng filter
            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }
            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " GROUP BY COALESCE(ket_qua_ghe_tham, 'Không xác định')
                      ORDER BY count DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['ket_qua']] = intval($row['count']);
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->error("getResultStats error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DỮ LIỆU CHO BIỂU ĐỒ (FIX: SCALE THEO FILTER) ==========
     */
    public function getChartData($tu_ngay, $den_ngay, $filters = []) {
        try {
            $sql = "SELECT 
                    DATE(ngay) as ngay,
                    COUNT(*) as so_lang_ghe_tham,
                    ROUND(AVG(COALESCE(tong_thoi_gian_ghe_tham, 0)), 1) as avg_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' 
                             OR ket_qua_ghe_tham LIKE '%Có%' 
                             OR ket_qua_ghe_tham LIKE '%Đúng%' THEN 1 ELSE 0 END) as success_count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $params = [$tu_ngay, $den_ngay];

            // Áp dụng cùng filter
            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }
            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }
            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham = ?";
                $params[] = $filters['ket_qua'];
            }

            $sql .= " GROUP BY DATE(ngay) ORDER BY ngay ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$row) {
                $row['so_lang_ghe_tham'] = intval($row['so_lang_ghe_tham']);
                $row['avg_time'] = floatval($row['avg_time']);
                $row['success_count'] = intval($row['success_count'] ?? 0);
            }
            unset($row);
            
            return $results;
        } catch (Exception $e) {
            $this->logger->error("getChartData error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== TÌM KIẾM THỜI GIAN THEO NGÀY (BẢNG ẢNH 2) ==========
     * Lấy tất cả cuộc gọi theo nhân viên và ngày trong khoảng thời gian
     */
    public function getEmployeeDailyCallTimes($tu_ngay, $den_ngay, $ma_nhan_vien = null) {
        try {
            $sql = "SELECT 
                    gs as 'GS',
                    tinh_thanh as 'TINH',
                    ma_nhan_vien as 'MA_NV',
                    ten_nhan_vien as 'TEN_NV',
                    DATE(ngay) as order_date,
                    thoi_gian_ket_thuc,
                    ket_qua_ghe_tham as result
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $params = [$tu_ngay, $den_ngay];

            if (!empty($ma_nhan_vien)) {
                $sql .= " AND ma_nhan_vien = ?";
                $params[] = $ma_nhan_vien;
            }

            $sql .= " ORDER BY ma_nhan_vien ASC, ngay ASC, thoi_gian_ket_thuc ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeDailyCallTimes error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DANH SÁCH NHÂN VIÊN DÙNG FILTER ==========
     */
    public function getEmployeeListFiltered($tu_ngay, $den_ngay, $filters = []) {
        try {
            $sql = "SELECT DISTINCT 
                    ma_nhan_vien,
                    ten_nhan_vien,
                    gs,
                    tinh_thanh,
                    COUNT(*) as tong_lan_ghe_tham
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                AND ma_nhan_vien IS NOT NULL AND ma_nhan_vien != ''";
            
            $params = [$tu_ngay, $den_ngay];

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " GROUP BY ma_nhan_vien, ten_nhan_vien
                      ORDER BY ma_nhan_vien ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeListFiltered error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DANH SÁCH KHÁCH HÀNG DÙNG FILTER ==========
     */
    public function getCustomerListFiltered($tu_ngay, $den_ngay, $filters = []) {
        try {
            $sql = "SELECT DISTINCT 
                    ma_khach_hang,
                    ten_khach_hang,
                    COUNT(*) as so_lan_ghe_tham
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                AND ma_khach_hang IS NOT NULL AND ma_khach_hang != ''";
            
            $params = [$tu_ngay, $den_ngay];

            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " GROUP BY ma_khach_hang, ten_khach_hang
                      ORDER BY ma_khach_hang ASC
                      LIMIT 10";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getCustomerListFiltered error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DANH SÁCH KẾT QUẢ DÙNG FILTER ==========
     */
    public function getResultListFiltered($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT DISTINCT ket_qua_ghe_tham
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                AND ket_qua_ghe_tham IS NOT NULL AND ket_qua_ghe_tham != ''
                ORDER BY ket_qua_ghe_tham ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->logger->error("getResultListFiltered error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== LẤY DANH SÁCH TỈNH ==========
     */
    public function getProvinceList() {
        try {
            $sql = "SELECT DISTINCT tinh_thanh
                FROM {$this->table}
                WHERE tinh_thanh IS NOT NULL AND tinh_thanh != ''
                ORDER BY tinh_thanh ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->logger->error("getProvinceList error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== HELPERS: PARSE DỮ LIỆU ==========
     */
    
    private function parseDate($value) {
        if (empty($value)) return null;
        try {
            $str = trim((string)$value);
            $str = preg_replace('/\s.*/', '', $str);
            
            $date = DateTime::createFromFormat('d/m/Y', $str);
            if ($date) return $date->format('Y-m-d');
            
            $date = DateTime::createFromFormat('Y-m-d', $str);
            if ($date) return $date->format('Y-m-d');
            
            if (is_numeric($str)) {
                return date('Y-m-d', ($str - 25569) * 86400);
            }
        } catch (Exception $e) {}
        return null;
    }

    private function parseTime($value) {
        if (empty($value)) return null;
        try {
            $str = trim((string)$value);
            $str = preg_replace('/\s.*/', '', $str);
            
            if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $str)) {
                $parts = explode(':', $str);
                $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $s = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                return "$h:$m:$s";
            }
            
            if (preg_match('/^\d{1,2}:\d{2}$/', $str)) {
                $parts = explode(':', $str);
                $h = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $m = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                return "$h:$m:00";
            }
            
            $date = DateTime::createFromFormat('H:i:s', $str);
            if ($date) return $date->format('H:i:s');
            
            $date = DateTime::createFromFormat('H:i', $str);
            if ($date) return $date->format('H:i:s');
            
        } catch (Exception $e) {}
        return null;
    }

    private function calculateTimeDiffMinutes($time_bat_dau, $time_ket_thuc) {
        if (!$time_bat_dau || !$time_ket_thuc) return 0;
        
        try {
            $start = strtotime("2024-01-01 $time_bat_dau");
            $end = strtotime("2024-01-01 $time_ket_thuc");
            
            if ($end < $start) {
                $end = strtotime("2024-01-02 $time_ket_thuc");
            }
            
            $diff_seconds = $end - $start;
            $diff_minutes = round($diff_seconds / 60);
            
            return max(0, intval($diff_minutes));
        } catch (Exception $e) {
            return 0;
        }
    }

    private function parseInt($value) {
        if (empty($value)) return 0;
        return (int)preg_replace('/\D/', '', (string)$value);
    }

    private function parseCoord($value) {
        if (empty($value)) return null;
        $val = floatval(preg_replace('/[^\d.-]/', '', (string)$value));
        return ($val !== 0.0) ? $val : null;
    }
}