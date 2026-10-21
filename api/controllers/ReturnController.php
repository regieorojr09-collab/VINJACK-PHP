<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ReturnDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Returns & Warranty Claims Controller
 * 
 * Manages customer returns, restock workflows, refunds, and supplier warranty shipments.
 */
class ReturnController {
    private ReturnDAO $returnDAO;

    public function __construct() {
        $this->returnDAO = new ReturnDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $action = $uriParts[1] ?? 'customer';
        $subId = isset($uriParts[2]) && is_numeric($uriParts[2]) ? (int)$uriParts[2] : null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canManageReturns');
                if ($action === 'supplier') {
                    $this->getSupplierReturns();
                } elseif ($action === 'sale' && $subId !== null) {
                    $this->getSaleForReturn($subId);
                } elseif ($id !== null) {
                    $this->getSaleForReturn($id);
                } else {
                    $this->getCustomerReturns();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageReturns');
                if ($action === 'supplier') {
                    $this->createSupplierReturn((int)$user['id']);
                } else {
                    $this->createCustomerReturn((int)$user['id']);
                }
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getCustomerReturns(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null;

        try {
            $result = $this->returnDAO->getCustomerReturns($page, $limit, $search);
            Response::json([
                'success'    => true,
                'data'       => $result['data'],
                'pagination' => $result['pagination']
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying returns: ' . $e->getMessage()], 500);
        }
    }

    public function getSupplierReturns(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null;

        try {
            $result = $this->returnDAO->getSupplierReturns($page, $limit, $search);
            Response::json([
                'success'    => true,
                'data'       => $result['data'],
                'pagination' => $result['pagination']
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying supplier returns: ' . $e->getMessage()], 500);
        }
    }

    public function getSaleForReturn(int $saleId): void {
        try {
            $sale = $this->returnDAO->getSaleForReturn($saleId);
            if (!$sale) {
                Response::json(['success' => false, 'message' => 'Sale ticket not found.'], 404);
            }

            Response::json([
                'success' => true,
                'data'    => $sale
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error loading sale: ' . $e->getMessage()], 500);
        }
    }

    public function createCustomerReturn(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['sale_id', 'outcome', 'reason']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Sale ID, outcome, and reason are required.'], 400);
        }

        $saleId = (int)$data['sale_id'];
        $items = $data['items'] ?? [];
        $services = $data['services'] ?? [];
        $reason = Validator::sanitize($data['reason']);
        $outcome = Validator::sanitize($data['outcome']);

        try {
            $result = $this->returnDAO->processCustomerReturn($saleId, $items, $services, $reason, $outcome, $userId);
            Response::json([
                'success' => true,
                'message' => 'Customer return processed successfully.',
                'data'    => $result
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()], 500);
        }
    }

    public function createSupplierReturn(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['supplier_id', 'items']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Supplier ID and items list are required.'], 400);
        }

        $supplierId = (int)$data['supplier_id'];
        $items = (array)$data['items'];
        $notes = isset($data['notes']) ? Validator::sanitize($data['notes']) : '';

        try {
            $result = $this->returnDAO->processSupplierReturn($supplierId, $items, $notes, $userId);
            Response::json([
                'success' => true,
                'message' => 'Supplier return shipment committed successfully.',
                'data'    => $result
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error processing supplier return: ' . $e->getMessage()], 500);
        }
    }
}
