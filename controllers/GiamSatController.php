<?php
/**
 * FILE: CONTROLLERS/GIAMSATCONTROLLER.PHP - FIX v12
 * ✅ NEW: Phát hiện gian lận theo giờ, thời gian call, và hành vi
 */

class GiamSatController {
    private $giamsatModel;
    private $logger;

    public function __construct() {
        $this->giamsatModel = new GiamSatModel();
        $this->logger = new Logger();
    }

    /**
     * ========== HIỂN THỊ TRANG GIÁM SÁT CHÍNH ==========
     */
public function showGiamSat() {
        $message = '';
        $type = '';
        $giamsat_summary = [];
        $employee_daily_data = [];
        $statistics = [
            'total_records' => 0,
            'total_days' => 0,
            'total_employees' => 0,
            'total_customers' => 0,
            'min_call_time' => 0,
            'max_call_time' => 0,
            'avg_call_time' => 0,
            'success_rate' => 0,
            'by_result' => []
        ];
        $filters = [
            'tu_ngay' => date('Y-m-d', strtotime('-7 days')),
            'den_ngay' => date('Y-m-d'),
            'ma_nhan_vien' => '',
            'ket_qua' => '',
            'tinh_thanh' => '',
            'gio_bat_dau' => '00:00',
            'gio_ket_thuc' => '23:59',
            'min_phut_nghi_van' => 0,
            'max_phut_nghi_van' => 999,
            'gio_nghi_van' => '14:00'
        ];
        $chart_data = [];
        $employee_list = [];
        $result_list = [];
        $province_list = [];

        try {
            // ========== LẤY KHOẢNG NGÀY ==========
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : date('Y-m-d', strtotime('-7 days'));
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-d');

            if (!$this->isValidDate($tu_ngay) || !$this->isValidDate($den_ngay)) {
                throw new Exception("Ngày không hợp lệ");
            }

            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                $temp = $tu_ngay;
                $tu_ngay = $den_ngay;
                $den_ngay = $temp;
            }

            $ma_nhan_vien = !empty($_GET['ma_nhan_vien']) ? trim($_GET['ma_nhan_vien']) : '';
            $ket_qua = !empty($_GET['ket_qua']) ? trim($_GET['ket_qua']) : '';
            $tinh_thanh = !empty($_GET['tinh_thanh']) ? trim($_GET['tinh_thanh']) : '';
            
            // ✅ NEW FILTERS
            $gio_bat_dau = !empty($_GET['gio_bat_dau']) ? trim($_GET['gio_bat_dau']) : '00:00';
            $gio_ket_thuc = !empty($_GET['gio_ket_thuc']) ? trim($_GET['gio_ket_thuc']) : '23:59';
            $min_phut_nghi_van = !empty($_GET['min_phut_nghi_van']) ? intval($_GET['min_phut_nghi_van']) : 0;
            $max_phut_nghi_van = !empty($_GET['max_phut_nghi_van']) ? intval($_GET['max_phut_nghi_van']) : 999;
            $gio_nghi_van = !empty($_GET['gio_nghi_van']) ? trim($_GET['gio_nghi_van']) : '14:00';

            $filters = [
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'ma_nhan_vien' => $ma_nhan_vien,
                'ket_qua' => $ket_qua,
                'tinh_thanh' => $tinh_thanh,
                'gio_bat_dau' => $gio_bat_dau,
                'gio_ket_thuc' => $gio_ket_thuc,
                'min_phut_nghi_van' => $min_phut_nghi_van,
                'max_phut_nghi_van' => $max_phut_nghi_van,
                'gio_nghi_van' => $gio_nghi_van
            ];

            // ========== LẤY DỮ LIỆU TỔNG HỢP (BẢNG 1) ==========
            $giamsat_summary = $this->giamsatModel->getEmployeeSummary($filters);

            // ========== THỐNG KÊ ==========
            $statistics = $this->giamsatModel->getStatistics($tu_ngay, $den_ngay);
            $statistics['by_result'] = $this->giamsatModel->getResultStats($tu_ngay, $den_ngay, $filters);

            // ========== BIỂU ĐỒ ==========
            $chart_data = $this->giamsatModel->getChartData($tu_ngay, $den_ngay, $filters);

            // ========== DANH SÁCH DROPDOWN ==========
            $employee_list = $this->giamsatModel->getEmployeeListFiltered($tu_ngay, $den_ngay, $filters);
            $result_list = $this->giamsatModel->getResultListFiltered($tu_ngay, $den_ngay);
            $province_list = $this->giamsatModel->getProvinceList();

            // ========== BẢNG TÌM KIẾM THỜI GIAN (BẢNG 2) ==========
            if (!empty($ma_nhan_vien)) {
                $employee_daily_data = $this->giamsatModel->getEmployeeDailyCallTimes($tu_ngay, $den_ngay, $ma_nhan_vien, $gio_nghi_van);
            }

            if (empty($giamsat_summary)) {
                $message = "⚠️ Không có dữ liệu giám sát cho khoảng thời gian này.";
                $type = 'warning';
            }

            $this->logger->info("GiamSat displayed", [
                'records' => count($giamsat_summary),
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            $this->logger->error("GiamSat error", ['error' => $e->getMessage()]);
        }

        include 'views/giamsat.view.php';
    }

    /**
     * ========== TÍNH FRAUD SCORE CHO MỖI CALL ==========
     */
    private function calculateFraudScore($item, $min_call_time, $max_call_time, $time_from, $time_to) {
        $score = 0;
        $reasons = [];
        
        $call_time = intval($item['tong_thoi_gian_ghe_tham'] ?? 0);
        $end_time = $item['thoi_gian_ket_thuc'] ?? '';
        
        // ========== 1. KIỂM TRA THỜI GIAN CALL ==========
        if ($call_time > 0) {
            if ($call_time < $min_call_time || $call_time > $max_call_time) {
                $score += 30;
                $reasons[] = "TG call: {$call_time}min (ngoài [{$min_call_time}-{$max_call_time}])";
            }
        }
        
        // ========== 2. KIỂM TRA GIỜ KẾT THÚC ==========
        if (!empty($end_time)) {
            if (!$this->isTimeInRange($end_time, $time_from, $time_to)) {
                $score += 40;
                $reasons[] = "Kết thúc: {$end_time} (ngoài [{$time_from}-{$time_to}])";
            }
        }
        
        // ========== 3. KIỂM TRA NGÀY WEEKEND ==========
        if (!empty($item['ngay'])) {
            $dayOfWeek = date('N', strtotime($item['ngay'])); // 1=Mon, 7=Sun
            if ($dayOfWeek >= 6) { // Sat=6, Sun=7
                $score += 20;
                $reasons[] = "Ngày: " . $this->getDayName($dayOfWeek);
            }
        }
        
        return [
            'score' => min(100, $score),
            'reason' => implode(" | ", $reasons) ?: "Bình thường",
            'is_suspicious' => $score > 50
        ];
    }

    /**
     * ========== PHÂN TÍCH HÀNH VI THEO GIỜ ==========
     */
    private function analyzeBehaviorByTime($employee_daily_data, $time_from, $time_to, $min_call_time, $max_call_time) {
        $suspicious = [];
        $suspicious_count = 0;
        
        foreach ($employee_daily_data as $call) {
            $call_time = intval($call['tong_thoi_gian_ghe_tham'] ?? 0);
            $end_time = $call['thoi_gian_ket_thuc'] ?? '';
            
            // Kiểm tra call này có nghi vấn không
            $is_suspicious = false;
            $reasons = [];
            
            if ($call_time < $min_call_time || $call_time > $max_call_time) {
                $is_suspicious = true;
                $reasons[] = "TG: {$call_time}min";
            }
            
            if (!empty($end_time) && !$this->isTimeInRange($end_time, $time_from, $time_to)) {
                $is_suspicious = true;
                $reasons[] = "Giờ: {$end_time}";
            }
            
            if ($is_suspicious) {
                $suspicious_count++;
                $suspicious[] = array_merge($call, [
                    'suspicious_reasons' => implode(" | ", $reasons)
                ]);
            }
        }
        
        return [
            'calls' => $suspicious,
            'total_suspicious' => $suspicious_count,
            'total_calls' => count($employee_daily_data),
            'suspicious_rate' => count($employee_daily_data) > 0 
                ? round(($suspicious_count / count($employee_daily_data)) * 100, 1) 
                : 0
        ];
    }

    /**
     * ========== TÓM TẮT HÀNH VI ==========
     */
    private function generateBehaviorSummary($suspicious_calls, $employee_daily_data, $tu_ngay, $den_ngay) {
        $days_count = max(1, intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400) + 1);
        $calls_per_day = count($employee_daily_data) / $days_count;
        $suspicious_per_day = $suspicious_calls['total_suspicious'] / $days_count;
        
        return [
            'total_days' => $days_count,
            'total_calls' => count($employee_daily_data),
            'suspicious_calls' => $suspicious_calls['total_suspicious'],
            'calls_per_day' => round($calls_per_day, 1),
            'suspicious_per_day' => round($suspicious_per_day, 1),
            'suspicious_rate' => $suspicious_calls['suspicious_rate'],
            'assessment' => $this->assessBehavior($suspicious_calls['suspicious_rate'])
        ];
    }

    /**
     * ========== ĐÁNH GIÁ HÀNH VI ==========
     */
    private function assessBehavior($rate) {
        if ($rate >= 70) return ['level' => 'critical', 'icon' => '🚨', 'text' => 'RẤT NGHI VẤN'];
        if ($rate >= 50) return ['level' => 'warning', 'icon' => '⚠️', 'text' => 'NGHI VẤN'];
        if ($rate >= 30) return ['level' => 'caution', 'icon' => '⚡', 'text' => 'CẢN THẬN'];
        return ['level' => 'normal', 'icon' => '✅', 'text' => 'BÌNH THƯỜNG'];
    }

    /**
     * ========== HELPER: Kiểm tra thời gian trong khoảng ==========
     */
    private function isTimeInRange($time, $start, $end) {
        if (empty($time) || empty($start) || empty($end)) return true;
        
        try {
            $timeVal = strtotime("2024-01-01 {$time}");
            $startVal = strtotime("2024-01-01 {$start}");
            $endVal = strtotime("2024-01-01 {$end}");
            
            return $timeVal >= $startVal && $timeVal <= $endVal;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * ========== HELPER: Lấy tên ngày ==========
     */
    private function getDayName($dayOfWeek) {
        $days = [
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            7 => 'Chủ nhật'
        ];
        return $days[$dayOfWeek] ?? '';
    }

    /**
     * ========== XỬ LÝ UPLOAD FILE CSV ==========
     */
      public function handleUpload() {
        $message = '';
        $type = '';

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
            $file = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            $size = $_FILES['file']['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $max_size = 500 * 1024 * 1024;
            if ($size > $max_size) {
                $message = "❌ Tệp quá lớn (> 500MB)";
                $type = 'danger';
            } elseif ($ext !== 'csv') {
                $message = "❌ Chỉ chấp nhận file CSV";
                $type = 'danger';
            } else {
                try {
                    $imported = $this->giamsatModel->importFromCSV($file);
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
     * ========== HELPER: Kiểm tra ngày hợp lệ ==========
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}