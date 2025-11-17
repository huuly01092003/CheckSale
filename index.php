<?php
/**
 * FILE: INDEX.PHP (UPDATED)
 * Entry point - Routing chính (bổ sung KPI Report)
 */

// ========== LOAD DEPENDENCIES ==========
require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'utilities/logger.php';
require_once 'controllers/UploadController.php';
require_once 'controllers/ReportController.php';
require_once 'controllers/KPIReportController.php';
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
    
    case 'kpi_report':
        $controller = new KPIReportController();
        $controller->showKPIReport();
        break;
    
    case 'report':
    default:
        $controller = new ReportController();
        $controller->showReport();
        break;
}