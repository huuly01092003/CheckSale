<?php
/**
 * FILE 6: CONTROLLERS/REPORTCONTROLLER.PHP (FINAL)
 * 
 * ============================================================================
 * ✅ LOGIC NGHI VẤN GIAN LẬN CHÍNH XÁC
 * ============================================================================
 * 
 * 1️⃣ Tính Kết quả chung = Tổng tiền khoảng / Tổng tiền kỳ
 * 
 * 2️⃣ Tính Tỉ lệ hoàn thành nghi vấn = Kết quả chung × 1.5
 * 
 * 3️⃣ So sánh từng nhân viên:
 *    - Nếu % tiến độ >= Tỉ lệ nghi vấn → NGHI VẤN gian lận
 *    - Nếu % tiến độ < Tỉ lệ nghi vấn → OK, không nghi vấn
 * 
 * 4️⃣ Highlight ĐỘNG trong nhóm nghi vấn:
 *    - ≥20 người → Tô đỏ top 20, còn lại cam
 *    - 15-19 người → Tô đỏ top 15, còn lại cam
 *    - 10-14 người → Tô đỏ top 10, còn lại cam
 *    - 5-9 người → Tô đỏ top 5, còn lại cam
 *    - <5 người → Tô đỏ tất cả
 * 
 * 5️⃣ Nhóm OK: Không tô màu
 * 
 * ============================================================================
 */

class ReportController {
    
    public function showReport() {
        $message = '';
        $type = '';
        $report = [];
        $so_ngay = 0;
        $ket_qua_chung = 0;
        $ty_le_nghi_van = 0;
        $tu_ngay = date('Y-m-d');
        $den_ngay = date('Y-m-d');
        $tong_tien_ky = 0;
        $tong_tien_khoang = 0;
        $debug_info = '';
        $available_months = [];
        $top_threshold = 0;
        $tong_nghi_van = 0;
        $logger = new Logger();
        
        try {
            $orderModel = new OrderModel();
            $available_months = $orderModel->getAvailableMonths();
            
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng upload file đơn hàng trước.";
                $type = 'warning';
                include 'views/report.view.php';
                return;
            }
            
            // ========== LẤY THÁNG ==========
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) {
                $thang = $available_months[0];
            }
            
            // ========== LẤY KHOẢNG NGÀY ==========
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : $thang . '-01';
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-t', strtotime($thang . '-01'));
            
            // Validate & swap nếu cần
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                $temp = $tu_ngay;
                $tu_ngay = $den_ngay;
                $den_ngay = $temp;
            }
            
            // Đảm bảo trong tháng
            $thang_start = $thang . '-01';
            $thang_end = date('Y-m-t', strtotime($thang . '-01'));
            
            if (strtotime($tu_ngay) < strtotime($thang_start)) $tu_ngay = $thang_start;
            if (strtotime($den_ngay) > strtotime($thang_end)) $den_ngay = $thang_end;

            // ========== THỐNG KÊ DATABASE ==========
            $pdo = Config::getPDO();
            
            $count_donhang_stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM donhang WHERE DATE_FORMAT(ngay_tao_don, '%Y-%m') = ?"
            );
            $count_donhang_stmt->execute([$thang]);
            $count_donhang = $count_donhang_stmt->fetchColumn();
            
            $count_nhanvien = $pdo->query("SELECT COUNT(*) FROM nhanvien")->fetchColumn();

            // ============================================================================
            // ✅ CÔNG THỨC CHÍNH
            // ============================================================================
            
            // 1️⃣ Tổng tiền kỳ = SUM toàn tháng
            $tong_tien_ky = $orderModel->getTotalByMonth($thang);
            
            // 2️⃣ Tổng tiền khoảng = SUM khoảng ngày chọn
            $tong_tien_khoang = $orderModel->getTotalByDateRange($tu_ngay, $den_ngay);
            
            // 3️⃣ Kết quả chung = Tổng tiền khoảng / Tổng tiền kỳ
            $ket_qua_chung = ($tong_tien_ky > 0) ? ($tong_tien_khoang / $tong_tien_ky) : 0;
            
            // 4️⃣ TỈ LỄ HOÀN THÀNH NGHI VẤN = Kết quả chung × 1.5
            $ty_le_nghi_van = $ket_qua_chung * 1.5;
            
            // Số ngày
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);

            // ========== LẤY DANH SÁCH NHÂN VIÊN ==========
            $employeeModel = new EmployeeModel();
            $employees = $employeeModel->getAll();

            if (!$employees) {
                $message = "⚠️ Không có dữ liệu nhân viên. Vui lòng import danh sách nhân viên trước.";
                $type = 'warning';
                include 'views/report.view.php';
                return;
            }

            // ========== TÍNH TOÁN VÀ PHÂN LOẠI NGHI VẤN ==========
            $report_nghi_van = [];      // Những người nghi vấn
            $report_ok = [];            // Những người OK
            
            foreach ($employees as $emp) {
                $ds_tim_kiem = $orderModel->getEmployeeTotalByMonth($emp['ma_nv'], $thang);
                $ds_tien_do = $orderModel->getEmployeeTotalByDateRange(
                    $emp['ma_nv'], 
                    $tu_ngay, 
                    $den_ngay
                );

                if ($ds_tien_do > 0 || $ds_tim_kiem > 0) {
                    $ty_le = ($ds_tim_kiem > 0) ? ($ds_tien_do / $ds_tim_kiem) : 0;
                    
                    $row = array_merge($emp, [
                        'ds_tim_kiem' => $ds_tim_kiem, 
                        'ds_tien_do' => $ds_tien_do, 
                        'ty_le' => $ty_le
                    ]);
                    
                    // ← SO SÁNH: % tiến độ vs Tỉ lệ nghi vấn
                    if ($ty_le >= $ty_le_nghi_van) {
                        // NGHI VẤN gian lận
                        $row['is_suspect'] = true;
                        $report_nghi_van[] = $row;
                    } else {
                        // OK - không nghi vấn
                        $row['is_suspect'] = false;
                        $report_ok[] = $row;
                    }
                }
            }

            // ========== SẮP XẾP NHÓM NGHI VẤN THEO % GIẢM DẦN ==========
            usort($report_nghi_van, function($a, $b) {
                return $b['ty_le'] <=> $a['ty_le'];
            });
            
            // ========== DYNAMIC HIGHLIGHT TRONG NHÓM NGHI VẤN ==========
            $tong_nghi_van = count($report_nghi_van);
            
            // Xác định ngưỡng highlight
            if ($tong_nghi_van >= 20) {
                $top_threshold = 20;
            } elseif ($tong_nghi_van >= 15) {
                $top_threshold = 15;
            } elseif ($tong_nghi_van >= 10) {
                $top_threshold = 10;
            } elseif ($tong_nghi_van >= 5) {
                $top_threshold = 5;
            } else {
                $top_threshold = $tong_nghi_van;
            }
            
            // Thêm rank & flag highlight cho nhóm nghi vấn
            foreach ($report_nghi_van as $key => &$item) {
                $item['rank'] = $key + 1;
                // Tô đỏ nếu trong top threshold, còn lại tô cam
                $item['highlight_type'] = ($item['rank'] <= $top_threshold) ? 'red' : 'orange';
            }
            unset($item);
            
            // Nhóm OK không tô màu
            foreach ($report_ok as &$item) {
                $item['rank'] = 0;
                $item['highlight_type'] = 'none';
            }
            unset($item);
            
            // ← GỘP LẠI: Nghi vấn đầu, OK sau
            $report = array_merge($report_nghi_van, $report_ok);
            
            $debug_info = "Tháng: $thang | Đơn hàng: $count_donhang | Nhân viên: $count_nhanvien | Nghi vấn: $tong_nghi_van | Top highlight: $top_threshold";
            
            $logger->info("Report generated", [
                'thang' => $thang,
                'khoang' => "$tu_ngay ~ $den_ngay",
                'ket_qua_chung' => number_format($ket_qua_chung * 100, 2) . '%',
                'ty_le_nghi_van' => number_format($ty_le_nghi_van * 100, 2) . '%',
                'tong_nghi_van' => $tong_nghi_van,
                'top_threshold' => $top_threshold
            ]);
            
            if (empty($report)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            $logger->error("Report error", ['error' => $e->getMessage()]);
        }

        include 'views/report.view.php';
    }
}