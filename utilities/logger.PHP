<?php
/**
 * FILE 3: UTILITIES/LOGGER.PHP
 * Utility để ghi log chi tiết
 */

class Logger {
    private $log_file;
    private $log_dir;
    
    public function __construct() {
        $this->log_dir = Config::$log_dir;
        $this->log_file = $this->log_dir . '/app-' . date('Y-m-d') . '.log';
        
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
    }
    
    /**
     * Ghi log INFO
     */
    public function info($message, $data = null) {
        $this->write('[INFO]', $message, $data);
    }
    
    /**
     * Ghi log ERROR
     */
    public function error($message, $data = null) {
        $this->write('[ERROR]', $message, $data);
    }
    
    /**
     * Ghi log WARNING
     */
    public function warning($message, $data = null) {
        $this->write('[WARNING]', $message, $data);
    }
    
    /**
     * Ghi log DEBUG
     */
    public function debug($message, $data = null) {
        $this->write('[DEBUG]', $message, $data);
    }
    
    /**
     * Ghi log SUCCESS
     */
    public function success($message, $data = null) {
        $this->write('[SUCCESS]', $message, $data);
    }
    
    /**
     * Hàm ghi log nội bộ
     */
    private function write($level, $message, $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $memory = round(memory_get_usage() / 1024 / 1024, 2) . 'MB';
        
        $log_message = "[$timestamp] $level | MEM: $memory | Message: $message";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log_message .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $log_message .= " | Data: $data";
            }
        }
        
        $log_message .= "\n";
        
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }
    
    /**
     * Đọc log file gần đây nhất
     */
    public function readLatestLog($lines = 50) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $all_lines = file($this->log_file);
        return array_slice($all_lines, -$lines);
    }
    
    /**
     * Xóa log cũ (>30 ngày)
     */
    public function cleanOldLogs($days = 30) {
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $count = 0;
        
        if (is_dir($this->log_dir)) {
            foreach (glob($this->log_dir . '/app-*.log') as $file) {
                if (filemtime($file) < $cutoff_time) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }
}