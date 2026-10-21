<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/SupplierDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Supplier Controller
 * 
 * Manages supplier directory, payment terms, status approval workflows, and RBAC protection.
 */
class SupplierController {
    private SupplierDAO $supplierDAO;

    public function __construct() {
        $this->supplierDAO = new SupplierDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $subresource = $uriParts[2] ?? null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canViewSuppliers');
                if ($id !== null) {
                    $this->getById($id);
                } else {
                    $this->getAll();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageSuppliers');
                $this->create();
                break;

            case 'PUT':
                RBACMiddleware::checkPermission($user, 'canManageSuppliers');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Supplier ID required.'], 400);
                }
                if ($subresource === 'status') {
                    $this->toggleStatus($id);
                } else {
                    $this->update($id);
                }
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageSuppliers');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Supplier ID required.'], 400);
                }
                $this->delete($id);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? Validator::sanitize($_GET['status']) : null;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null;

        $suppliers = $this->supplierDAO->getAll($status, $search);
        Response::json([
            'success' => true,
            'data'    => $suppliers
        ], 200);
    }

    public function getById(int $id): void {
        $supplier = $this->supplierDAO->getById($id);
        if (!$supplier) {
            Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $supplier
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
            Response::json(['success' => false, 'message' => 'Supplier name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Supplier name must be at least 2 characters.'], 422);
        }

        if ($this->supplierDAO->nameExists($name)) {
            Response::json(['success' => false, 'message' => "Supplier '{$name}' already exists."], 409);
        }

        $validTerms = ['Cash', 'Consignment', 'Terms'];
        $terms = in_array($data['default_payment_terms'] ?? '', $validTerms, true) 
                 ? $data['default_payment_terms'] 
                 : 'Cash';

        $validStatuses = ['Approved', 'Pending', 'Rejected'];
        $status = in_array($data['status'] ?? '', $validStatuses, true) 
                  ? $data['status'] 
                  : 'Approved';

        $payload = [
            'name'                  => $name,
            'email'                 => isset($data['email']) ? trim(Validator::sanitize($data['email'])) : null,
            'contact_person'        => isset($data['contact_person']) ? trim(Validator::sanitize($data['contact_person'])) : null,
            'contact_number'        => isset($data['contact_number']) ? trim(Validator::sanitize($data['contact_number'])) : null,
            'address'               => isset($data['address']) ? trim(Validator::sanitize($data['address'])) : null,
            'status'                => $status,
            'default_payment_terms' => $terms
        ];

        try {
            $newId = $this->supplierDAO->create($payload);
            $created = $this->supplierDAO->getById($newId);

            Response::json([
                'success' => true,
                'message' => 'Supplier registered successfully.',
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

        $existing = $this->supplierDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        $missing = Validator::required($data, ['name']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Supplier name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if ($this->supplierDAO->nameExists($name, $id)) {
            Response::json(['success' => false, 'message' => "Another supplier with name '{$name}' already exists."], 409);
        }

        $validTerms = ['Cash', 'Consignment', 'Terms'];
        $terms = in_array($data['default_payment_terms'] ?? '', $validTerms, true) 
                 ? $data['default_payment_terms'] 
                 : ($existing['default_payment_terms'] ?? 'Cash');

        $validStatuses = ['Approved', 'Pending', 'Rejected'];
        $status = in_array($data['status'] ?? '', $validStatuses, true) 
                  ? $data['status'] 
                  : ($existing['status'] ?? 'Approved');

        $payload = [
            'name'                  => $name,
            'email'                 => isset($data['email']) ? trim(Validator::sanitize($data['email'])) : null,
            'contact_person'        => isset($data['contact_person']) ? trim(Validator::sanitize($data['contact_person'])) : null,
            'contact_number'        => isset($data['contact_number']) ? trim(Validator::sanitize($data['contact_number'])) : null,
            'address'               => isset($data['address']) ? trim(Validator::sanitize($data['address'])) : null,
            'status'                => $status,
            'default_payment_terms' => $terms
        ];

        try {
            $this->supplierDAO->update($id, $payload);
            $updated = $this->supplierDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Supplier updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function toggleStatus(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        $status = $data['status'] ?? '';
        $valid = ['Approved', 'Pending', 'Rejected'];
        if (!in_array($status, $valid, true)) {
            Response::json(['success' => false, 'message' => 'Invalid status. Allowed: ' . implode(', ', $valid)], 422);
        }

        $existing = $this->supplierDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        $this->supplierDAO->updateStatus($id, $status);
        Response::json([
            'success' => true,
            'message' => "Supplier status updated to {$status}."
        ], 200);
    }

    public function delete(int $id): void {
        $existing = $this->supplierDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        if ((int)($existing['total_orders'] ?? 0) > 0) {
            Response::json([
                'success' => false,
                'message' => "Cannot delete supplier '{$existing['name']}' because they are linked to {$existing['total_orders']} purchase order(s)."
            ], 400);
        }

        try {
            $this->supplierDAO->delete($id);
            Response::json([
                'success' => true,
                'message' => 'Supplier deleted successfully.'
            ], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Cannot delete supplier with active references.'], 400);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
