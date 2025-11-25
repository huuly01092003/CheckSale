<?php
/**
 * FILE: MODELS/GIAMSATMODEL.PHP
 * Model xử lý dữ liệu giám sát ghé thăm
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
     * Lấy tất cả dữ liệu giám sát
     */
    public function getAll($limit = 1000, $offset = 0) {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY ngay DESC, thoi_gian_bat_dau DESC LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getAll error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy dữ liệu theo khoảng ngày
     */
    public function getByDateRange($tu_ngay, $den_ngay, $limit = 1000) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?
                    ORDER BY ngay DESC, thoi_gian_bat_dau DESC
                    LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getByDateRange error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy dữ liệu theo nhân viên
     */
    public function getByEmployee($ma_nhan_vien, $tu_ngay = null, $den_ngay = null, $limit = 1000) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE ma_nhan_vien = ?";
            $params = [$ma_nhan_vien];

            if ($tu_ngay && $den_ngay) {
                $sql .= " AND DATE(ngay) >= ? AND DATE(ngay) <= ?";
                $params[] = $tu_ngay;
                $params[] = $den_ngay;
            }

            $sql .= " ORDER BY ngay DESC, thoi_gian_bat_dau DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getByEmployee error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy dữ liệu theo khách hàng
     */
    public function getByCustomer($ma_khach_hang, $limit = 1000) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE ma_khach_hang = ?
                    ORDER BY ngay DESC
                    LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ma_khach_hang, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("getByCustomer error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lọc dữ liệu với nhiều điều kiện
     */
    public function search($filters = [], $limit = 1000) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            // Khoảng ngày
            if (!empty($filters['tu_ngay']) && !empty($filters['den_ngay'])) {
                $sql .= " AND DATE(ngay) >= ? AND DATE(ngay) <= ?";
                $params[] = $filters['tu_ngay'];
                $params[] = $filters['den_ngay'];
            }

            // Nhân viên
            if (!empty($filters['ma_nhan_vien'])) {
                $sql .= " AND ma_nhan_vien LIKE ?";
                $params[] = '%' . $filters['ma_nhan_vien'] . '%';
            }

            // Kết quả
            if (!empty($filters['ket_qua'])) {
                $sql .= " AND ket_qua_ghe_tham = ?";
                $params[] = $filters['ket_qua'];
            }

            // Tỉnh
            if (!empty($filters['tinh_thanh'])) {
                $sql .= " AND tinh_thanh LIKE ?";
                $params[] = '%' . $filters['tinh_thanh'] . '%';
            }

            // Khách hàng
            if (!empty($filters['ma_khach_hang'])) {
                $sql .= " AND ma_khach_hang = ?";
                $params[] = $filters['ma_khach_hang'];
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
     * Lấy chi tiết một bản ghi
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
     * Thêm bản ghi mới
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
     * Cập nhật bản ghi
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
     * Xóa bản ghi
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
     * Đếm tổng số bản ghi
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
            
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            $this->logger->error("count error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Tính thống kê tổng hợp
     */
    public function getStatistics($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT DATE(ngay)) as total_days,
                    COUNT(DISTINCT ma_nhan_vien) as total_employees,
                    COUNT(DISTINCT ma_khach_hang) as total_customers,
                    AVG(tong_thoi_gian_ghe_tham) as avg_call_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' OR ket_qua_ghe_tham LIKE '%Có%' THEN 1 ELSE 0 END) as success_count
                FROM {$this->table}
                WHERE DATE(ngay) >= ? AND DATE(ngay) <= ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tu_ngay, $den_ngay]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Tính tỷ lệ thành công
            $success_rate = ($result['total_records'] > 0) 
                ? round(($result['success_count'] / $result['total_records']) * 100, 2) 
                : 0;
            
            $result['success_rate'] = $success_rate;
            $result['avg_call_time'] = round($result['avg_call_time'] ?? 0, 1);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error("getStatistics error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy dữ liệu cho biểu đồ
     */
    public function getChartData($tu_ngay, $den_ngay) {
        try {
            $sql = "SELECT 
                    DATE(ngay) as ngay, 
                    COUNT(*) as so_lang_ghe_tham, 
                    AVG(tong_thoi_gian_ghe_tham) as avg_time,
                    SUM(CASE WHEN ket_qua_ghe_tham LIKE '%Thành công%' OR ket_qua_ghe_tham LIKE '%Có%' THEN 1 ELSE 0 END) as success_count
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
     * Lấy dữ liệu kết quả ghé thăm
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
                $results[$row['ket_qua_ghe_tham']] = $row['count'];
            }
            
            return $results;
        } catch (Exception $e) {
            $this->logger->error("getResultStats error", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Lấy danh sách nhân viên duy nhất
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
     * Lấy danh sách tỉnh duy nhất
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
     * Lấy danh sách kết quả duy nhất
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
     * Xóa dữ liệu cũ (trước N ngày)
     */
    public function deleteOldData($days = 90) {
        try {
            $sql = "DELETE FROM {$this->table} WHERE ngay < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            
            $deleted = $this->pdo->query("SELECT ROW_COUNT() as count")->fetch(PDO::FETCH_ASSOC)['count'];
            $this->logger->info("Old data deleted", ['days' => $days, 'deleted' => $deleted]);
            
            return $deleted;
        } catch (Exception $e) {
            $this->logger->error("deleteOldData error", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Nhập dữ liệu hàng loạt
     */
    public function insertBatch($data_array) {
        try {
            $sql = "INSERT INTO {$this->table} (
                ma_don_vi_phan_phoi, ten_don_vi_phan_phoi, ma_nhan_vien, ten_nhan_vien, chuc_vu,
                ma_tuyen_ban_hang, ten_tuyen_ban_hang, ngay, thu, thu_tu_ghe_tham, lo_trinh,
                ma_khach_hang, ten_khach_hang, dia_chi, lan_ghe_tham, ket_qua_ghe_tham,
                thoi_gian_bat_dau, thoi_gian_ket_thuc, tong_thoi_gian_ghe_tham,
                toa_do_ghe_tham_lat, toa_do_ghe_tham_lng, toa_do_ket_thuc_lat, toa_do_ket_thuc_lng,
                tinh_thanh
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE updated_at = NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $inserted = 0;
            
            foreach ($data_array as $data) {
                if ($stmt->execute($data)) {
                    $inserted++;
                }
            }
            
            return $inserted;
        } catch (Exception $e) {
            $this->logger->error("insertBatch error", ['error' => $e->getMessage()]);
            return 0;
        }
    }
}