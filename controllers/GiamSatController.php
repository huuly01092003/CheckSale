<?php
/**
 * FILE: CONTROLLERS/GIAMSATCONTROLLER.PHP
 * Quản lý dữ liệu giám sát ghé thăm (MVC Pattern)
 */

class GiamSatController {
    private $giamsatModel;
    private $logger;

    public function __construct() {
        $this->giamsatModel = new GiamSatModel();
        $this->logger = new Logger();
    }

    /**
     * Hiển thị trang giám sát chính
     */
    public function showGiamSat() {
        $message = '';
        $type = '';
        $giamsat_data = [];
        $statistics = [];
        $filters = [];
        $chart_data = [];

        try {
            // ========== LẤY KHOẢNG NGÀY ==========
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : date('Y-m-d', strtotime('-7 days'));
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-d');

            // Validate date range
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                $temp = $tu_ngay;
                $tu_ngay = $den_ngay;
                $den_ngay = $temp;
            }

            $ma_nhan_vien = !empty($_GET['ma_nhan_vien']) ? trim($_GET['ma_nhan_vien']) : '';
            $ket_qua = !empty($_GET['ket_qua']) ? trim($_GET['ket_qua']) : '';
            $tinh_thanh = !empty($_GET['tinh_thanh']) ? trim($_GET['tinh_thanh']) : '';

            $filters = [
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'ma_nhan_vien' => $ma_nhan_vien,
                'ket_qua' => $ket_qua,
                'tinh_thanh' => $tinh_thanh
            ];

            // ========== LẤY DỮ LIỆU TỬ MODEL ==========
            $giamsat_data = $this->giamsatModel->search($filters, 1000);

            // ========== TÍNH TOÁN THỐNG KÊ ==========
            $statistics = $this->giamsatModel->getStatistics($tu_ngay, $den_ngay);
            $statistics['by_result'] = $this->giamsatModel->getResultStats($tu_ngay, $den_ngay);
            
            // Thêm thông tin chi tiết
            $statistics['total_records'] = count($giamsat_data);

            // ========== LẤY DỮ LIỆU CHO BIỂU ĐỒ ==========
            $chart_data = $this->giamsatModel->getChartData($tu_ngay, $den_ngay);

            if (empty($giamsat_data)) {
                $message = "⚠️ Không có dữ liệu giám sát cho khoảng thời gian này.";
                $type = 'warning';
            }

        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            $this->logger->error("GiamSat error", ['error' => $e->getMessage()]);
        }

        include 'views/giamsat.view.php';
    }

    /**
     * Xử lý upload file CSV
     */
    public function handleUpload() {
        $message = '';
        $type = '';

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
            $file = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            $size = $_FILES['file']['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            // Validate
            $max_size = 500 * 1024 * 1024;
            if ($size > $max_size) {
                $message = "❌ Tệp quá lớn (> 500MB)";
                $type = 'danger';
            } elseif ($ext !== 'csv') {
                $message = "❌ Chỉ chấp nhận file CSV";
                $type = 'danger';
            } else {
                try {
                    $imported = $this->importFromCSV($file);
                    $message = "✅ Import thành công: $imported dòng dữ liệu đã thêm vào hệ thống";
                    $type = 'success';
                    $this->logger->success("GiamSat import success", ['rows' => $imported]);
                } catch (Exception $e) {
                    $message = "❌ Lỗi: " . $e->getMessage();
                    $type = 'danger';
                    $this->logger->error("GiamSat upload error", ['error' => $e->getMessage()]);
                }
            }
        }

        include 'views/giamsat_upload.view.php';
    }

    /**
     * Import dữ liệu từ CSV
     */
    private function importFromCSV($file) {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $handle = fopen($file, 'r');
        if (!$handle) throw new Exception("Không mở được file CSV");

        $batch = [];
        $total = 0;
        $batchSize = 500;
        $inserted = 0;

        // Bỏ qua header
        $header = fgetcsv($handle, 0, ',', '"');

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (empty($row) || count($row) < 10) continue;

            try {
                // Parse dữ liệu - Index tính từ 0
                $data = [
                    isset($row[1]) ? trim($row[1]) : null, // B - ma_don_vi_phan_phoi
                    isset($row[2]) ? trim($row[2]) : null, // C - ten_don_vi_phan_phoi
                    isset($row[5]) ? trim($row[5]) : null, // F - ma_nhan_vien
                    isset($row[6]) ? trim($row[6]) : null, // G - ten_nhan_vien
                    isset($row[7]) ? trim($row[7]) : null, // H - chuc_vu
                    isset($row[8]) ? trim($row[8]) : null, // I - ma_tuyen_ban_hang
                    isset($row[9]) ? trim($row[9]) : null, // J - ten_tuyen_ban_hang
                    $this->parseDate(isset($row[10]) ? $row[10] : null), // K - ngay
                    isset($row[11]) ? trim($row[11]) : null, // L - thu
                    $this->parseInt(isset($row[12]) ? $row[12] : 0), // M - thu_tu_ghe_tham
                    isset($row[13]) ? trim($row[13]) : null, // N - lo_trinh
                    isset($row[14]) ? trim($row[14]) : null, // O - ma_khach_hang
                    isset($row[15]) ? trim($row[15]) : null, // P - ten_khach_hang
                    isset($row[16]) ? trim($row[16]) : null, // Q - dia_chi
                    $this->parseInt(isset($row[17]) ? $row[17] : 0), // R - lan_ghe_tham
                    isset($row[18]) ? trim($row[18]) : null, // S - ket_qua_ghe_tham
                    $this->parseTime(isset($row[19]) ? $row[19] : null), // T - thoi_gian_bat_dau
                    $this->parseTime(isset($row[20]) ? $row[20] : null), // U - thoi_gian_ket_thuc
                    $this->parseInt(isset($row[21]) ? $row[21] : 0), // V - tong_thoi_gian_ghe_tham
                    $this->parseCoord(isset($row[28]) ? $row[28] : 0), // AC - toa_do_ghe_tham_lat
                    $this->parseCoord(isset($row[29]) ? $row[29] : 0), // AD - toa_do_ghe_tham_lng
                    $this->parseCoord(isset($row[30]) ? $row[30] : 0), // AE - toa_do_ket_thuc_lat
                    $this->parseCoord(isset($row[31]) ? $row[31] : 0), // AF - toa_do_ket_thuc_lng
                    isset($row[34]) ? trim($row[34]) : null, // AI - tinh_thanh
                ];

                $batch[] = $data;
                $total++;

                if (count($batch) >= $batchSize) {
                    $inserted += $this->giamsatModel->insertBatch($batch);
                    $batch = [];
                }
            } catch (Exception $e) {
                $this->logger->debug("Row parse error: " . $e->getMessage());
                continue;
            }
        }

        // Insert batch cuối cùng
        if (!empty($batch)) {
            $inserted += $this->giamsatModel->insertBatch($batch);
        }

        fclose($handle);
        return $inserted;
    }

    /**
     * Helper functions
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
            $date = DateTime::createFromFormat('H:i', $str);
            if ($date) return $date->format('H:i:s');
            $date = DateTime::createFromFormat('H:i:s', $str);
            if ($date) return $date->format('H:i:s');
        } catch (Exception $e) {}
        return null;
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