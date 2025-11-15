<?php
require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'controllers/UploadController.php';
require_once 'controllers/ReportController.php';
require_once 'models/EmployeeModel.php';
require_once 'models/OrderModel.php';

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
?>