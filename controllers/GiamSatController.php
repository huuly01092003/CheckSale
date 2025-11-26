<?php
/**
 * FILE: CONTROLLERS/GIAMSATCONTROLLER.PHP (WITH PAGINATION)
 * ✅ Fix: Pagination để load hết 91k+ records
 * ✅ Fix: Time format hiển thị đúng
 */

class GiamSatController {
    private $giamsatModel;
    private $logger;

    public function __construct() {
        $this->giamsatModel = new GiamSatModel();
        $this->logger = new Logger();
    }

    /**
     * Hiển thị trang giám sát chính (với pagination)
     */
    public function showGiamSat() {
        $message = '';
        $type = '';
        $giamsat_data = [];
        $statistics = [];
        $filters = [];
        $chart_data = [];
        
        // ✅ PAGINATION
        $page = !empty($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = 500;  // Load 500 records/page
        $offset = ($page - 1) * $per_page;
        $total_pages = 0;
        $total_records = 0;

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

            // ========== LẤY DỮ LIỆU VỚI PAGINATION ==========
            $giamsat_data = $this->giamsatModel->searchPaginated($filters, $offset, $per_page);
            
            // ========== TÍNH TOÁN PAGINATION ==========
            $total_records = $this->giamsatModel->countFiltered($filters);
            $total_pages = ceil($total_records / $per_page);

            // ========== TÍNH TOÁN THỐNG KÊ ==========
            $statistics = $this->giamsatModel->getStatistics($tu_ngay, $den_ngay);
            $statistics['by_result'] = $this->giamsatModel->getResultStats($tu_ngay, $den_ngay);
            
            // ✅ TỔNG RECORDS từ DATABASE (không phải count($giamsat_data))
            $statistics['total_records'] = $total_records;

            // ========== LẤY DỮ LIỆU CHO BIỂU ĐỒ ==========
            $chart_data = $this->giamsatModel->getChartData($tu_ngay, $den_ngay);

            if (empty($giamsat_data) && $page == 1) {
                $message = "⚠️ Không có dữ liệu giám sát cho khoảng thời gian này.";
                $type = 'warning';
            }

            $this->logger->info("GiamSat displayed", [
                'page' => $page,
                'per_page' => $per_page,
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
}