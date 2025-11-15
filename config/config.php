<?php
class Config {
    public static function getPDO() {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=doanhso_nghi_ngo;charset=utf8mb4', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die("Lỗi kết nối DB: " . $e->getMessage());
        }
    }
}
?>