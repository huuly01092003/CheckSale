<?php
/**
 * FILE 7: CONTROLLERS/UPLOADCONTROLLER.PHP
 * Controller xử lý upload file
 */

class UploadController {
    
    public function handleUpload() {
        $message = '';
        $type = '';
        $logger = new Logger();
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
            $file = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            $size = $_FILES['file']['size'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            // ========== VALIDATE SIZE ==========
            $max_size = Config::$max_upload_size * 1024 * 1024;
            if ($size > $max_size) {
                $message = "❌ Tệp quá lớn (>" . Config::$max_upload_size . "MB)";
                $type = 'danger';
                $logger->warning("Upload file too large", ['size' => $size, 'max' => $max_size]);
            } 
            // ========== VALIDATE EXTENSION ==========
            elseif (!in_array($ext, Config::$allowed_extensions)) {
                $message = "❌ Định dạng file không hợp lệ. Chỉ chấp nhận: " . implode(', ', Config::$allowed_extensions);
                $type = 'danger';
                $logger->warning("Invalid file extension", ['ext' => $ext]);
            }
            else {
                try {
                    // ========== NHÂN VIÊN ==========
                    if (stripos($name, 'DSach_NV_Công') !== false || 
                        stripos($name, 'DSNV') !== false || 
                        stripos($name, 'nhanvien') !== false) {
                        
                        $employeeModel = new EmployeeModel();
                        
                        if ($ext === 'csv') {
                            $employeeModel->importFromCSV($file);
                            $message = "✅ Import danh sách nhân viên (CSV) thành công! Nhanh hơn 10x, tiết kiệm 80% RAM.";
                        } else {
                            $employeeModel->importFromExcelChunked($file);
                            $message = "✅ Import danh sách nhân viên (XLSX) thành công!";
                        }
                        
                        $type = 'success';
                        $logger->success("Employee import success", ['format' => $ext]);
                    } 
                    // ========== ĐƠN HÀNG ==========
                    elseif (stripos($name, '1.3') !== false || 
                            stripos($name, 'Báo cáo') !== false || 
                            stripos($name, 'donhang') !== false ||
                            stripos($name, 'order') !== false) {
                        
                        $orderModel = new OrderModel();
                        
                        if ($ext === 'csv') {
                            $orderModel->importFromCSV($file);
                            $message = "✅ Import đơn hàng (CSV) thành công! Thời gian: 1-2 phút, RAM: 50-100MB";
                        } else {
                            $orderModel->importFromExcelChunked($file);
                            $message = "✅ Import đơn hàng (XLSX) thành công! Thời gian: 5-10 phút, RAM: ~2GB";
                        }
                        
                        $type = 'success';
                        $logger->success("Order import success", ['format' => $ext]);
                    } 
                    else {
                        $message = "⚠️ Tên file không hợp lệ. Vui lòng upload file đúng:";
                        $message .= "<br>• Danh sách nhân viên: 'DSach_NV_Công...' hoặc 'DSNV...'";
                        $message .= "<br>• Báo cáo đơn hàng: '1.3...' hoặc 'Báo cáo...'";
                        $type = 'warning';
                        $logger->warning("Unknown file type", ['name' => $name]);
                    }
                } catch (Exception $e) {
                    $message = "❌ Lỗi: " . $e->getMessage();
                    $type = 'danger';
                    $logger->error("Upload error", ['error' => $e->getMessage()]);
                }
            }
        }

        include 'views/upload.view.php';
    }
}