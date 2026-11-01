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
        $role = $user['role'] ?? '';
        $isSuperAdmin = $role === 'Super Admin';
        $canViewReportsRole = in_array($role, ['Super Admin', 'Admin'], true);

        if ($method !== 'GET') {
            if (!$canViewReportsRole) {
                Response::json([
                    'success' => false,
                    'message' => '403 Forbidden: Access Denied.'
                ], 403);
                return;
            }
            Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
            return;
        }

        switch ($action) {
            case 'dashboard':
            case 'top-selling':
                // Dashboard metrics permitted for Super Admin and Admin (Mechanic); blocked for Salesperson (Clerk)
                if (!$canViewReportsRole) {
                    Response::json([
                        'success' => false,
                        'message' => '403 Forbidden: Access Denied to Dashboard.'
                    ], 403);
                    return;
                }
                if ($action === 'dashboard') {
                    $this->getDashboardOverview($user);
                } else {
                    $this->getTopSelling($user);
                }
                break;

            case 'inventory-valuation':
            case 'valuation':
            case 'sales':
            case 'revenue-trend':
                // Standalone Reports permitted for Super Admin and Admin (Mechanic); blocked for Salesperson (Clerk)
                if (!$canViewReportsRole) {
                    Response::json([
                        'success' => false,
                        'message' => '403 Forbidden: Access Denied to Reports.'
                    ], 403);
                    return;
                }
                if ($action === 'inventory-valuation' || $action === 'valuation') {
                    $this->getValuation();
                } else {
                    $this->getSalesReport($user, $action === 'revenue-trend');
                }
                break;

            default:
                if (!$canViewReportsRole) {
                    Response::json([
                        'success' => false,
                        'message' => '403 Forbidden: Access Denied.'
                    ], 403);
                    return;
                }
                Response::json(['success' => false, 'message' => 'Invalid report endpoint specified.'], 404);
                break;
        }
    }

    private function hasReportAccess(?array $user): bool {
        if ($user === null) {
            return false;
        }
        return in_array($user['role'] ?? '', ['Super Admin', 'Admin'], true);
    }

    public function getDashboardOverview(?array $user = null): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;

        try {
            $data = $this->reportDAO->getDashboardOverview($startDate, $endDate);

            // Redact sensitive COGS & net profit figures if user lacks higher-level canViewReports
            if (!$this->hasReportAccess($user)) {
                unset($data['total_cogs'], $data['gross_profit'], $data['profit_margin_percent']);
            }

            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying dashboard: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Alias for getDashboardOverview
     */
    public function getDashboard(): void {
        $this->getDashboardOverview();
    }

    public function getTopSelling(?array $user = null): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 5;

        try {
            $data = $this->reportDAO->getTopSelling($startDate, $endDate, $limit);

            // Redact per-product COGS & gross profit if user lacks higher-level canViewReports
            if (!$this->hasReportAccess($user) && !empty($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as &$prod) {
                    unset($prod['total_cogs'], $prod['gross_profit']);
                }
                unset($prod);
            }

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

    public function getSalesReport(?array $user = null, bool $trendOnly = false): void {
        $startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null;
        $endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null;
        $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $supplierId = isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

        try {
            $data = $this->reportDAO->getSalesReport($startDate, $endDate, $categoryId, $supplierId);

            // Expose only chart-safe date & gross revenue totals when user lacks higher-tier canViewReports
            if ($trendOnly || !$this->hasReportAccess($user)) {
                $chartRecords = array_map(static fn(array $row): array => [
                    'sale_date'     => (string)($row['sale_date'] ?? ''),
                    'gross_revenue' => (float)($row['gross_revenue'] ?? 0.0),
                ], $data['records'] ?? []);

                Response::json([
                    'success' => true,
                    'data'    => [
                        'records' => $chartRecords
                    ]
                ], 200);
            }

            Response::json([
                'success' => true,
                'data'    => $data
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying sales report: ' . $e->getMessage()], 500);
        }
    }
}
