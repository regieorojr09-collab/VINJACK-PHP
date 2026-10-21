<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/CategoryDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Category Controller
 * 
 * Manages category CRUD with item count aggregation, soft validation, and RBAC enforcement.
 */
class CategoryController {
    private CategoryDAO $categoryDAO;

    public function __construct() {
        $this->categoryDAO = new CategoryDAO();
    }

    /**
     * Dispatcher method for RESTful routing.
     */
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
                    Response::json(['success' => false, 'message' => 'Category ID required for update.'], 400);
                }
                $this->update($id);
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Category ID required for deletion.'], 400);
                }
                $this->delete($id);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $categories = $this->categoryDAO->getAll();
        Response::json([
            'success' => true,
            'data'    => $categories
        ], 200);
    }

    public function getById(int $id): void {
        $category = $this->categoryDAO->getById($id);
        if (!$category) {
            Response::json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $category
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
            Response::json(['success' => false, 'message' => 'Category name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Category name must be at least 2 characters.'], 422);
        }

        if ($this->categoryDAO->nameExists($name)) {
            Response::json(['success' => false, 'message' => "Category '{$name}' already exists."], 409);
        }

        $insertData = [
            'name'        => $name,
            'description' => isset($data['description']) ? trim(Validator::sanitize($data['description'])) : null
        ];

        try {
            $newId = $this->categoryDAO->create($insertData);
            $created = $this->categoryDAO->getById($newId);

            Response::json([
                'success' => true,
                'message' => 'Category created successfully.',
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

        $existing = $this->categoryDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $missing = Validator::required($data, ['name']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Category name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Category name must be at least 2 characters.'], 422);
        }

        if ($this->categoryDAO->nameExists($name, $id)) {
            Response::json(['success' => false, 'message' => "Another category with name '{$name}' already exists."], 409);
        }

        $updateData = [
            'name'        => $name,
            'description' => isset($data['description']) ? trim(Validator::sanitize($data['description'])) : null
        ];

        try {
            $this->categoryDAO->update($id, $updateData);
            $updated = $this->categoryDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void {
        $existing = $this->categoryDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        if ((int)($existing['product_count'] ?? 0) > 0) {
            Response::json([
                'success' => false,
                'message' => "Cannot delete category '{$existing['name']}' because it is assigned to {$existing['product_count']} product(s)."
            ], 400);
        }

        try {
            $this->categoryDAO->delete($id);
            Response::json([
                'success' => true,
                'message' => 'Category deleted successfully.'
            ], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Cannot delete category due to active foreign key references.'], 400);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
