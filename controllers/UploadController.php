<?php
class UploadController {
    public function handleUpload() {
        $message = '';
        $type = '';
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
            $file = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            $size = $_FILES['file']['size'];
            
            if ($size > 60 * 1024 * 1024) {
                $message = "Tệp quá lớn (>200MB). Vui lòng upload file nhỏ hơn.";
                $type = 'warning';
            } else {
                try {
                    // Tăng thời gian thực thi
                    set_time_limit(600); // 10 phút
                    ini_set('memory_limit', '8192M');
                    
                    if (stripos($name, 'DSach_NV_Công') !== false || stripos($name, 'DSNV') !== false) {
                        $employeeModel = new EmployeeModel();
                        $employeeModel->importFromExcelChunked($file);
                        $message = "✓ Đã import danh sách nhân viên thành công!";
                        $type = 'success';
                    } elseif (stripos($name, '1.3') !== false || stripos($name, 'Báo cáo chi tiết đơn hàng') !== false) {
                        $orderModel = new OrderModel();
                        $orderModel->importFromExcelChunked($file);
                        $message = "✓ Đã import đơn hàng thành công!";
                        $type = 'success';
                    } else {
                        $message = "⚠ Tên file không hợp lệ. Vui lòng upload đúng file.";
                        $type = 'warning';
                    }
                } catch (Exception $e) {
                    $message = "✗ Lỗi: " . $e->getMessage();
                    $type = 'danger';
                }
            }
        }

        include 'views/upload.view.php';
    }
}
?>
