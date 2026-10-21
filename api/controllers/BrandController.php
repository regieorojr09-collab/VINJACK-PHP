<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/BrandDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Brand Controller
 * 
 * Manages brand CRUD with item count aggregation, soft validation, and RBAC enforcement.
 */
class BrandController {
    private BrandDAO $brandDAO;

    public function __construct() {
        $this->brandDAO = new BrandDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewInventory');
                if ($id !== null) {
                    $this->getById($id);
                } else {
                    $this->getAll();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                $this->create();
                break;

            case 'PUT':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Brand ID required for update.'], 400);
                }
                $this->update($id);
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Brand ID required for deletion.'], 400);
                }
                $this->delete($id);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $brands = $this->brandDAO->getAll();
        Response::json([
            'success' => true,
            'data'    => $brands
        ], 200);
    }

    public function getById(int $id): void {
        $brand = $this->brandDAO->getById($id);
        if (!$brand) {
            Response::json(['success' => false, 'message' => 'Brand not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $brand
        ], 200);
    }

    public function create(): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['name']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Brand name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Brand name must be at least 2 characters.'], 422);
        }

        if ($this->brandDAO->nameExists($name)) {
            Response::json(['success' => false, 'message' => "Brand '{$name}' already exists."], 409);
        }

        try {
            $newId = $this->brandDAO->create(['name' => $name]);
            $created = $this->brandDAO->getById($newId);

            Response::json([
                'success' => true,
                'message' => 'Brand created successfully.',
                'data'    => $created
            ], 201);
        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function update(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $existing = $this->brandDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Brand not found.'], 404);
        }

        $missing = Validator::required($data, ['name']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Brand name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Brand name must be at least 2 characters.'], 422);
        }

        if ($this->brandDAO->nameExists($name, $id)) {
            Response::json(['success' => false, 'message' => "Another brand with name '{$name}' already exists."], 409);
        }

        try {
            $this->brandDAO->update($id, ['name' => $name]);
            $updated = $this->brandDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Brand updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void {
        $existing = $this->brandDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Brand not found.'], 404);
        }

        if ((int)($existing['product_count'] ?? 0) > 0) {
            Response::json([
                'success' => false,
                'message' => "Cannot delete brand '{$existing['name']}' because it is assigned to {$existing['product_count']} product(s)."
            ], 400);
        }

        try {
            $this->brandDAO->delete($id);
            Response::json([
                'success' => true,
                'message' => 'Brand deleted successfully.'
            ], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Cannot delete brand due to active foreign key references.'], 400);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
