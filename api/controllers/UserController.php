<?php
require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

class UserController {
    private $userDAO;

    public function __construct() {
        $this->userDAO = new UserDAO();
    }

    public function handleRequest($method, $id, $uriParts) {
        // All user routes except perhaps password reset require auth
        $user = AuthMiddleware::protect();

        $action = isset($uriParts[1]) && !is_numeric($uriParts[1]) ? $uriParts[1] : '';

        if ($method === 'GET' && $action === 'me') {
            $this->getMe($user);
        } elseif ($method === 'POST' && $action === 'logout') {
            $this->logout($user);
        } elseif ($method === 'GET' && !$id && !$action) {
            RBACMiddleware::checkPermission($user, 'Super Admin'); // Only Super Admin can list users
            $this->getAllUsers();
        } else {
            Response::json(['message' => 'Endpoint not found or method not allowed'], 404);
        }
    }

    private function getMe($userPayload) {
        $user = $this->userDAO->findById($userPayload['id']);
        if ($user) {
            unset($user['password']);
            $user['permissions'] = $userPayload['permissions'];
            Response::json($user);
        } else {
            Response::json(['message' => 'User not found'], 404);
        }
    }

    private function logout($userPayload) {
        // JWT is stateless, so we just return success.
        // In a real app, we might blacklist the token or log the action.
        // TODO: Log Audit LOGOUT
        Response::json(['message' => 'Logged out successfully']);
    }

    private function getAllUsers() {
        $users = $this->userDAO->getAllUsers();
        Response::json($users);
    }
}
