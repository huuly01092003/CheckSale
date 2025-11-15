<?php
class UploadController {
    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
            $file = $_FILES['file']['tmp_name'];
            $name = $_FILES['file']['name'];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);            $data = $spreadsheet->getActiveSheet()->toArray();

            if (stripos($name, 'DSach_NV_Công') !== false || stripos($name, 'DSNV') !== false) {
                $employeeModel = new EmployeeModel();
                $employeeModel->importFromExcel($data);
                echo "<div class='alert alert-success'>Đã import DS Nhân viên</div>";
            }

            if (stripos($name, '1.3') !== false || stripos($name, 'Báo cáo chi tiết đơn hàng') !== false) {
                $orderModel = new OrderModel();
                $orderModel->importFromExcel($data);
                echo "<div class='alert alert-success'>Đã import Đơn hàng</div>";
            }
        }

        include 'views/upload.view.php';
    }
}
?>