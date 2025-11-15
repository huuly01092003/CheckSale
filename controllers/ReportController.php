<?php
class ReportController {
    public function showReport() {
        // Khởi tạo tất cả biến
        $message = '';
        $type = '';
        $report = [];
        $so_ngay = 0;
        $ket_qua_chung = 0;
        $ty_le_nghi_van = 0;
        $tu_ngay = date('Y-m-d');
        $den_ngay = date('Y-m-d');
        $total_lay = 0;
        $total_xem = 0;
        $debug_info = '';
        
        try {
            $orderModel = new OrderModel();
            $ky = $orderModel->getPeriodRange();
            
            $ky_start = $ky['min'];
            $ky_end = $ky['max'];

            // Lấy ngày từ GET
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : '';
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : $tu_ngay;
            
            if (!$tu_ngay) {
                $tu_ngay = date('Y-m-d', strtotime('-2 days'));
                $den_ngay = date('Y-m-d');
            }

            // Đảm bảo tu_ngay <= den_ngay
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                $temp = $tu_ngay;
                $tu_ngay = $den_ngay;
                $den_ngay = $temp;
            }

            // Kiểm tra số lượng dữ liệu
            $pdo = Config::getPDO();
            $count_donhang = $pdo->query("SELECT COUNT(*) FROM donhang")->fetchColumn();
            $count_nhanvien = $pdo->query("SELECT COUNT(*) FROM nhanvien")->fetchColumn();
            $debug_info = "DB: Đơn hàng=$count_donhang, Nhân viên=$count_nhanvien";

            // Lấy dữ liệu với kiểm tra type
            $total_lay_raw = $orderModel->getTotalByPeriod($ky_start, $ky_end);
            $total_xem_raw = $orderModel->getTotalByPeriod($tu_ngay, $den_ngay);

            $total_lay = is_numeric($total_lay_raw) ? floatval($total_lay_raw) : 0;
            $total_xem = is_numeric($total_xem_raw) ? floatval($total_xem_raw) : 0;

            // Tính toán an toàn
            if ($total_lay > 0) {
                $ket_qua_chung = $total_xem / $total_lay;
            } else {
                $ket_qua_chung = 0;
            }
            
            $ty_le_nghi_van = $ket_qua_chung * 1.5;
            $ngay_diff = intval((strtotime($den_ngay) - strtotime($tu_ngay)) / 86400);
            $so_ngay = max(1, $ngay_diff + 1);

            $employeeModel = new EmployeeModel();
            $employees = $employeeModel->getAll();

            if (!$employees) {
                $employees = [];
                $message = "⚠ Không có dữ liệu nhân viên. Vui lòng import file danh sách nhân viên trước.";
                $type = 'warning';
            }

            $report = [];
            foreach ($employees as $emp) {
                $ds_tim_kiem_raw = $orderModel->getByEmployeeAndPeriod($emp['ma_nv'], $ky_start, $ky_end);
                $ds_tien_do_raw = $orderModel->getByEmployeeAndPeriod($emp['ma_nv'], $tu_ngay, $den_ngay);

                $ds_tim_kiem = is_numeric($ds_tim_kiem_raw) ? floatval($ds_tim_kiem_raw) : 0;
                $ds_tien_do = is_numeric($ds_tien_do_raw) ? floatval($ds_tien_do_raw) : 0;

                if ($ds_tien_do > 0) {
                    if ($ds_tim_kiem > 0) {
                        $ty_le = $ds_tien_do / $ds_tim_kiem;
                    } else {
                        $ty_le = 0;
                    }
                    
                    $report[] = array_merge($emp, [
                        'ds_tim_kiem' => $ds_tim_kiem, 
                        'ds_tien_do' => $ds_tien_do, 
                        'ty_le' => $ty_le
                    ]);
                }
            }

            usort($report, function($a, $b) {
                return $b['ty_le'] <=> $a['ty_le'];
            });
            
            if (empty($report)) {
                $message = "⚠ Không có dữ liệu báo cáo cho khoảng thời gian này.";
                $type = 'warning';
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            error_log("Report error: " . $e->getMessage());
        }

        include 'views/report.view.php';
    }
}
?>
