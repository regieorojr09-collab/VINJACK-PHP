<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ReportDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Reporting & Analytics Controller
 * 
 * Exposes executive dashboard metrics, inventory valuation, top products, and daily sales reports.
 */
class ReportController {
    private ReportDAO $reportDAO;

    public function __construct() {
        $this->reportDAO = new ReportDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $action = $uriParts[1] ?? 'dashboard';

        if ($method !== 'GET') {
            Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
        }

        switch ($action) {
            case 'dashboard':
                RBACMiddleware::checkPermission($user, 'canViewDashboard');
                $this->getDashboard();
                break;

            case 'top-selling':
                RBACMiddleware::checkPermission($user, 'canViewReports');
                $this->getTopSelling();
                break;

            case 'inventory-valuation':
            case 'valuation':
                RBACMiddleware::checkPermission($user, 'canViewReports');
                $this->getValuation();
                break;

            case 'sales':
                RBACMiddleware::checkPermission($user, 'canViewReports');
                $this->getSalesReport();
                break;

            default:
                Response::json(['success' => false, 'message' => 'Invalid report endpoint specified.'], 404);
                break;
        }
    }

    public function getDashboard(): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;

        try {
            $data = $this->reportDAO->getDashboardOverview($startDate, $endDate);
            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying dashboard: ' . $e->getMessage()], 500);
        }
    }

    public function getTopSelling(): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 5;

        try {
            $data = $this->reportDAO->getTopSelling($startDate, $endDate, $limit);
            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying top selling: ' . $e->getMessage()], 500);
        }
    }

    public function getValuation(): void {
        try {
            $data = $this->reportDAO->getInventoryValuation();
            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying inventory valuation: ' . $e->getMessage()], 500);
        }
    }

    public function getSalesReport(): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $supplierId = isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

        try {
            $data = $this->reportDAO->getSalesReport($startDate, $endDate, $categoryId, $supplierId);
            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying sales report: ' . $e->getMessage()], 500);
        }
    }
}
