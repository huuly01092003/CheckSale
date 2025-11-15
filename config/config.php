<?php
/**
 * FILE 2: CONFIG/CONFIG.PHP
 * Cấu hình Database & Application Settings
 */

class Config {
    // ========== CẤU HÌNH DATABASE ==========
    private static $host = 'localhost';
    private static $dbname = 'hoalinhchecksale';
    private static $user = 'root';
    private static $pass = '';
    private static $charset = 'utf8mb4';
    
    // ========== CẤU HÌNH APPLICATION ==========
    public static $timezone = 'Asia/Ho_Chi_Minh';
    public static $date_format = 'Y-m-d';
    public static $currency = '₫';
    
    // ========== CẤU HÌNH UPLOAD ==========
    public static $max_upload_size = 500; // MB
    public static $allowed_extensions = ['csv', 'xlsx', 'xls'];
    public static $upload_dir = __DIR__ . '/../uploads';
    
    // ========== CẤU HÌNH PERFORMANCE ==========
    public static $batch_size = 1000; // rows
    public static $cache_ttl = 3600; // seconds
    public static $log_dir = __DIR__ . '/../logs';
    
    /**
     * Khởi tạo cấu hình toàn cục
     */
    public static function initialize() {
        // Set timezone
        date_default_timezone_set(self::$timezone);
        
        // Set error handling
        set_time_limit(1800);              // 30 phút
        ini_set('memory_limit', '1024M');   // 256MB
        ini_set('max_execution_time', 1800);
        ini_set('upload_max_filesize', self::$max_upload_size . 'M');
        ini_set('post_max_size', self::$max_upload_size . 'M');
        
        // Create directories
        self::ensureDirectoriesExist();
        
        // Error logging
        ini_set('log_errors', '1');
        ini_set('error_log', self::$log_dir . '/php-errors.log');
    }
    
    /**
     * Tạo kết nối PDO
     */
    public static function getPDO() {
        try {
            $dsn = "mysql:host=" . self::$host . 
                   ";dbname=" . self::$dbname . 
                   ";charset=" . self::$charset;
            
            $pdo = new PDO($dsn, self::$user, self::$pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$charset,
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            die("❌ Lỗi kết nối DB: " . $e->getMessage());
        }
    }
    
    /**
     * Đảm bảo các thư mục tồn tại
     */
    private static function ensureDirectoriesExist() {
        $dirs = [self::$upload_dir, self::$log_dir];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Lấy URL base
     */
    public static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . $path;
    }
}