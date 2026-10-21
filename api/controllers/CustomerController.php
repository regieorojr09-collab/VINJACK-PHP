<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/CustomerDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Customer Controller
 * 
 * Manages customer records, motorcycle associations, and RBAC authorization.
 */
class CustomerController {
    private CustomerDAO $customerDAO;

    public function __construct() {
        $this->customerDAO = new CustomerDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $subresource = $uriParts[2] ?? null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canManageCustomers');
                if ($id !== null) {
                    $this->getById($id);
                } else {
                    $this->getAll();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageCustomers');
                if ($id !== null && $subresource === 'motorcycles') {
                    $this->addMotorcycle($id);
                } else {
                    $this->create();
                }
                break;

            case 'PUT':
                RBACMiddleware::checkPermission($user, 'canManageCustomers');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Customer ID required.'], 400);
                }
                $this->update($id);
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageCustomers');
                if ($id !== null && $subresource === 'motorcycles') {
                    $motoId = isset($_GET['motorcycle_id']) ? (int)$_GET['motorcycle_id'] : null;
                    if (!$motoId) {
                        Response::json(['success' => false, 'message' => 'motorcycle_id parameter required.'], 400);
                    }
                    $this->deleteMotorcycle($motoId);
                } elseif ($id !== null) {
                    $this->delete($id);
                } else {
                    Response::json(['success' => false, 'message' => 'Customer ID required.'], 400);
                }
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getAll(): void {
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? Validator::sanitize($_GET['search']) : null;
        $customers = $this->customerDAO->getAll($search);

        Response::json([
            'success' => true,
            'data'    => $customers
        ], 200);
    }

    public function getById(int $id): void {
        $customer = $this->customerDAO->getById($id);
        if (!$customer) {
            Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $customer
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
            Response::json(['success' => false, 'message' => 'Customer name is required.'], 400);
        }

        $name = trim(Validator::sanitize($data['name']));
        if (mb_strlen($name) < 2) {
            Response::json(['success' => false, 'message' => 'Customer name must be at least 2 characters.'], 422);
        }

        $payload = [
            'name'        => $name,
            'email'       => !empty($data['email']) ? trim(Validator::sanitize($data['email'])) : null,
            'phone'       => !empty($data['phone']) ? trim(Validator::sanitize($data['phone'])) : null,
            'address'     => !empty($data['address']) ? trim(Validator::sanitize($data['address'])) : null,
            'motorcycles' => $data['motorcycles'] ?? []
        ];

        try {
            $newId = $this->customerDAO->create($payload);
            $created = $this->customerDAO->getById($newId);

            Response::json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'data'    => $created
            ], 201);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Email address or motorcycle plate number already in use.'], 409);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function update(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $existing = $this->customerDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $missing = Validator::required($data, ['name']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Customer name is required.'], 400);
        }

        $payload = [
            'name'    => trim(Validator::sanitize($data['name'])),
            'email'   => !empty($data['email']) ? trim(Validator::sanitize($data['email'])) : null,
            'phone'   => !empty($data['phone']) ? trim(Validator::sanitize($data['phone'])) : null,
            'address' => !empty($data['address']) ? trim(Validator::sanitize($data['address'])) : null
        ];

        try {
            $this->customerDAO->update($id, $payload);
            $updated = $this->customerDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Customer updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Email address already registered to another customer.'], 409);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function addMotorcycle(int $customerId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['make', 'model']);
        if (!empty($missing)) {
            Response::json(['success' => false, 'message' => 'Motorcycle make and model are required.'], 400);
        }

        try {
            $motoId = $this->customerDAO->addMotorcycle($customerId, $data);
            $customer = $this->customerDAO->getById($customerId);

            Response::json([
                'success'       => true,
                'message'       => 'Motorcycle added to customer profile.',
                'motorcycle_id' => $motoId,
                'customer'      => $customer
            ], 201);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Motorcycle plate number or VIN already registered.'], 409);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function deleteMotorcycle(int $motoId): void {
        try {
            $this->customerDAO->deleteMotorcycle($motoId);
            Response::json(['success' => true, 'message' => 'Motorcycle removed.'], 200);
        } catch (\PDOException $e) {
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void {
        $existing = $this->customerDAO->getById($id);
        if (!$existing) {
            Response::json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        if ((int)($existing['total_purchases'] ?? 0) > 0) {
            Response::json([
                'success' => false,
                'message' => "Cannot delete customer '{$existing['name']}' because they have {$existing['total_purchases']} recorded sales transactions."
            ], 400);
        }

        try {
            $this->customerDAO->delete($id);
            Response::json(['success' => true, 'message' => 'Customer deleted successfully.'], 200);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                Response::json(['success' => false, 'message' => 'Cannot delete customer due to foreign key constraints.'], 400);
            }
            Response::json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
