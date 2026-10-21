<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ConsignmentDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Consignment Payables & Settlement Controller
 * 
 * Manages tracking of consignment liabilities and vendor payout settlements.
 */
class ConsignmentController {
    private ConsignmentDAO $consignmentDAO;

    public function __construct() {
        $this->consignmentDAO = new ConsignmentDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $action = $uriParts[1] ?? 'payables';

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewSuppliers');
                if ($action === 'summary') {
                    $this->getSummary();
                } else {
                    $this->getPayables();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageSuppliers');
                if ($action === 'settle-supplier') {
                    $this->settleBySupplier((int)$user['id']);
                } else {
                    $this->settlePayables((int)$user['id']);
                }
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getPayables(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 50;

        $filters = [
            'supplier_id' => isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null,
            'status'      => isset($_GET['status']) && $_GET['status'] !== '' ? Validator::sanitize($_GET['status']) : null,
            'start_date'  => isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null,
            'end_date'    => isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null,
            'search'      => isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null
        ];

        try {
            $result = $this->consignmentDAO->getPayables($filters, $page, $limit);
            Response::json([
                'success'    => true,
                'data'       => $result['data'],
                'pagination' => $result['pagination']
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying consignment payables: ' . $e->getMessage()], 500);
        }
    }

    public function getSummary(): void {
        try {
            $summary = $this->consignmentDAO->getSummary();
            Response::json([
                'success' => true,
                'data'    => $summary
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying consignment summary: ' . $e->getMessage()], 500);
        }
    }

    public function settlePayables(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        // Support both single payable_id and batch payable_ids array
        if (isset($data['payable_id'])) {
            $payableId = (int)$data['payable_id'];
            try {
                $settled = $this->consignmentDAO->settlePayable($payableId, $userId);
                if ($settled) {
                    Response::json(['success' => true, 'message' => "Payable #{$payableId} successfully marked as Paid."], 200);
                } else {
                    Response::json(['success' => false, 'message' => "Payable #{$payableId} is already paid or not found."], 404);
                }
            } catch (\Throwable $e) {
                Response::json(['success' => false, 'message' => 'Settlement failed: ' . $e->getMessage()], 500);
            }
            return;
        }

        if (!empty($data['payable_ids']) && is_array($data['payable_ids'])) {
            $ids = array_map('intval', $data['payable_ids']);
            try {
                $count = $this->consignmentDAO->settleBatch($ids, $userId);
                Response::json([
                    'success' => true,
                    'message' => "{$count} consignment payable(s) successfully marked as Paid.",
                    'settled_count' => $count
                ], 200);
            } catch (\Throwable $e) {
                Response::json(['success' => false, 'message' => 'Batch settlement failed: ' . $e->getMessage()], 500);
            }
            return;
        }

        Response::json(['success' => false, 'message' => 'Please provide payable_id or payable_ids array.'], 400);
    }

    public function settleBySupplier(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['supplier_id'])) {
            Response::json(['success' => false, 'message' => 'Supplier ID is required.'], 400);
        }

        $supplierId = (int)$data['supplier_id'];

        try {
            $count = $this->consignmentDAO->settleBySupplier($supplierId, $userId);
            Response::json([
                'success' => true,
                'message' => "Successfully settled all {$count} outstanding payable(s) for supplier.",
                'settled_count' => $count
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Supplier settlement failed: ' . $e->getMessage()], 500);
        }
    }
}
