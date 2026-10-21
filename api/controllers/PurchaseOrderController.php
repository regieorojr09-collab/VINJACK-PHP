<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/PurchaseOrderDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Purchase Order Controller
 * 
 * Manages procurement orders, stock receipt verification, and XML export generation.
 */
class PurchaseOrderController {
    private PurchaseOrderDAO $poDAO;

    public function __construct() {
        $this->poDAO = new PurchaseOrderDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $subresource = $uriParts[2] ?? null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewSuppliers');
                if ($id !== null && ($subresource === 'export-xml' || $subresource === 'export')) {
                    $this->exportXml($id);
                } elseif ($id !== null) {
                    $this->getById($id);
                } else {
                    $this->getAll();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManagePurchaseOrders');
                if ($id !== null && $subresource === 'receive') {
                    $this->receiveItems($id, (int)$user['id']);
                } elseif ($id === null) {
                    $this->create((int)$user['id']);
                } else {
                    Response::json(['success' => false, 'message' => 'Invalid action.'], 400);
                }
                break;

            case 'PUT':
                RBACMiddleware::checkPermission($user, 'canManagePurchaseOrders');
                if ($id !== null && $subresource === 'status') {
                    $this->updateStatus($id, (string)($user['full_name'] ?? $user['username']));
                } else {
                    Response::json(['success' => false, 'message' => 'Invalid action.'], 400);
                }
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? Validator::sanitize($_GET['status']) : null;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null;

        $result = $this->poDAO->getPurchaseOrders($page, $limit, $status, $search);
        Response::json([
            'success'    => true,
            'data'       => $result['data'],
            'pagination' => $result['pagination']
        ], 200);
    }

    public function getById(int $id): void {
        $po = $this->poDAO->getPurchaseOrderById($id);
        if (!$po) {
            Response::json(['success' => false, 'message' => 'Purchase order not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $po
        ], 200);
    }

    public function create(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['supplier_id']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Supplier is required.'], 400);
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            Response::json(['success' => false, 'message' => 'At least one line item is required.'], 400);
        }

        try {
            $poId = $this->poDAO->createPurchaseOrder($data, $userId);
            $createdPO = $this->poDAO->getPurchaseOrderById($poId);

            Response::json([
                'success' => true,
                'message' => 'Purchase order created successfully.',
                'po_id'   => $poId,
                'data'    => $createdPO
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating PO: ' . $e->getMessage()], 500);
        }
    }

    public function receiveItems(int $poId, int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
            Response::json(['success' => false, 'message' => 'Please provide received items array.'], 400);
        }

        try {
            $updatedPO = $this->poDAO->receiveOrderItems($poId, $data['items'], $userId);

            Response::json([
                'success' => true,
                'message' => 'Stock delivery successfully committed to inventory.',
                'data'    => $updatedPO
            ], 200);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error receiving goods: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(int $poId, string $userName): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!isset($data['status'])) {
            Response::json(['success' => false, 'message' => 'Status field is required.'], 400);
        }

        $status = Validator::sanitize($data['status']);
        $notes = isset($data['notes']) ? Validator::sanitize($data['notes']) : "Status updated to {$status}";

        try {
            $this->poDAO->updateStatus($poId, $status, $notes, $userName);
            $po = $this->poDAO->getPurchaseOrderById($poId);

            Response::json([
                'success' => true,
                'message' => "Purchase order status updated to {$status}.",
                'data'    => $po
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()], 500);
        }
    }

    public function exportXml(int $id): void {
        try {
            $xmlString = $this->poDAO->getExportXml($id);
            Response::xml($xmlString);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'XML export failure: ' . $e->getMessage()], 500);
        }
    }
}
