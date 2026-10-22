<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ServiceDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Service Controller
 * 
 * Exposes REST endpoints for the workshop labor and services catalog.
 */
class ServiceController {
    private ServiceDAO $serviceDAO;

    public function __construct() {
        $this->serviceDAO = new ServiceDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();

        switch ($method) {
            case 'GET':
                // Any role that can sell or view inventory can view services
                RBACMiddleware::checkPermission($user, ['canManageSales', 'canViewInventory', 'canViewDashboard']);
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
                    Response::json(['success' => false, 'message' => 'Service ID is required for update.'], 400);
                }
                $this->update($id);
                break;

            case 'DELETE':
                RBACMiddleware::checkPermission($user, 'canManageInventory');
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'Service ID is required for deletion.'], 400);
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
        try {
            $services = $this->serviceDAO->getAll($status);
            Response::json([
                'success' => true,
                'data'    => $services
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error querying services: ' . $e->getMessage()], 500);
        }
    }

    public function getById(int $id): void {
        $service = $this->serviceDAO->getById($id);
        if (!$service) {
            Response::json(['success' => false, 'message' => 'Service record not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $service
        ], 200);
    }

    public function create(): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['name', 'charge']);
        if (!empty($missing)) {
            Response::json([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], 400);
        }

        try {
            $id = $this->serviceDAO->create($data);
            $service = $this->serviceDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Service created successfully.',
                'data'    => $service
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error creating service: ' . $e->getMessage()], 500);
        }
    }

    public function update(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        try {
            $this->serviceDAO->update($id, $data);
            $updated = $this->serviceDAO->getById($id);

            Response::json([
                'success' => true,
                'message' => 'Service updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error updating service: ' . $e->getMessage()], 500);
        }
    }

    public function delete(int $id): void {
        try {
            $this->serviceDAO->delete($id);
            Response::json([
                'success' => true,
                'message' => 'Service removed or deactivated successfully.'
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error deleting service: ' . $e->getMessage()], 500);
        }
    }
}
