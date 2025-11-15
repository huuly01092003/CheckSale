<?php
class ReportController {
    public function showReport() {
        $orderModel = new OrderModel();
        $ky = $orderModel->getPeriodRange();
        $ky_start = $ky['min'];
        $ky_end = $ky['max'];

        $tu_ngay = $_GET['tu_ngay'] ?? '';
        $den_ngay = $_GET['den_ngay'] ?? $tu_ngay;
        if (!$tu_ngay) {
            $tu_ngay = date('Y-m-d', strtotime($ky_end . ' -2 days'));
            $den_ngay = $ky_end;
        }

        $total_lay = $orderModel->getTotalByPeriod($ky_start, $ky_end);
        $total_xem = $orderModel->getTotalByPeriod($tu_ngay, $den_ngay);

        $ket_qua_chung = $total_lay > 0 ? ($total_xem / $total_lay) : 0;
        $ty_le_nghi_van = $ket_qua_chung * 1.5;
        $so_ngay = (strtotime($den_ngay) - strtotime($tu_ngay)) / 86400 + 1;

        $employeeModel = new EmployeeModel();
        $employees = $employeeModel->getAll();

        $report = [];
        foreach ($employees as $emp) {
            $ds_tim_kiem = $orderModel->getByEmployeeAndPeriod($emp['ma_nv'], $ky_start, $ky_end);
            $ds_tien_do = $orderModel->getByEmployeeAndPeriod($emp['ma_nv'], $tu_ngay, $den_ngay);

            if ($ds_tien_do > 0) {
                $ty_le = $ds_tim_kiem > 0 ? ($ds_tien_do / $ds_tim_kiem) : 0;
                $report[] = array_merge($emp, ['ds_tim_kiem' => $ds_tim_kiem, 'ds_tien_do' => $ds_tien_do, 'ty_le' => $ty_le]);
            }
        }

        usort($report, function($a, $b) {
            return $b['ty_le'] <=> $a['ty_le'];
        });

        include 'views/report.view.php';
    }
}
?>