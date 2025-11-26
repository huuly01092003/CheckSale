<?php
/**
 * FILE: MODELS/GIAMSATMODEL.PHP (FIX TIME v7 - FULL FIXED)
 * ✅ Fix: Gọi method đúng ($this->insertBatch thay vì $this->GiamSatModel->insertBatch)
 * ✅ Fix: Return statement đúng vị trí
 * ✅ Fix: Indentation và cấu trúc class
 * ✅ Fix: Các method nằm trong class scope
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
            if (!$handle) {
                throw new Exception("Không thể mở file CSV");
            }

            $batch = [];
            $batchSize = 500;
            $total = 0;
            $inserted = 0;

            // Bỏ qua header
            fgetcsv($handle, 0, ',', '"');

            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                if (empty($row) || count($row) < 10) {
                    continue;
                }

                try {
                    // Parse thời gian TRƯỚC khi tính tổng
                    $time_bat_dau = $this->parseTime(isset($row[19]) ? $row[19] : null);
                    $time_ket_thuc = $this->parseTime(isset($row[20]) ? $row[20] : null);
                    
                    // ✅ TÍNH TỔNG THỜI GIAN (PHÚT)
                    $tong_thoi_gian = 0;
                    if ($time_bat_dau && $time_ket_thuc) {
                        $tong_thoi_gian = $this->calculateTimeDiffMinutes($time_bat_dau, $time_ket_thuc);
                    } else {
                        // Fallback: nếu không có time, lấy từ cột V
                        $tong_thoi_gian = $this->parseInt(isset($row[21]) ? $row[21] : 0);
                    }

                    // Parse dữ liệu theo cấu trúc CSV
                    $data = [
                        isset($row[1]) ? trim($row[1]) : null,     // B
                        isset($row[2]) ? trim($row[2]) : null,     // C
                        isset($row[5]) ? trim($row[5]) : null,     // F
                        isset($row[6]) ? trim($row[6]) : null,     // G
                        isset($row[7]) ? trim($row[7]) : null,     // H
                        isset($row[8]) ? trim($row[8]) : null,     // I
                        isset($row[9]) ? trim($row[9]) : null,     // J
                        $this->parseDate(isset($row[10]) ? $row[10] : null), // K
                        isset($row[11]) ? trim($row[11]) : null,   // L
                        $this->parseInt(isset($row[12]) ? $row[12] : 0), // M
                        isset($row[13]) ? trim($row[13]) : null,   // N
                        isset($row[14]) ? trim($row[14]) : null,   // O
                        isset($row[15]) ? trim($row[15]) : null,   // P
                        isset($row[16]) ? trim($row[16]) : null,   // Q
                        $this->parseInt(isset($row[17]) ? $row[17] : 0), // R
                        isset($row[18]) ? trim($row[18]) : null,   // S
                        $time_bat_dau,                              // T
                        $time_ket_thuc,                             // U
                        $tong_thoi_gian,                            // V
                        $this->parseCoord(isset($row[28]) ? $row[28] : 0), // AC
                        $this->parseCoord(isset($row[29]) ? $row[29] : 0), // AD
                        $this->parseCoord(isset($row[30]) ? $row[30] : 0), // AE
                        $this->parseCoord(isset($row[31]) ? $row[31] : 0), // AF
                        isset($row[34]) ? trim($row[34]) : null,   // AI
                    ];

                    $batch[] = $data;
                    $total++;

                    if (count($batch) >= $batchSize) {
                        $inserted += $this->insertBatch($batch);
                        $batch = [];
                    }
                } catch (Exception $e) {
                    $this->logger->debug("Row parse error: " . $e->getMessage());
                    continue;
                }
            }

            // ✅ INSERT BATCH CUỐI CÙNG (FIX: gọi đúng method)
            if (!empty($batch)) {
                $inserted += $this->insertBatch($batch);
            }

            $this->logger->success("Import hoàn tất", [
                'total_rows' => $total,
                'inserted' => $inserted
            ]);

            return $inserted;

        } catch (Exception $e) {
            $this->logger->error("Import error: " . $e->getMessage());
            throw $e;
        } finally {
            // ✅ QUAN TRỌNG: Đóng file handle
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * ========== INSERT BATCH ==========
     */
    public function insertBatch($data_array) {
        if (empty($data_array)) {
            return 0;
        }

        $stmt = null;
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
                    if ($stmt->execute($data)) {
                        $inserted++;
                    }
                } catch (PDOException $e) {
                    $this->logger->warning("Insert row failed: " . $e->getMessage());
                    continue;
                }
            }
            
            $this->logger->info("Batch insert completed", [
                'total' => count($data_array),
                'inserted' => $inserted
            ]);
            
            return $inserted;
            
        } catch (Exception $e) {
            $this->logger->error("insertBatch error: " . $e->getMessage());
            throw $e;
        } finally {
            // ✅ Cleanup statement
            $stmt = null;
        }
    }

    /**
     * ========== LẤY TẤT CẢ DỮ LIỆU ==========
     */
    public function getAll($limit = 1000, $offset = 0) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    ORDER BY ngay DESC, thoi_gian_bat_dau DESC 
                    LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getAll error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== LỌC DỮ LIỆU VỚI ĐIỀU KIỆN ==========
     */
    public function search($filters = [], $limit = 1000) {
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

            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham LIKE ?";
                $params[] = '%' . $filters['ket_qua'] . '%';
            }

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " ORDER BY ngay DESC, thoi_gian_bat_dau DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("search error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== TÍNH THỐNG KÊ TỔNG HỢP ==========
     */
    public function getStatistics($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT DATE(ngay)) as total_days,
                    COUNT(DISTINCT ma_nhan_vien) as total_employees,
                    COUNT(DISTINCT ma_khach_hang) as total_customers,
                    ROUND(AVG(tong_thoi_gian_ghe_tham), 1) as avg_call_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' 
                             OR ket_qua_ghe_tham LIKE '%Có%' 
                             OR ket_qua_ghe_tham LIKE '%Đúng%' THEN 1 ELSE 0 END) as success_count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Tính tỷ lệ thành công
            $total_records = intval($result['total_records'] ?? 0);
            $success_count = intval($result['success_count'] ?? 0);
            $success_rate = ($total_records > 0) ? round(($success_count / $total_records) * 100, 2) : 0;
            
            $result['success_rate'] = $success_rate;
            $result['total_records'] = $total_records;
            $result['total_days'] = intval($result['total_days'] ?? 0);
            $result['total_employees'] = intval($result['total_employees'] ?? 0);
            $result['total_customers'] = intval($result['total_customers'] ?? 0);
            $result['avg_call_time'] = floatval($result['avg_call_time'] ?? 0);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("getStatistics error", ['error' => $e->getMessage()]);
            return [
                'total_records' => 0,
                'total_days' => 0,
                'total_employees' => 0,
                'total_customers' => 0,
                'avg_call_time' => 0,
                'success_rate' => 0
            ];
        }
    }

    /**
     * ========== THỐNG KÊ THEO KẾT QUẢ ==========
     */
    public function getResultStats($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    ket_qua_ghe_tham,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                GROUP BY ket_qua_ghe_tham
                ORDER BY count DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['ket_qua_ghe_tham'] ?: 'Không xác định';
                $results[$key] = intval($row['count']);
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->error("getResultStats error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DỮ LIỆU CHO BIỂU ĐỒ ==========
     */
    public function getChartData($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    DATE(ngay) as ngay,
                    COUNT(*) as so_lang_ghe_tham,
                    ROUND(AVG(tong_thoi_gian_ghe_tham), 1) as avg_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' 
                             OR ket_qua_ghe_tham LIKE '%Có%' 
                             OR ket_qua_ghe_tham LIKE '%Đúng%' THEN 1 ELSE 0 END) as success_count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                GROUP BY DATE(ngay)
                ORDER BY ngay ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getChartData error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== LẤY CHI TIẾT MỘT BẢN GHI ==========
     */
    public function getById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getById error", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * ========== THÊM BẢN GHI MỚI ==========
     */
    public function create($data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->logger->error("create error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * ========== CẬP NHẬT BẢN GHI ==========
     */
    public function update($id, $data) {
        try {
            $set = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                $set[] = "$key = ?";
                $params[] = $value;
            }
            
            $params[] = $id;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            $this->logger->error("update error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * ========== XÓA BẢN GHI ==========
     */
    public function delete($id) {
        try {
            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            $this->logger->error("delete error", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * ========== ĐẾM TỔNG SỐ BẢN GHI ==========
     */
    public function count($filters = []) {
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

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return intval($result['total'] ?? 0);
        } catch (Exception $e) {
            $this->logger->error("count error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * ========== DANH SÁCH NHÂN VIÊN DUY NHẤT ==========
     */
    public function getEmployeeList() {
        try {
            $sql = "SELECT DISTINCT 
                    ma_nhan_vien,
                    ten_nhan_vien,
                    COUNT(*) as tong_lan_ghe_tham
                FROM {$this->table}
                WHERE ma_nhan_vien IS NOT NULL AND ma_nhan_vien != ''
                GROUP BY ma_nhan_vien, ten_nhan_vien
                ORDER BY ma_nhan_vien ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getEmployeeList error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== DANH SÁCH TỈNH DUY NHẤT ==========
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
     * ========== DANH SÁCH KẾT QUẢ DUY NHẤT ==========
     */
    public function getResultList() {
        try {
            $sql = "SELECT DISTINCT ket_qua_ghe_tham
                FROM {$this->table}
                WHERE ket_qua_ghe_tham IS NOT NULL AND ket_qua_ghe_tham != ''
                ORDER BY ket_qua_ghe_tham ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $this->logger->error("getResultList error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ========== SEARCH PAGINATION ==========
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

            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham LIKE ?";
                $params[] = '%' . $filters['ket_qua'] . '%';
            }

            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            $sql .= " ORDER BY ngay DESC, thoi_gian_bat_dau DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ CAST tong_thoi_gian_ghe_tham về INT
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
     * ========== COUNT FILTERED RECORDS ==========
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
                $sql .= " AND ket_qua_ghe_tham LIKE ?";
                $params[] = '%' . $filters['ket_qua'] . '%';
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
            $this->logger->debug("Time calc error: " . $e->getMessage());
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