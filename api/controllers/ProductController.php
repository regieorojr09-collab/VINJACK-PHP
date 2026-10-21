<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ProductDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Product Controller
 * 
 * Exposes REST endpoints for the product catalog, inventory monitoring,
 * and supplier cost matrices with strict RBAC enforcement.
 */
class ProductController {
    private ProductDAO $productDAO;

    public function __construct() {
        $this->productDAO = new ProductDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewInventory');
                if ($id !== null) {
                    $this->getProduct($id);
                } else {
                    $this->getProducts();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                $this->createProduct();
                break;

            case 'PUT':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Product ID required for update.'], 400);
                }
                $this->updateProduct($id);
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Product ID required for deletion.'], 400);
                }
                $this->deleteProduct($id);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getProducts(): void {
        $filters = [
            'search'       => isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null,
            'category_id'  => isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null,
            'brand_id'     => isset($_GET['brand_id']) && is_numeric($_GET['brand_id']) ? (int)$_GET['brand_id'] : null,
            'stock_status' => isset($_GET['stock_status']) && $_GET['stock_status'] !== '' ? Validator::sanitize($_GET['stock_status']) : null,
            'status'       => isset($_GET['status']) && $_GET['status'] !== '' ? Validator::sanitize($_GET['status']) : null
        ];

        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;

        $results = $this->productDAO->getFiltered($filters, $page, $limit);
        Response::json([
            'success'    => true,
            'data'       => $results['data'],
            'pagination' => $results['pagination']
        ], 200);
    }

    public function getProduct(int $id): void {
        $product = $this->productDAO->getProductById($id);
        if (!$product) {
            Response::json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $product
        ], 200);
    }

    public function createProduct(): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $required = ['item_code', 'name', 'category_id', 'brand_id', 'price'];
        $missing = Validator::required($data, $required);
        if (!empty($missing)) {
            Response::json([
                'success' => false, 
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], 400);
        }

        $itemCode = strtoupper(trim(Validator::sanitize($data['item_code'])));
        if ($this->productDAO->itemCodeExists($itemCode)) {
            Response::json([
                'success' => false, 
                'message' => "Item code '{$itemCode}' is already registered."
            ], 409);
        }

        $price = (float)$data['price'];
        if ($price < 0) {
            Response::json(['success' => false, 'message' => 'Price cannot be negative.'], 422);
        }

        $payload = [
            'item_code'       => $itemCode,
            'name'            => trim(Validator::sanitize($data['name'])),
            'category_id'     => (int)$data['category_id'],
            'brand_id'        => (int)$data['brand_id'],
            'price'           => $price,
            'default_cost'    => isset($data['default_cost']) ? (float)$data['default_cost'] : 0.00,
            'quantity'        => isset($data['quantity']) ? (int)$data['quantity'] : 0,
            'consigned_stock' => isset($data['consigned_stock']) ? (int)$data['consigned_stock'] : 0,
            'max_stock'       => isset($data['max_stock']) ? (int)$data['max_stock'] : 100,
            'unit'            => isset($data['unit']) ? Validator::sanitize($data['unit']) : 'pc',
            'status'          => isset($data['status']) ? Validator::sanitize($data['status']) : 'active',
            'image'           => isset($data['image']) ? Validator::sanitize($data['image']) : null,
            'is_serialized'   => !empty($data['is_serialized']),
            'supplier_costs'  => $data['supplier_costs'] ?? []
        ];

        try {
            $newId = $this->productDAO->createProduct($payload);
            $created = $this->productDAO->getProductById($newId);

            Response::json([
                'success' => true,
                'message' => 'Product catalog item created successfully.',
                'data'    => $created
            ], 201);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Referenced category or brand does not exist.'], 422);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $existing = $this->productDAO->getProductById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $required = ['item_code', 'name', 'category_id', 'brand_id', 'price'];
        $missing = Validator::required($data, $required);
        if (!empty($missing)) {
            Response::json([
                'success' => false, 
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], 400);
        }

        $itemCode = strtoupper(trim(Validator::sanitize($data['item_code'])));
        if ($this->productDAO->itemCodeExists($itemCode, $id)) {
            Response::json([
                'success' => false, 
                'message' => "Another product is already using item code '{$itemCode}'."
            ], 409);
        }

        $price = (float)$data['price'];
        if ($price < 0) {
            Response::json(['success' => false, 'message' => 'Price cannot be negative.'], 422);
        }

        $payload = [
            'item_code'       => $itemCode,
            'name'            => trim(Validator::sanitize($data['name'])),
            'category_id'     => (int)$data['category_id'],
            'brand_id'        => (int)$data['brand_id'],
            'price'           => $price,
            'default_cost'    => isset($data['default_cost']) ? (float)$data['default_cost'] : (float)$existing['default_cost'],
            'quantity'        => isset($data['quantity']) ? (int)$data['quantity'] : (int)$existing['quantity'],
            'consigned_stock' => isset($data['consigned_stock']) ? (int)$data['consigned_stock'] : (int)$existing['consigned_stock'],
            'max_stock'       => isset($data['max_stock']) ? (int)$data['max_stock'] : (int)$existing['max_stock'],
            'unit'            => isset($data['unit']) ? Validator::sanitize($data['unit']) : $existing['unit'],
            'status'          => isset($data['status']) ? Validator::sanitize($data['status']) : $existing['status'],
            'image'           => isset($data['image']) ? Validator::sanitize($data['image']) : $existing['image'],
            'is_serialized'   => isset($data['is_serialized']) ? (!empty($data['is_serialized']) ? 1 : 0) : (int)$existing['is_serialized'],
            'supplier_costs'  => $data['supplier_costs'] ?? []
        ];

        try {
            $this->productDAO->updateProduct($id, $payload);
            $updated = $this->productDAO->getProductById($id);

            Response::json([
                'success' => true,
                'message' => 'Product updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Invalid category, brand, or supplier foreign key.'], 422);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function deleteProduct(int $id): void {
        $existing = $this->productDAO->getProductById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        try {
            $this->productDAO->deleteProduct($id);
            Response::json([
                'success' => true,
                'message' => "Product '{$existing['name']}' deleted successfully."
            ], 200);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Cannot delete product because it has associated transaction records.'], 400);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
