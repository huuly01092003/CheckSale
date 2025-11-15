<?php
/**
 * FILE: CONTROLLERS/VALIDATIONCONTROLLER.PHP
 * Xử lý validation dữ liệu input
 */

class ValidationController {
    private $logger;
    private $errors = [];
    private $minYear = 2020;
    private $maxYear;
    private $maxDateRange = 90; // days

    public function __construct() {
        $this->logger = new Logger();
        $this->maxYear = date('Y');
    }

    /**
     * Validate ngày đơn lẻ
     */
    public function validateDate($dateStr, $fieldName = 'Ngày') {
        $this->errors = [];

        if (empty($dateStr)) {
            $this->errors[] = "$fieldName không được để trống";
            return false;
        }

        try {
            $date = new DateTime($dateStr);
            $year = (int)$date->format('Y');

            if ($year < $this->minYear) {
                $this->errors[] = "$fieldName phải >= năm {$this->minYear}";
                return false;
            }

            if ($year > $this->maxYear) {
                $this->errors[] = "$fieldName phải <= năm {$this->maxYear}";
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "$fieldName không đúng định dạng (YYYY-MM-DD)";
            return false;
        }
    }

    /**
     * Validate khoảng ngày
     */
    public function validateDateRange($tuNgay, $denNgay) {
        $this->errors = [];

        // Validate từng ngày
        if (!$this->validateDate($tuNgay, 'Từ ngày')) {
            return false;
        }

        if (!$this->validateDate($denNgay, 'Đến ngày')) {
            return false;
        }

        try {
            $from = new DateTime($tuNgay);
            $to = new DateTime($denNgay);

            // So sánh
            if ($from > $to) {
                $this->errors[] = 'Từ ngày không được lớn hơn Đến ngày';
                return false;
            }

            // Giới hạn khoảng
            $diff = $to->diff($from)->days;
            if ($diff > $this->maxDateRange) {
                $this->errors[] = "Khoảng thời gian không vượt quá {$this->maxDateRange} ngày (hiện tại: {$diff} ngày)";
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = 'Lỗi so sánh khoảng ngày';
            return false;
        }
    }

    /**
     * Validate tháng
     */
    public function validateMonth($month) {
        $this->errors = [];

        if (empty($month)) {
            $this->errors[] = 'Tháng không được để trống';
            return false;
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->errors[] = 'Tháng không đúng định dạng (YYYY-MM)';
            return false;
        }

        $parts = explode('-', $month);
        $year = (int)$parts[0];
        $monthNum = (int)$parts[1];

        if ($year < $this->minYear || $year > $this->maxYear) {
            $this->errors[] = "Năm phải nằm trong khoảng {$this->minYear}-{$this->maxYear}";
            return false;
        }

        if ($monthNum < 1 || $monthNum > 12) {
            $this->errors[] = 'Tháng phải từ 01-12';
            return false;
        }

        return true;
    }

    /**
     * Validate filter report
     */
    public function validateReportFilter($thang, $tuNgay, $denNgay) {
        $this->errors = [];

        // Validate tháng
        if (!$this->validateMonth($thang)) {
            return false;
        }

        // Validate khoảng ngày
        if (!$this->validateDateRange($tuNgay, $denNgay)) {
            return false;
        }

        // Đảm bảo khoảng ngày nằm trong tháng
        try {
            $monthStart = $thang . '-01';
            $monthObj = new DateTime($monthStart);
            $monthEnd = $monthObj->format('Y-m-t');

            $from = new DateTime($tuNgay);
            $to = new DateTime($denNgay);
            $mStart = new DateTime($monthStart);
            $mEnd = new DateTime($monthEnd);

            if ($from < $mStart) {
                $this->errors[] = 'Từ ngày không được trước ngày đầu tháng';
                return false;
            }

            if ($to > $mEnd) {
                $this->errors[] = 'Đến ngày không được vượt quá ngày cuối tháng';
                return false;
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = 'Lỗi validate khoảng ngày trong tháng';
            return false;
        }
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload($file) {
        $this->errors = [];

        if (!isset($file['tmp_name']) || !isset($file['name'])) {
            $this->errors[] = 'Không có file được chọn';
            return false;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = 'File không hợp lệ';
            return false;
        }

        $size = $file['size'];
        $maxSize = Config::$max_upload_size * 1024 * 1024;

        if ($size > $maxSize) {
            $this->errors[] = 'File quá lớn (tối đa: ' . Config::$max_upload_size . 'MB)';
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, Config::$allowed_extensions)) {
            $this->errors[] = 'Định dạng không hỗ trợ. Chỉ chấp nhận: ' . implode(', ', Config::$allowed_extensions);
            return false;
        }

        return true;
    }

    /**
     * Xử lý dữ liệu ngày (nullable)
     * Giải pháp cho các dòng dữ liệu có ngày trống
     */
    public function sanitizeDate($dateStr, $fallbackDate = null) {
        if (empty($dateStr)) {
            return $fallbackDate ?? date('Y-m-d');
        }

        try {
            // Thử parse định dạng Excel số
            if (is_numeric($dateStr)) {
                $date = date('Y-m-d', ($dateStr - 25569) * 86400);
                if ($this->validateDate($date)) {
                    return $date;
                }
            }

            // Thử parse định dạng ngày
            $dateObj = DateTime::createFromFormat('d/m/Y', trim($dateStr));
            if ($dateObj) {
                $date = $dateObj->format('Y-m-d');
                if ($this->validateDate($date)) {
                    return $date;
                }
            }

            // Fallback: sử dụng ngày hiện tại
            return $fallbackDate ?? date('Y-m-d');
        } catch (Exception $e) {
            return $fallbackDate ?? date('Y-m-d');
        }
    }

    /**
     * Xử lý dữ liệu tiền (nullable)
     */
    public function sanitizeMoney($value, $fallbackValue = 0) {
        if (empty($value)) {
            return $fallbackValue;
        }

        try {
            // Nếu đã là số
            if (is_numeric($value)) {
                $money = (float)$value;
            } else {
                // Bỏ ký tự không phải số, dấu thập phân, dấu âm
                $money = (float)preg_replace('/[^\d.-]/', '', (string)$value);
            }

            // Validate: phải >= 0 và < 1 tỷ
            if ($money >= 0 && $money < 1000000000) {
                return $money;
            }

            return $fallbackValue;
        } catch (Exception $e) {
            return $fallbackValue;
        }
    }

    /**
     * Xử lý dữ liệu mã code (nullable)
     */
    public function sanitizeCode($value) {
        if (empty($value)) {
            return '';
        }

        return trim(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$value));
    }

    /**
     * Xử lý dữ liệu text (nullable)
     */
    public function sanitizeText($value, $maxLength = 255) {
        if (empty($value)) {
            return '';
        }

        $text = trim((string)$value);
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return substr($text, 0, $maxLength);
    }

    /**
     * Lấy danh sách lỗi
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Lấy lỗi đầu tiên
     */
    public function getFirstError() {
        return isset($this->errors[0]) ? $this->errors[0] : '';
    }

    /**
     * Có lỗi không
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Xóa lỗi
     */
    public function clearErrors() {
        $this->errors = [];
    }
}