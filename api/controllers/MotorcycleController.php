<?php
require_once __DIR__ . '/../models/MotorcycleDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

class MotorcycleController {
    private $motorcycleDAO;

    public function __construct() {
        $this->motorcycleDAO = new MotorcycleDAO();
    }

    public function handleRequest($method, $id, $uriParts) {
        $user = AuthMiddleware::protect();

        if ($method === 'GET' && !$id) {
            RBACMiddleware::checkPermission($user, 'canManageCustomers');
            $this->getAll();
        } elseif ($method === 'GET' && $id) {
            RBACMiddleware::checkPermission($user, 'canManageCustomers');
            $this->getById($id);
        } elseif ($method === 'POST') {
            RBACMiddleware::checkPermission($user, 'canManageCustomers');
            $this->create();
        } elseif ($method === 'PUT' && $id) {
            RBACMiddleware::checkPermission($user, 'canManageCustomers');
            $this->update($id);
        } elseif ($method === 'DELETE' && $id) {
            RBACMiddleware::checkPermission($user, 'canManageCustomers');
            $this->delete($id);
        } else {
            Response::json(['message' => 'Endpoint not found or method not allowed'], 404);
        }
    }

    private function getAll() {
        $data = $this->motorcycleDAO->getAll();
        Response::json($data);
    }

    private function getById($id) {
        $data = $this->motorcycleDAO->getById($id);
        if ($data) {
            Response::json($data);
        } else {
            Response::json(['message' => 'Motorcycle not found'], 404);
        }
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"), true);
        $missing = Validator::required($data, ['make', 'model']);
        if (!empty($missing)) {
            Response::json(['message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        }

        try {
            $id = $this->motorcycleDAO->create($data);
            if ($id) {
                Response::json($this->motorcycleDAO->getById($id), 201);
            } else {
                Response::json(['message' => 'Failed to create motorcycle'], 500);
            }
        } catch (PDOException $e) {
            Response::json(['message' => $e->getMessage()], 500);
        }
    }

    private function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        $missing = Validator::required($data, ['make', 'model']);
        if (!empty($missing)) {
            Response::json(['message' => 'Missing required fields: ' . implode(', ', $missing)], 400);
        }

        try {
            if ($this->motorcycleDAO->update($id, $data)) {
                Response::json($this->motorcycleDAO->getById($id));
            } else {
                Response::json(['message' => 'Failed to update motorcycle'], 500);
            }
        } catch (PDOException $e) {
            Response::json(['message' => $e->getMessage()], 500);
        }
    }

    private function delete($id) {
        try {
            if ($this->motorcycleDAO->delete($id)) {
                Response::json(['message' => 'Motorcycle deleted successfully']);
            } else {
                Response::json(['message' => 'Failed to delete motorcycle'], 500);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Response::json(['message' => 'Cannot delete motorcycle as it has associated service records'], 400);
            }
            Response::json(['message' => $e->getMessage()], 500);
        }
    }
}
