<?php
class OrderModel {
    private $pdo;

    public function __init__() {
        $this->pdo = Config::getPDO();
    }

    public function importFromExcel($data) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO donhang (ma_nv, ngay_tao_don, thanh_tien) VALUES (?, ?, ?)");
        for ($i = 6; $i < count($data); $i++) {
            $row = $data[$i];
            if (empty($row[3])) continue;
            $ngay = date('Y-m-d', strtotime($row[17]));
            $tien = (float)preg_replace('/[^0-9.-]/', '', $row[22]);
            $stmt->execute([$row[3], $ngay, $tien]);
        }
    }

    public function getTotalByPeriod($start, $end) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(thanh_tien), 0) FROM donhang WHERE ngay_tao_don BETWEEN ? AND ?");
        $stmt->execute([$start, $end]);
        return $stmt->fetchColumn();
    }

    public function getByEmployeeAndPeriod($ma_nv, $start, $end) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(thanh_tien), 0) FROM donhang WHERE ma_nv = ? AND ngay_tao_don BETWEEN ? AND ?");
        $stmt->execute([$ma_nv, $start, $end]);
        return $stmt->fetchColumn();
    }

    public function getPeriodRange() {
        return $this->pdo->query("SELECT MIN(ngay_tao_don) AS min, MAX(ngay_tao_don) AS max FROM donhang")->fetch(PDO::FETCH_ASSOC);
    }
}
?>