<?php
/**
 * FILE: CONTROLLERS/KPIREPORTCONTROLLER.PHP (FIXED)
 * Báo Cáo KPI - Phát Hiện Gian Lận Theo 5 Tiêu Chí
 * 
 * FIX:
 * 1. Consistency Score không hiển thị đúng - logic tính lại
 * 2. KPI Score được tính ngược (100 - total_score) cần điều chỉnh thang điểm
 * 3. Product filter: Tìm theo 2 kí tự đầu hoặc tất cả sản phẩm
 * 4. Detail modal cho nhân viên không hoạt động
 */

class KPIReportController {
    
    public function showKPIReport() {
        $message = '';
        $type = '';
        $kpi_data = [];
        $statistics = [];
        $filters = [];
        $available_months = [];
        $available_products = [];
        $logger = new Logger();
        
        try {
            $orderModel = new OrderModel();
            $employeeModel = new EmployeeModel();
            
            // ========== LẤY DANH SÁCH THÁNG ==========
            $available_months = $orderModel->getAvailableMonths();
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng upload file đơn hàng trước.";
                $type = 'warning';
                include 'views/kpi_report.view.php';
                return;
            }
            
            // ========== LẤY DANH SÁCH SẢN PHẨM ==========
            $available_products = $orderModel->getAvailableProducts();
            
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
            
            $product_filter = !empty($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
            if (!empty($product_filter) && $product_filter !== '--all--') {
                $product_filter = substr($product_filter, 0, 2);
            } elseif ($product_filter === '--all--') {
                $product_filter = '';
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
            
            // ========== TÍNH TOÁN KPI CHO TỪNG NHÂN VIÊN ==========
            $employee_kpi_list = [];
            $system_metrics = [
                'total_orders' => 0,
                'total_amount' => 0,
                'all_daily_orders' => [],
                'all_daily_amounts' => [],
                'employee_count' => 0,
                'max_daily_orders' => 0,
                'max_daily_amount' => 0,
                'total_working_days' => 0
            ];
            
            foreach ($employees as $emp) {
                $kpi = $this->calculateEmployeeKPI(
                    $emp,
                    $tu_ngay,
                    $den_ngay,
                    $product_filter
                );
                
                if ($kpi['total_orders'] > 0) {
                    $employee_kpi_list[] = $kpi;
                    
                    $system_metrics['total_orders'] += $kpi['total_orders'];
                    $system_metrics['total_amount'] += $kpi['total_amount'];
                    $system_metrics['total_working_days'] += $kpi['working_days'];
                    $system_metrics['employee_count']++;
                    $system_metrics['max_daily_orders'] = max(
                        $system_metrics['max_daily_orders'],
                        $kpi['max_day_orders']
                    );
                    $system_metrics['max_daily_amount'] = max(
                        $system_metrics['max_daily_amount'],
                        $kpi['max_day_amount']
                    );
                    
                    $system_metrics['all_daily_orders'] = array_merge(
                        $system_metrics['all_daily_orders'],
                        $kpi['daily_orders'] ?? []
                    );
                    $system_metrics['all_daily_amounts'] = array_merge(
                        $system_metrics['all_daily_amounts'],
                        $kpi['daily_amounts'] ?? []
                    );
                }
            }
            
            // ========== TÍNH BENCHMARK CHUNG ==========
            if ($system_metrics['employee_count'] > 0) {
                $system_metrics['avg_orders_per_emp'] = $system_metrics['total_orders'] / $system_metrics['employee_count'];
                $system_metrics['avg_daily_orders'] = $system_metrics['total_orders'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['avg_daily_amount'] = $system_metrics['total_amount'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['std_dev_orders'] = $this->calculateStdDev($system_metrics['all_daily_orders']);
                $system_metrics['std_dev_amount'] = $this->calculateStdDev($system_metrics['all_daily_amounts']);
            }
            
            // ========== PHÂN LOẠI VÀ ĐÁNH GIÁ KPI ==========
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employee_kpi_list as &$emp_kpi) {
                $emp_kpi = $this->calculateKPIScore(
                    $emp_kpi,
                    $system_metrics
                );
                
                if ($emp_kpi['kpi_score'] >= 70) {
                    $suspicious_employees[] = $emp_kpi;
                } elseif ($emp_kpi['kpi_score'] >= 40) {
                    $warning_employees[] = $emp_kpi;
                } else {
                    $normal_employees[] = $emp_kpi;
                }
            }
            unset($emp_kpi);
            
            // Sắp xếp theo score giảm dần
            usort($suspicious_employees, fn($a, $b) => $b['kpi_score'] <=> $a['kpi_score']);
            usort($warning_employees, fn($a, $b) => $b['kpi_score'] <=> $a['kpi_score']);
            usort($normal_employees, fn($a, $b) => $b['kpi_score'] <=> $a['kpi_score']);
            
            // ========== THỐNG KÊ ==========
            $statistics = [
                'total_employees' => count($employees),
                'employees_with_orders' => $system_metrics['employee_count'],
                'total_orders' => $system_metrics['total_orders'],
                'total_amount' => $system_metrics['total_amount'],
                'avg_orders_per_emp' => round($system_metrics['avg_orders_per_emp'] ?? 0, 2),
                'avg_daily_orders' => round($system_metrics['avg_daily_orders'] ?? 0, 2),
                'avg_daily_amount' => round($system_metrics['avg_daily_amount'] ?? 0, 0),
                'max_daily_orders' => $system_metrics['max_daily_orders'],
                'max_daily_amount' => $system_metrics['max_daily_amount'],
                'std_dev_orders' => round($system_metrics['std_dev_orders'] ?? 0, 2),
                'std_dev_amount' => round($system_metrics['std_dev_amount'] ?? 0, 0),
                'suspicious_count' => count($suspicious_employees),
                'warning_count' => count($warning_employees),
                'normal_count' => count($normal_employees),
                'danger_count' => count($suspicious_employees)
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
            $logger->info("KPI Report generated", [
                'thang' => $thang,
                'khoang' => "$tu_ngay ~ $den_ngay",
                'product_filter' => $product_filter ?: 'all',
                'suspicious' => $statistics['suspicious_count']
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
        
        $daily_data = $orderModel->getEmployeeDailyKPI(
            $emp['ma_nv'],
            $tu_ngay,
            $den_ngay,
            $product_filter
        );
        
        $total_orders = 0;
        $total_amount = 0;
        $max_day_orders = 0;
        $max_day_amount = 0;
        $min_day_orders = PHP_INT_MAX;
        $min_day_amount = PHP_INT_MAX;
        $avg_daily_orders = 0;
        $working_days = 0;
        $daily_orders = [];
        $daily_amounts = [];
        $trend = 'stable';
        $consistency_score = 100;
        $volatility = 0;
        
        if (!empty($daily_data)) {
            $order_counts = array_column($daily_data, 'order_count');
            $amounts = array_column($daily_data, 'total_amount');
            
            $total_orders = array_sum($order_counts);
            $total_amount = array_sum($amounts);
            $max_day_orders = max($order_counts);
            $max_day_amount = max($amounts);
            $min_day_orders = min($order_counts);
            $min_day_amount = min($amounts);
            $working_days = count($daily_data);
            $avg_daily_orders = $working_days > 0 ? $total_orders / $working_days : 0;
            
            $daily_orders = $order_counts;
            $daily_amounts = $amounts;
            
            // Tính volatility
            $volatility = $this->calculateStdDev($order_counts);
            
            // FIX: Tính consistency score - càng ít biến động càng cao (0-100)
            if ($working_days > 1) {
                $avg_orders = array_sum($order_counts) / count($order_counts);
                if ($avg_orders > 0) {
                    $deviations = array_map(fn($x) => abs($x - $avg_orders), $order_counts);
                    $max_deviation = max($deviations);
                    $variation_ratio = $max_deviation / $avg_orders;
                    
                    // variation_ratio nhỏ = consistency cao
                    if ($variation_ratio <= 0.1) {
                        $consistency_score = 100;
                    } elseif ($variation_ratio <= 0.3) {
                        $consistency_score = 85;
                    } elseif ($variation_ratio <= 0.5) {
                        $consistency_score = 70;
                    } elseif ($variation_ratio <= 1.0) {
                        $consistency_score = 50;
                    } else {
                        $consistency_score = max(0, 100 - ($variation_ratio * 50));
                    }
                }
            }
            
            // Tính xu hướng
            if ($working_days > 2) {
                $mid = intval($working_days / 2);
                $first_half_avg = array_sum(array_slice($order_counts, 0, $mid)) / $mid;
                $second_half_avg = array_sum(array_slice($order_counts, $mid)) / ($working_days - $mid);
                
                $trend_diff = $second_half_avg - $first_half_avg;
                if ($trend_diff > $first_half_avg * 0.15) {
                    $trend = 'increasing';
                } elseif ($trend_diff < -$first_half_avg * 0.15) {
                    $trend = 'decreasing';
                }
            }
        }
        
        if ($min_day_orders === PHP_INT_MAX) $min_day_orders = 0;
        if ($min_day_amount === PHP_INT_MAX) $min_day_amount = 0;
        
        return [
            'ma_nv' => $emp['ma_nv'],
            'ten_nv' => $emp['ten_nv'] ?? '',
            'tinh' => $emp['tinh'] ?? '',
            'gs' => $emp['gs'] ?? '',
            'ngay_vao_cong_ty' => $emp['ngay_vao_cong_ty'] ?? '',
            'total_orders' => $total_orders,
            'total_amount' => $total_amount,
            'avg_daily_orders' => round($avg_daily_orders, 2),
            'avg_daily_amount' => round($total_amount / max(1, $working_days), 0),
            'max_day_orders' => $max_day_orders,
            'max_day_amount' => $max_day_amount,
            'min_day_orders' => $min_day_orders,
            'min_day_amount' => $min_day_amount,
            'working_days' => $working_days,
            'trend' => $trend,
            'consistency_score' => round($consistency_score, 1),
            'volatility' => round($volatility, 2),
            'daily_orders' => $daily_orders,
            'daily_amounts' => $daily_amounts
        ];
    }
    
    /**
     * Tính KPI Score (Điểm KPI tổng hợp)
     * 5 Tiêu Chí:
     * 1. Hiệu suất (30%)
     * 2. Tính nhất quán (25%)
     * 3. Xu hướng (15%)
     * 4. Độ biến động (20%)
     * 5. Thời gian hoạt động (10%)
     * 
     * FIX: Score càng cao = risk càng cao
     */
    private function calculateKPIScore($emp_kpi, $system_metrics) {
        $score_performance = 0;
        $score_consistency = 0;
        $score_trend = 0;
        $score_volatility = 0;
        $score_working_days = 0;
        $reasons = [];
        
        // ========== 1. HIỆU SUẤT (30%) ==========
        if (isset($system_metrics['avg_daily_orders']) && $system_metrics['avg_daily_orders'] > 0) {
            $perf_ratio = $emp_kpi['avg_daily_orders'] / $system_metrics['avg_daily_orders'];
            
            if ($perf_ratio >= 1.2) {
                // Hiệu suất rất cao = risk cao
                $score_performance = 100;
                $reasons[] = "Hiệu suất " . number_format($perf_ratio * 100, 0) . "% so với chung (cao bất thường)";
            } elseif ($perf_ratio >= 1.0) {
                $score_performance = 60;
            } elseif ($perf_ratio >= 0.8) {
                $score_performance = 30;
            } elseif ($perf_ratio >= 0.6) {
                $score_performance = 20;
            } else {
                $score_performance = 10;
            }
        }
        
        // ========== 2. TÍNH NHẤT QUÁN (25%) ==========
        $consistency = $emp_kpi['consistency_score'];
        if ($consistency >= 80) {
            $score_consistency = 10; // Nhất quán = risk thấp
        } elseif ($consistency >= 60) {
            $score_consistency = 30;
        } elseif ($consistency >= 40) {
            $score_consistency = 60;
            $reasons[] = "Hiệu suất biến động " . number_format(100 - $consistency, 0) . "%";
        } else {
            $score_consistency = 100;
            $reasons[] = "Hiệu suất rất biến động (" . number_format(100 - $consistency, 0) . "%)";
        }
        
        // ========== 3. XU HƯỚNG (15%) ==========
        if ($emp_kpi['trend'] === 'increasing') {
            $score_trend = 80;
            $reasons[] = "Xu hướng đơn hàng tăng đột ngột";
        } elseif ($emp_kpi['trend'] === 'stable') {
            $score_trend = 30;
        } else {
            $score_trend = 10;
        }
        
        // ========== 4. ĐỘ BIẾN ĐỘNG (20%) ==========
        if (isset($system_metrics['std_dev_orders']) && $system_metrics['std_dev_orders'] > 0) {
            $volatility_ratio = $emp_kpi['volatility'] / $system_metrics['std_dev_orders'];
            
            if ($volatility_ratio <= 0.8) {
                $score_volatility = 10;
            } elseif ($volatility_ratio <= 1.2) {
                $score_volatility = 40;
            } elseif ($volatility_ratio <= 1.8) {
                $score_volatility = 70;
                $reasons[] = "Biến động cao gấp " . number_format($volatility_ratio, 1) . "x chung";
            } else {
                $score_volatility = 100;
                $reasons[] = "Biến động rất cao gấp " . number_format($volatility_ratio, 1) . "x chung";
            }
        } else {
            $score_volatility = 10;
        }
        
        // ========== 5. THỜI GIAN HOẠT ĐỘNG (10%) ==========
        $days_in_range = intval((strtotime('2024-01-10') - strtotime('2024-01-01')) / 86400) + 1;
        $working_days_ratio = $emp_kpi['working_days'] / max(1, $days_in_range);
        
        if ($working_days_ratio >= 0.95) {
            $score_working_days = 10;
        } elseif ($working_days_ratio >= 0.8) {
            $score_working_days = 30;
        } elseif ($working_days_ratio >= 0.6) {
            $score_working_days = 60;
        } else {
            $score_working_days = 80;
            $reasons[] = "Ngày hoạt động chỉ " . number_format($working_days_ratio * 100, 0) . "%";
        }
        
        // ========== TÍNH ĐIỂM TỔNG HỢP ==========
        $total_score = 
            ($score_performance * 0.30) +
            ($score_consistency * 0.25) +
            ($score_trend * 0.15) +
            ($score_volatility * 0.20) +
            ($score_working_days * 0.10);
        
        // FIX: Score là điểm nghi vấn (0-100), không cần đảo ngược
        $kpi_score = intval($total_score);
        $kpi_score = min(100, max(0, $kpi_score));
        
        // Xác định mức độ
        if ($kpi_score >= 70) {
            $kpi_level = 'danger';
        } elseif ($kpi_score >= 40) {
            $kpi_level = 'warning';
        } else {
            $kpi_level = 'normal';
        }
        
        if (empty($reasons)) {
            $reasons[] = "Hoạt động bình thường";
        }
        
        $emp_kpi['kpi_score'] = $kpi_score;
        $emp_kpi['kpi_level'] = $kpi_level;
        $emp_kpi['kpi_reasons'] = $reasons;
        $emp_kpi['score_breakdown'] = [
            'performance' => intval($score_performance),
            'consistency' => intval($score_consistency),
            'trend' => intval($score_trend),
            'volatility' => intval($score_volatility),
            'working_days' => intval($score_working_days)
        ];
        
        return $emp_kpi;
    }
    
    /**
     * Tính Standard Deviation
     */
    private function calculateStdDev($arr) {
        if (count($arr) < 2) return 0;
        
        $avg = array_sum($arr) / count($arr);
        $deviations = array_map(fn($x) => pow($x - $avg, 2), $arr);
        $variance = array_sum($deviations) / count($deviations);
        
        return sqrt($variance);
    }
}