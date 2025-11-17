<?php
/**
 * FILE: CONTROLLERS/KPIREPORTCONTROLLER.PHP
 * Báo Cáo KPI - Phát Hiện Gian Lận Theo Hiệu Suất Bán Hàng
 * 
 * LOGIC PHÁT HIỆN GIAN LẬN:
 * 1. So sánh số đơn trung bình nhân viên vs trung bình toàn hệ thống
 * 2. So sánh số đơn cao nhất vs benchmark cao nhất
 * 3. Phân tích xu hướng (đơn tăng/giảm)
 * 4. Xác định mức độ nghi vấn: Bình thường | Cảnh báo | Nguy hiểm
 */

class KPIReportController {
    
    public function showKPIReport() {
        $message = '';
        $type = '';
        $kpi_data = [];
        $statistics = [];
        $filters = [];
        $suspicious_employees = [];
        $normal_employees = [];
        $warning_level_stats = [];
        $logger = new Logger();
        
        try {
            $orderModel = new OrderModel();
            $employeeModel = new EmployeeModel();
            
            // Lấy danh sách tháng
            $available_months = $orderModel->getAvailableMonths();
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng upload file đơn hàng trước.";
                $type = 'warning';
                include 'views/kpi_report.view.php';
                return;
            }
            
            // ========== LẤY FILTERS ==========
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) {
                $thang = $available_months[0];
            }
            
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : $thang . '-01';
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-t', strtotime($thang . '-01'));
            
            // Validate & swap ngày
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
            
            // Lọc theo sản phẩm (2 ký tự đầu)
            $product_filter = !empty($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
            if (!empty($product_filter)) {
                $product_filter = substr($product_filter, 0, 2); // Lấy 2 ký tự đầu
            }
            
            $filters = [
                'thang' => $thang,
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'product_filter' => $product_filter
            ];
            
            // ========== LẤY DANH SÁCH NHÂN VIÊN ==========
            $employees = $employeeModel->getAll();
            if (!$employees) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                include 'views/kpi_report.view.php';
                return;
            }
            
            // ========== TÍNH TOÁN KPI CHO TỪng NHÂN VIÊN ==========
            $all_avg_orders = 0;
            $all_max_orders = 0;
            $all_min_orders = PHP_INT_MAX;
            $employee_kpi_list = [];
            $total_orders_all = 0;
            $total_employees_with_orders = 0;
            
            foreach ($employees as $emp) {
                $kpi = $this->calculateEmployeeKPI(
                    $emp, 
                    $tu_ngay, 
                    $den_ngay, 
                    $product_filter
                );
                
                if ($kpi['total_orders'] > 0) {
                    $employee_kpi_list[] = $kpi;
                    $total_orders_all += $kpi['total_orders'];
                    $total_employees_with_orders++;
                    
                    $all_max_orders = max($all_max_orders, $kpi['max_day_orders']);
                    $all_min_orders = min($all_min_orders, $kpi['min_day_orders']);
                }
            }
            
            // Tính trung bình chung
            if ($total_employees_with_orders > 0) {
                $all_avg_orders = $total_orders_all / $total_employees_with_orders;
            }
            
            // Nếu min_orders không được set, gán bằng 0
            if ($all_min_orders === PHP_INT_MAX) {
                $all_min_orders = 0;
            }
            
            // ========== PHÂN LOẠI VÀ ĐÁNH GIÁ NGHI VẤN ==========
            foreach ($employee_kpi_list as &$emp_kpi) {
                $emp_kpi = $this->calculateSuspicionLevel(
                    $emp_kpi,
                    $all_avg_orders,
                    $all_max_orders,
                    $all_min_orders
                );
                
                if ($emp_kpi['suspicion_score'] >= 70) {
                    $suspicious_employees[] = $emp_kpi;
                } else {
                    $normal_employees[] = $emp_kpi;
                }
            }
            unset($emp_kpi);
            
            // Sắp xếp theo suspicion_score giảm dần
            usort($suspicious_employees, function($a, $b) {
                return $b['suspicion_score'] <=> $a['suspicion_score'];
            });
            
            usort($normal_employees, function($a, $b) {
                return $b['suspicion_score'] <=> $a['suspicion_score'];
            });
            
            // ========== THỐNG KÊ ==========
            $statistics = [
                'total_employees' => count($employees),
                'employees_with_orders' => $total_employees_with_orders,
                'total_orders' => $total_orders_all,
                'avg_orders_per_emp' => round($all_avg_orders, 2),
                'max_orders_day' => $all_max_orders,
                'min_orders_day' => $all_min_orders,
                'suspicious_count' => count($suspicious_employees),
                'warning_count' => count(array_filter($suspicious_employees, fn($e) => $e['suspicion_level'] === 'warning')),
                'danger_count' => count(array_filter($suspicious_employees, fn($e) => $e['suspicion_level'] === 'danger')),
                'normal_count' => count($normal_employees)
            ];
            
            $kpi_data = array_merge($suspicious_employees, $normal_employees);
            
            $logger->info("KPI Report generated", [
                'thang' => $thang,
                'khoang' => "$tu_ngay ~ $den_ngay",
                'product_filter' => $product_filter ?: 'all',
                'suspicious' => $statistics['suspicious_count'],
                'danger' => $statistics['danger_count']
            ]);
            
            if (empty($kpi_data)) {
                $message = "⚠️ Không có dữ liệu cho khoảng thời gian này.";
                $type = 'warning';
            }
        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            $logger->error("KPI Report error", ['error' => $e->getMessage()]);
        }
        
        include 'views/kpi_report.view.php';
    }
    
    /**
     * Tính KPI cho một nhân viên
     */
    private function calculateEmployeeKPI($emp, $tu_ngay, $den_ngay, $product_filter = '') {
        $orderModel = new OrderModel();
        
        // Lấy dữ liệu đơn hàng theo ngày
        $daily_orders = $orderModel->getEmployeeDailyOrders(
            $emp['ma_nv'],
            $tu_ngay,
            $den_ngay,
            $product_filter
        );
        
        $total_orders = 0;
        $max_day_orders = 0;
        $min_day_orders = PHP_INT_MAX;
        $avg_daily_orders = 0;
        $working_days = 0;
        $highest_day = '';
        $lowest_day = '';
        $trend = 'stable';
        
        if (!empty($daily_orders)) {
            $order_counts = array_column($daily_orders, 'order_count');
            $total_orders = array_sum($order_counts);
            $max_day_orders = max($order_counts);
            $min_day_orders = min($order_counts);
            $working_days = count($daily_orders);
            $avg_daily_orders = $total_orders / $working_days;
            
            // Tìm ngày cao nhất
            $max_key = array_search($max_day_orders, $order_counts);
            $highest_day = $daily_orders[$max_key]['order_date'];
            
            // Tìm ngày thấp nhất
            $min_key = array_search($min_day_orders, $order_counts);
            $lowest_day = $daily_orders[$min_key]['order_date'];
            
            // Tính xu hướng (so sánh nửa đầu vs nửa sau)
            if ($working_days > 2) {
                $mid = intval($working_days / 2);
                $first_half_avg = array_sum(array_slice($order_counts, 0, $mid)) / $mid;
                $second_half_avg = array_sum(array_slice($order_counts, $mid)) / ($working_days - $mid);
                
                $trend_diff = $second_half_avg - $first_half_avg;
                if ($trend_diff > $first_half_avg * 0.2) {
                    $trend = 'increasing';
                } elseif ($trend_diff < -$first_half_avg * 0.2) {
                    $trend = 'decreasing';
                }
            }
        }
        
        // Nếu min_day_orders không được set, gán 0
        if ($min_day_orders === PHP_INT_MAX) {
            $min_day_orders = 0;
        }
        
        return [
            'ma_nv' => $emp['ma_nv'],
            'ten_nv' => $emp['ten_nv'] ?? '',
            'tinh' => $emp['tinh'] ?? '',
            'gs' => $emp['gs'] ?? '',
            'ngay_vao_cong_ty' => $emp['ngay_vao_cong_ty'] ?? '',
            'total_orders' => $total_orders,
            'avg_daily_orders' => round($avg_daily_orders, 2),
            'max_day_orders' => $max_day_orders,
            'min_day_orders' => $min_day_orders,
            'working_days' => $working_days,
            'highest_day' => $highest_day,
            'lowest_day' => $lowest_day,
            'trend' => $trend,
            'daily_details' => $daily_orders
        ];
    }
    
    /**
     * Tính mức độ nghi vấn
     * Thuật toán:
     * - Nếu TBD nhân viên < TBD chung × 0.5 → nguy hiểm
     * - Nếu TBD nhân viên < TBD chung × 0.8 → cảnh báo
     * - Nếu max_day < benchmark max × 0.6 → nguy hiểm
     * - Nếu đơn tăng đột ngột hoặc có pattern lạ → cảnh báo
     */
    private function calculateSuspicionLevel($emp_kpi, $system_avg, $system_max, $system_min) {
        $suspicion_score = 0;
        $reasons = [];
        $suspicion_level = 'normal'; // normal, warning, danger
        
        // 1. So sánh TBD nhân viên vs chung
        if ($system_avg > 0) {
            $ratio_to_avg = $emp_kpi['avg_daily_orders'] / $system_avg;
            
            if ($ratio_to_avg < 0.5) {
                $suspicion_score += 40;
                $reasons[] = "TBD nhân viên chỉ <strong>50% so với chung</strong>";
            } elseif ($ratio_to_avg < 0.8) {
                $suspicion_score += 20;
                $reasons[] = "TBD nhân viên <strong>80% so với chung</strong>";
            }
        }
        
        // 2. So sánh đơn cao nhất vs benchmark
        if ($system_max > 0) {
            $max_ratio = $emp_kpi['max_day_orders'] / $system_max;
            
            if ($max_ratio < 0.6) {
                $suspicion_score += 25;
                $reasons[] = "Đơn cao nhất chỉ <strong>60% so với cao nhất chung</strong>";
            } elseif ($max_ratio < 0.8) {
                $suspicion_score += 10;
                $reasons[] = "Đơn cao nhất <strong>80% so với cao nhất chung</strong>";
            }
        }
        
        // 3. Phân tích xu hướng
        if ($emp_kpi['trend'] === 'decreasing') {
            $suspicion_score += 15;
            $reasons[] = "<strong>Xu hướng giảm</strong> - đơn hàng giảm dần";
        } elseif ($emp_kpi['trend'] === 'increasing') {
            $suspicion_score -= 5; // Giảm điểm nếu tăng
            $reasons[] = "Xu hướng tăng - nỗ lực cải thiện";
        }
        
        // 4. Kiểm tra ngày làm việc (nếu ít ngày làm = mức độ hoài nghi)
        if ($emp_kpi['working_days'] < 5 && $emp_kpi['total_orders'] > 0) {
            $suspicion_score += 10;
            $reasons[] = "Số ngày làm việc ít (<strong>" . $emp_kpi['working_days'] . " ngày</strong>)";
        }
        
        // Xác định mức độ nghi vấn
        if ($suspicion_score >= 70) {
            $suspicion_level = 'danger';
        } elseif ($suspicion_score >= 40) {
            $suspicion_level = 'warning';
        } else {
            $suspicion_level = 'normal';
        }
        
        // Đảm bảo điểm không vượt 100
        $suspicion_score = min(100, max(0, $suspicion_score));
        
        $emp_kpi['suspicion_score'] = intval($suspicion_score);
        $emp_kpi['suspicion_level'] = $suspicion_level;
        $emp_kpi['suspicion_reasons'] = $reasons;
        
        return $emp_kpi;
    }
}