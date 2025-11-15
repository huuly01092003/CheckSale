<?php
class EmployeeModel {
    private $pdo;

    public function __init__() {
        $this->pdo = Config::getPDO();
    }

    public function importFromExcel($data) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO nhanvien (ma_nv, ten_nv, ngay_vao_cong_ty, tinh, gs) VALUES (?, ?, ?, ?, ?)");
        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            if (empty($row[6])) continue;
            $ngay = $row[9] ? date('Y-m-d', ($row[9] - 25569) * 86400) : null;
            $stmt->execute([$row[6], $row[7], $ngay, $row[1], $row[3]]);
        }
    }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM nhanvien")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>