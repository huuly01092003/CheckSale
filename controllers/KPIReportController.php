<?php
class KPIReportController {
    
public function showKPIReport() {
        $message = '';
        $type = '';
        $kpi_data = [];
        $statistics = [];
        $filters = [];
        $available_months = [];
        $available_products = [];
        $available_gs = [];
        // Đã xóa $available_statuses
        $logger = new Logger();
        
        try {
            $orderModel = new OrderModel();
            $employeeModel = new EmployeeModel();
            
            // ========== LẤY DATA DROPDOWN ==========
            $available_months = $orderModel->getAvailableMonths();
            if (empty($available_months)) {
                $message = "⚠️ Chưa có dữ liệu. Vui lòng upload file đơn hàng trước.";
                $type = 'warning';
                include 'views/kpi_report.view.php';
                return;
            }
            
            $available_products = $orderModel->getAvailableProducts();
            $available_gs = $employeeModel->getAvailableGS();
            // Đã xóa getAvailableStatuses
            
            // ========== LẤY FILTERS ==========
            $thang = !empty($_GET['thang']) ? $_GET['thang'] : $available_months[0];
            if (!in_array($thang, $available_months)) $thang = $available_months[0];
            
            $tu_ngay = !empty($_GET['tu_ngay']) ? $_GET['tu_ngay'] : $thang . '-01';
            $den_ngay = !empty($_GET['den_ngay']) ? $_GET['den_ngay'] : date('Y-m-t', strtotime($thang . '-01'));
            
            // Validate Date Logic
            if (strtotime($tu_ngay) > strtotime($den_ngay)) {
                list($tu_ngay, $den_ngay) = [$den_ngay, $tu_ngay];
            }
            
            $product_filter = !empty($_GET['product_filter']) ? trim($_GET['product_filter']) : '';
            if ($product_filter === '--all--') $product_filter = '';
            // Giữ nguyên logic lấy 2 ký tự đầu cho product filter
            if (!empty($product_filter)) $product_filter = substr($product_filter, 0, 2);
            
            $gs_filter = !empty($_GET['gs_filter']) ? trim($_GET['gs_filter']) : '';
            
            // Đã xóa logic status_filter
            
            $filters = [
                'thang' => $thang,
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'product_filter' => $product_filter,
                'gs_filter' => $gs_filter
            ];
            
            $employees = $employeeModel->getAll();
            if (!$employees) {
                $message = "⚠️ Không có dữ liệu nhân viên.";
                $type = 'warning';
                include 'views/kpi_report.view.php';
                return;
            }
            
            // ========== MAIN CALCULATION LOOP ==========
            $employee_kpi_list = [];
            $all_risk_scores = [];
            $system_metrics = [
                'total_orders' => 0,
                'total_amount' => 0,
                'all_daily_orders' => [],
                'all_daily_amounts' => [],
                'employee_count' => 0,
                'max_daily_orders' => 0,
                'max_daily_amount' => 0,
                'total_working_days' => 0,
                'gs_groups' => []
            ];
            
            foreach ($employees as $emp) {
                if (!empty($gs_filter) && $emp['gs'] !== $gs_filter) continue;
                
                // Bỏ tham số status_filter
                $kpi = $this->calculateEmployeeKPI($emp, $tu_ngay, $den_ngay, $product_filter, $orderModel);
                
                $customTotalOrder = $orderModel->countCustomTotalOrders($emp['ma_nv'], $tu_ngay, $den_ngay);
                $kpi['total_orders'] = $customTotalOrder;
                
                if ($kpi['working_days'] > 0) {
                    $kpi['avg_daily_orders'] = round($customTotalOrder / $kpi['working_days'], 2);
                }
                
                if ($kpi['total_orders'] > 0) {
                    $employee_kpi_list[] = $kpi;
                    $system_metrics['total_orders'] += $kpi['total_orders'];
                    $system_metrics['total_amount'] += $kpi['total_amount'];
                    $system_metrics['total_working_days'] += $kpi['working_days'];
                    $system_metrics['employee_count']++;
                    $system_metrics['max_daily_orders'] = max($system_metrics['max_daily_orders'], $kpi['max_day_orders']);
                    $system_metrics['max_daily_amount'] = max($system_metrics['max_daily_amount'], $kpi['max_day_amount']);
                    
                    $system_metrics['all_daily_orders'] = array_merge($system_metrics['all_daily_orders'], $kpi['daily_orders'] ?? []);
                    $system_metrics['all_daily_amounts'] = array_merge($system_metrics['all_daily_amounts'], $kpi['daily_amounts'] ?? []);
                    
                    // GS Grouping logic... (giữ nguyên)
                    $gs = $emp['gs'] ?? 'N/A';
                    if (!isset($system_metrics['gs_groups'][$gs])) {
                        $system_metrics['gs_groups'][$gs] = ['total_orders' => 0, 'count' => 0, 'daily_orders' => []];
                    }
                    $system_metrics['gs_groups'][$gs]['total_orders'] += $kpi['total_orders'];
                    $system_metrics['gs_groups'][$gs]['count']++;
                    $system_metrics['gs_groups'][$gs]['daily_orders'] = array_merge($system_metrics['gs_groups'][$gs]['daily_orders'], $kpi['daily_orders'] ?? []);
                }
            }
            
            // ========== BENCHMARK & CLASSIFICATION ==========
            if ($system_metrics['employee_count'] > 0) {
                $system_metrics['avg_orders_per_emp'] = $system_metrics['total_orders'] / $system_metrics['employee_count'];
                $system_metrics['avg_daily_orders'] = $system_metrics['total_orders'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['avg_daily_amount'] = $system_metrics['total_amount'] / max(1, $system_metrics['total_working_days']);
                $system_metrics['std_dev_orders'] = $this->calculateStdDev($system_metrics['all_daily_orders']);
                $system_metrics['std_dev_amount'] = $this->calculateStdDev($system_metrics['all_daily_amounts']);
            }
            
            $suspicious_employees = [];
            $warning_employees = [];
            $normal_employees = [];
            
            foreach ($employee_kpi_list as &$emp_kpi) {
                $emp_kpi = $this->calculateRiskScoreImproved($emp_kpi, $system_metrics);
                $all_risk_scores[] = $emp_kpi['risk_score'];
                
                if ($emp_kpi['risk_score'] >= 75) $suspicious_employees[] = $emp_kpi;
                elseif ($emp_kpi['risk_score'] >= 50) $warning_employees[] = $emp_kpi;
                else $normal_employees[] = $emp_kpi;
            }
            unset($emp_kpi);
            
            // Sorting
            usort($suspicious_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($warning_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            usort($normal_employees, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
            
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
                'warning_count' => count($warning_employees),
                'danger_count' => count($suspicious_employees),
                'normal_count' => count($normal_employees)
            ];
            
            $kpi_data = array_merge($suspicious_employees, $warning_employees, $normal_employees);
            
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
private function calculateEmployeeKPI($emp, $tu_ngay, $den_ngay, $product_filter = '', $status_filter = [], $orderModel = null) {
    if (!$orderModel) $orderModel = new OrderModel();
    
    $daily_data = $orderModel->getEmployeeDailyKPI($emp['ma_nv'], $tu_ngay, $den_ngay, $product_filter);
        
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
        $new_customer_ratio = 0;
        $repeat_customer_ratio = 0;
        $volatility = 0;
        $consistency_score = 100;
        $trend = 'stable';
        $trend_slope = 0;
        $trend_r_squared = 0;
        
        if (!empty($daily_data)) {
            $order_counts = array_column($daily_data, 'order_count');
            $amounts = array_column($daily_data, 'total_amount');
            $unique_customers = array_column($daily_data, 'unique_customers');
            $total_unique_customers = array_sum($unique_customers);
            $total_transactions = array_sum($order_counts);
            
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
            
            // Tính volatility (Std Dev)
            $volatility = $this->calculateStdDev($order_counts);
            
            // ✅ Consistency Score: CV formula (ISO 5725)
            $consistency_data = $this->calculateConsistencyScoreCV($order_counts);
            $consistency_score = $consistency_data['consistency_score'];
            
            // ✅ Trend Analysis: Linear Regression
            $trend_data = $this->calculateTrendLinearRegression($order_counts);
            $trend = $trend_data['trend'];
            $trend_slope = $trend_data['slope'];
            $trend_r_squared = $trend_data['r_squared'];
            
            // Tính tỉ lệ khách hàng mới vs lặp lại
            if ($total_unique_customers > 0 && $total_transactions > 0) {
                $new_customer_ratio = $total_unique_customers / $total_transactions;
                $repeat_customer_ratio = 1 - $new_customer_ratio;
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
            'trend_slope' => $trend_slope,
            'trend_r_squared' => $trend_r_squared,
            'consistency_score' => round($consistency_score, 1),
            'volatility' => round($volatility, 2),
            'new_customer_ratio' => round($new_customer_ratio, 3),
            'repeat_customer_ratio' => round($repeat_customer_ratio, 3),
            'daily_orders' => $daily_orders,
            'daily_amounts' => $daily_amounts
        ];
    }
    
    /**
     * ✅ CONSISTENCY SCORE - CV FORMULA (ISO 5725)
     * CV = (Std Dev / Mean) × 100%
     */
    private function calculateConsistencyScoreCV($daily_orders) {
        if (count($daily_orders) < 2) {
            return ['consistency_score' => 100, 'cv_percent' => 0];
        }
        
        $mean = array_sum($daily_orders) / count($daily_orders);
        $deviations = array_map(fn($x) => pow($x - $mean, 2), $daily_orders);
        $variance = array_sum($deviations) / count($daily_orders);
        $std_dev = sqrt($variance);
        
        if ($mean > 0) {
            $cv_percent = ($std_dev / $mean) * 100;
        } else {
            $cv_percent = 0;
        }
        
        // ISO 5725 Mapping
        if ($cv_percent <= 10) {
            $consistency_score = 100;
        } elseif ($cv_percent <= 20) {
            $consistency_score = 90;
        } elseif ($cv_percent <= 30) {
            $consistency_score = 80;
        } elseif ($cv_percent <= 50) {
            $consistency_score = 60;
        } elseif ($cv_percent <= 75) {
            $consistency_score = 40;
        } elseif ($cv_percent <= 100) {
            $consistency_score = 20;
        } else {
            $consistency_score = 10;
        }
        
        return [
            'consistency_score' => $consistency_score,
            'cv_percent' => $cv_percent
        ];
    }
    
    /**
     * ✅ TREND ANALYSIS - LINEAR REGRESSION
     * slope = Σ[(Xi - X̄)(Yi - Ȳ)] / Σ[(Xi - X̄)²]
     */
    private function calculateTrendLinearRegression($daily_orders) {
        if (count($daily_orders) < 2) {
            return [
                'slope' => 0,
                'trend' => 'stable',
                'r_squared' => 0
            ];
        }
        
        $n = count($daily_orders);
        $x_values = range(1, $n);
        $y_values = $daily_orders;
        
        $x_mean = array_sum($x_values) / $n;
        $y_mean = array_sum($y_values) / $n;
        
        $numerator = 0;
        $denominator = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x_values[$i] - $x_mean) * ($y_values[$i] - $y_mean);
            $denominator += pow($x_values[$i] - $x_mean, 2);
        }
        
        $slope = ($denominator != 0) ? $numerator / $denominator : 0;
        
        // Tính R²
        $ss_tot = 0;
        $ss_res = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $y_pred = $y_mean + $slope * ($x_values[$i] - $x_mean);
            $ss_tot += pow($y_values[$i] - $y_mean, 2);
            $ss_res += pow($y_values[$i] - $y_pred, 2);
        }
        
        $r_squared = ($ss_tot != 0) ? 1 - ($ss_res / $ss_tot) : 0;
        
        // Classify trend
        $trend = 'stable';
        if ($slope >= 0.15 && $r_squared >= 0.7) {
            $trend = 'increasing';
        } elseif ($slope >= 0.05) {
            $trend = 'slightly_increasing';
        } elseif ($slope <= -0.15 && $r_squared >= 0.7) {
            $trend = 'decreasing';
        } elseif ($slope <= -0.05) {
            $trend = 'slightly_decreasing';
        }
        
        return [
            'slope' => round($slope, 4),
            'trend' => $trend,
            'r_squared' => round($r_squared, 3)
        ];
    }
    
    /**
     * ⚠️ RISK SCORE - IMPROVED FORMULA (6 TIÊU CHÍ)
     * 
     * Trọng số CẢI TIẾN:
     * - Performance: 30% (↑ từ 25%)
     * - Consistency: 25%
     * - Customer: 20% (↑ từ 10%) ⭐ QUAN TRỌNG
     * - Trend: 15% (↓ từ 20%)
     * - Volatility: 10%
     * - Activity: 5% (↓ từ 10%)
     * 
     * + Interaction Bonus: +15 nếu 3+ tiêu chí cao
     */
    private function calculateRiskScoreImproved($emp_kpi, $system_metrics) {
        $score_performance = 0;
        $score_consistency = 0;
        $score_trend = 0;
        $score_volatility = 0;
        $score_customer = 0;
        $score_activity = 0;
        $interaction_bonus = 0;
        $reasons = [];
        $criteria_count = 0;
        
        // ========== 1. PERFORMANCE (30%) ==========
        if (isset($system_metrics['avg_daily_orders']) && $system_metrics['avg_daily_orders'] > 0) {
            $perf_ratio = $emp_kpi['avg_daily_orders'] / $system_metrics['avg_daily_orders'];
            
            if ($perf_ratio >= 1.5) {
                $score_performance = 100;
                $criteria_count++;
                $reasons[] = "Hiệu suất " . number_format($perf_ratio * 100, 0) . "% so với chung";
            } elseif ($perf_ratio >= 1.3) {
                $score_performance = 75;
                $criteria_count++;
            } elseif ($perf_ratio >= 1.0) {
                $score_performance = 40;
            } else {
                $score_performance = 10;
            }
        }
        
        // ========== 2. CONSISTENCY (25%) ==========
        $consistency = $emp_kpi['consistency_score'];
        
        if ($consistency >= 85) {
            $score_consistency = 5;
        } elseif ($consistency >= 70) {
            $score_consistency = 20;
        } elseif ($consistency >= 50) {
            $score_consistency = 50;
            $criteria_count++;
            $reasons[] = "Tính nhất quán: " . number_format($consistency, 1) . "%";
        } else {
            $score_consistency = 85;
            $criteria_count++;
            $reasons[] = "Rất không nhất quán: " . number_format($consistency, 1) . "%";
        }
        
        // ========== 3. TREND (15%) ==========
        $slope = $emp_kpi['trend_slope'];
        $r_squared = $emp_kpi['trend_r_squared'];
        
        if ($slope >= 0.15 && $r_squared >= 0.7) {
            $score_trend = 85;
            $criteria_count++;
            $reasons[] = "Tăng mạnh (slope: " . number_format($slope, 3) . ", r²: " . number_format($r_squared, 2) . ")";
        } elseif ($slope >= 0.05) {
            $score_trend = 40;
        } elseif ($slope <= -0.15) {
            $score_trend = 10;
        } else {
            $score_trend = 20;
        }
        
        // ========== 4. VOLATILITY (10%) ==========
        if (isset($system_metrics['std_dev_orders']) && $system_metrics['std_dev_orders'] > 0) {
            $volatility_ratio = $emp_kpi['volatility'] / $system_metrics['std_dev_orders'];
            
            if ($volatility_ratio >= 2.0) {
                $score_volatility = 85;
                $criteria_count++;
                $reasons[] = "Biến động cao gấp " . number_format($volatility_ratio, 1) . "x";
            } elseif ($volatility_ratio >= 1.5) {
                $score_volatility = 65;
                $criteria_count++;
            } elseif ($volatility_ratio >= 1.0) {
                $score_volatility = 40;
            } else {
                $score_volatility = 15;
            }
        }
        
        // ========== 5. CUSTOMER (20%) ⭐ TĂNG ==========
        $new_customer_pct = $emp_kpi['new_customer_ratio'] * 100;
        
        if ($new_customer_pct > 85) {
            $score_customer = 95;
            $criteria_count++;
            $reasons[] = "🚩 Khách mới quá cao: " . number_format($new_customer_pct, 1) . "%";
        } elseif ($new_customer_pct > 70) {
            $score_customer = 75;
            $criteria_count++;
        } elseif ($new_customer_pct > 50) {
            $score_customer = 50;
        } else {
            $score_customer = 15;
        }
        
        // ========== 6. ACTIVITY (5%) ⬇️ GIẢM ==========
        $days_in_range = max(1, intval((strtotime('2024-01-10') - strtotime('2024-01-01')) / 86400) + 1);
        $working_days_ratio = $emp_kpi['working_days'] / max(1, $days_in_range);
        
        if ($working_days_ratio < 0.4) {
            $score_activity = 75;
            $criteria_count++;
            $reasons[] = "Hoạt động chỉ " . number_format($working_days_ratio * 100, 0) . "%";
        } elseif ($working_days_ratio < 0.6) {
            $score_activity = 45;
        } else {
            $score_activity = 10;
        }
        
        // ========== INTERACTION BONUS ==========
        if ($criteria_count >= 3) {
            $interaction_bonus = 15;
            $reasons[] = "⚠️ Nhiều tiêu chí bất thường cùng lúc ($criteria_count/6)";
        }
        
        // ========== CALCULATE TOTAL RISK SCORE ==========
        $total_score = 
            ($score_performance * 0.30) +
            ($score_consistency * 0.25) +
            ($score_customer * 0.20) +
            ($score_trend * 0.15) +
            ($score_volatility * 0.10) +
            ($score_activity * 0.05);
        
        $total_score += $interaction_bonus;
        $risk_score = intval(min(100, max(0, $total_score)));
        
        // ========== XÁC ĐỊNH LEVEL ==========
        if ($risk_score >= 75) {
            $risk_level = 'critical';
        } elseif ($risk_score >= 50) {
            $risk_level = 'warning';
        } else {
            $risk_level = 'normal';
        }
        
        if (empty($reasons)) {
            $reasons[] = "Hoạt động bình thường";
        }
        
        $emp_kpi['risk_score'] = $risk_score;
        $emp_kpi['risk_level'] = $risk_level;
        $emp_kpi['risk_reasons'] = $reasons;
        $emp_kpi['criteria_high_count'] = $criteria_count;
        $emp_kpi['interaction_bonus'] = $interaction_bonus;
        $emp_kpi['score_breakdown'] = [
            'performance' => intval($score_performance),
            'consistency' => intval($score_consistency),
            'customer' => intval($score_customer),
            'trend' => intval($score_trend),
            'volatility' => intval($score_volatility),
            'activity' => intval($score_activity)
        ];
        
        return $emp_kpi;
    }
    
    /**
     * Helper: Tính Standard Deviation
     */
    private function calculateStdDev($arr) {
        if (count($arr) < 2) return 0;
        
        $avg = array_sum($arr) / count($arr);
        $deviations = array_map(fn($x) => pow($x - $avg, 2), $arr);
        $variance = array_sum($deviations) / count($deviations);
        
        return sqrt($variance);
    }
    
    /**
     * Helper: Tính Percentile
     */
    private function percentile($arr, $percentile) {
        if (empty($arr)) return 0;
        
        sort($arr);
        $n = count($arr);
        $rank = ($percentile / 100) * ($n - 1);
        
        if (is_int($rank)) {
            return $arr[$rank];
        }
        
        $lower = floor($rank);
        $upper = ceil($rank);
        $weight = $rank - $lower;
        
        return $arr[$lower] * (1 - $weight) + $arr[$upper] * $weight;
    }
}