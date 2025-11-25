<?php
/**
 * FILE: INDEX.PHP
 * Entry point - Routing chính (MVC Pattern)
 */

// ========== LOAD DEPENDENCIES ==========
require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'utilities/logger.php';

// ========== LOAD MODELS ==========
require_once 'models/EmployeeModel.php';
require_once 'models/OrderModel.php';
require_once 'models/GiamSatModel.php';

// ========== LOAD CONTROLLERS ==========
require_once 'controllers/UploadController.php';
require_once 'controllers/ReportController.php';
require_once 'controllers/KPIReportController.php';
require_once 'controllers/GiamSatController.php';

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

    case 'giamsat':
        $controller = new GiamSatController();
        $controller->showGiamSat();
        break;

    case 'giamsat_upload':
        $controller = new GiamSatController();
        $controller->handleUpload();
        break;

    case 'report':
    default:
        $controller = new ReportController();
        $controller->showReport();
        break;
}