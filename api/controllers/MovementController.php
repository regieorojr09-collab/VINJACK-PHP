<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/MovementDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Stock Movement & Inventory Ledger Controller
 * 
 * Manages query access to immutable stock movements and physical count adjustments.
 */
class MovementController {
    private MovementDAO $movementDAO;

    public function __construct() {
        $this->movementDAO = new MovementDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $action = $uriParts[1] ?? null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewInventory');
                if ($action === 'metrics') {
                    $this->getMetrics();
                } else {
                    $this->getAll();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                $this->adjustStock((int)$user['id']);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;

        $filters = [
            'product_id' => isset($_GET['product_id']) && is_numeric($_GET['product_id']) ? (int)$_GET['product_id'] : null,
            'type'       => isset($_GET['type']) && $_GET['type'] !== '' ? Validator::sanitize($_GET['type']) : null,
            'start_date' => isset($_GET['start_date']) && $_GET['start_date'] !== '' ? Validator::sanitize($_GET['start_date']) : null,
            'end_date'   => isset($_GET['end_date']) && $_GET['end_date'] !== '' ? Validator::sanitize($_GET['end_date']) : null,
            'search'     => isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null
        ];

        try {
            $result = $this->movementDAO->getMovements($filters, $page, $limit);
            Response::json([
                'success'    => true,
                'data'       => $result['data'],
                'pagination' => $result['pagination']
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying ledger: ' . $e->getMessage()], 500);
        }
    }

    public function getMetrics(): void {
        try {
            $metrics = $this->movementDAO->getMetrics();
            Response::json([
                'success' => true,
                'data'    => $metrics
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying metrics: ' . $e->getMessage()], 500);
        }
    }

    public function adjustStock(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['product_id', 'new_quantity']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Product and new quantity are required.'], 400);
        }

        $productId = (int)$data['product_id'];
        $newQuantity = (int)$data['new_quantity'];
        $notes = isset($data['notes']) ? Validator::sanitize($data['notes']) : '';

        try {
            $adjustment = $this->movementDAO->createAdjustment($productId, $newQuantity, $notes, $userId);
            Response::json([
                'success' => true,
                'message' => 'Inventory adjustment committed to ledger successfully.',
                'data'    => $adjustment
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Adjustment failed: ' . $e->getMessage()], 500);
        }
    }
}
