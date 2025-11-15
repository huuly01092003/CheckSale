<?php
/**
 * FILE 8: INDEX.PHP
 * Entry point - Routing chính
 */

// ========== LOAD DEPENDENCIES ==========
require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'utilities/logger.php';
require_once 'controllers/UploadController.php';
require_once 'controllers/ReportController.php';
require_once 'models/EmployeeModel.php';
require_once 'models/OrderModel.php';

// ========== INITIALIZE ==========
Config::initialize();

// ========== ROUTING ==========
$action = $_GET['action'] ?? 'report';

switch ($action) {
    case 'upload':
        $controller = new UploadController();
        $controller->handleUpload();
        break;
    case 'report':
    default:
        $controller = new ReportController();
        $controller->showReport();
        break;
}