<?php
/**
 * FILE: CONTROLLERS/GIAMSATCONTROLLER.PHP - FIX v11
 * ✅ FIX: Thống kê scale theo filter, hỗ trợ tìm kiếm thời gian
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
        $giamsat_data = [];
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
            'tinh_thanh' => ''
        ];
        $chart_data = [];
        $employee_list = [];
        $customer_list = [];
        $result_list = [];
        $province_list = [];
        
        $page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 500;
        $offset = ($page - 1) * $per_page;
        $total_pages = 0;
        $total_records = 0;

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

            $filters = [
                'tu_ngay' => $tu_ngay,
                'den_ngay' => $den_ngay,
                'ma_nhan_vien' => $ma_nhan_vien,
                'ket_qua' => $ket_qua,
                'tinh_thanh' => $tinh_thanh
            ];

            // ========== LẤY DỮ LIỆU BẢNG CHÍNH ==========
            $giamsat_data = $this->giamsatModel->searchPaginated($filters, $offset, $per_page);
            $total_records = $this->giamsatModel->countFiltered($filters);
            $total_pages = ceil($total_records / $per_page);

            // ✅ FIX: Thống kê SCALE theo filter
            $statistics = $this->giamsatModel->getStatistics($tu_ngay, $den_ngay);
            $statistics['by_result'] = $this->giamsatModel->getResultStats($tu_ngay, $den_ngay, $filters);
            $statistics['total_records'] = $total_records;

            // ✅ FIX: Biểu đồ SCALE theo filter
            $chart_data = $this->giamsatModel->getChartData($tu_ngay, $den_ngay, $filters);

            // ========== DANH SÁCH DROPDOWN (SCALE THEO FILTER) ==========
            $employee_list = $this->giamsatModel->getEmployeeListFiltered($tu_ngay, $den_ngay, $filters);
            $customer_list = $this->giamsatModel->getCustomerListFiltered($tu_ngay, $den_ngay, $filters);
            $result_list = $this->giamsatModel->getResultListFiltered($tu_ngay, $den_ngay);
            $province_list = $this->giamsatModel->getProvinceList();

            // ========== BẢNG TÌM KIẾM THỜI GIAN (ẢNH 2) ==========
            if (!empty($ma_nhan_vien)) {
                $employee_daily_data = $this->giamsatModel->getEmployeeDailyCallTimes($tu_ngay, $den_ngay, $ma_nhan_vien);
            }

            if (empty($giamsat_data) && $page == 1) {
                $message = "⚠️ Không có dữ liệu giám sát cho khoảng thời gian này.";
                $type = 'warning';
            }

            $this->logger->info("GiamSat displayed", [
                'page' => $page,
                'records_this_page' => count($giamsat_data),
                'total_records' => $total_records,
                'total_pages' => $total_pages
            ]);

        } catch (Exception $e) {
            $message = "❌ Lỗi: " . $e->getMessage();
            $type = 'danger';
            $this->logger->error("GiamSat error", ['error' => $e->getMessage()]);
        }

        include 'views/giamsat.view.php';
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