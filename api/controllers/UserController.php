<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * User Management Controller
 * 
 * Manages user authentication introspection, listing, creation, updates, and status toggles.
 * Only Super Admin possesses permission to manage user accounts.
 */
class UserController {
    private UserDAO $userDAO;

    public function __construct() {
        $this->userDAO = new UserDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();

        $action = isset($uriParts[1]) && !is_numeric($uriParts[1]) ? $uriParts[1] : '';
        $subresource = $uriParts[2] ?? null;

        // Session routes
        if ($method === 'GET' && $action === 'me') {
            $this->getMe($user);
            return;
        }

        if ($method === 'POST' && $action === 'logout') {
            $this->logout($user);
            return;
        }

        // All User Management operations require Super Admin
        if (($user['role'] ?? '') !== 'Super Admin') {
            Response::json([
                'success' => false,
                'message' => 'Forbidden: Only Super Admin can manage system users.'
            ], 403);
            return;
        }

        switch ($method) {
            case 'GET':
                if ($id !== null) {
                    $this->getUserById($id);
                } else {
                    $this->getAllUsers();
                }
                break;

            case 'POST':
                $this->createUser();
                break;

            case 'PUT':
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'User ID is required.'], 400);
                }
                if ($subresource === 'status') {
                    $this->updateStatus($id);
                } else {
                    $this->updateUser($id);
                }
                break;

            case 'DELETE':
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'User ID is required.'], 400);
                }
                $this->deleteUser($id, (int)$user['id']);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    private function getMe(array $userPayload): void {
        $user = $this->userDAO->findById((int)$userPayload['id']);
        if ($user) {
            unset($user['password']);
            $user['permissions'] = $userPayload['permissions'] ?? [];
            Response::json(['success' => true, 'data' => $user], 200);
        } else {
            Response::json(['success' => false, 'message' => 'User not found'], 404);
        }
    }

    private function logout(array $userPayload): void {
        Response::json(['success' => true, 'message' => 'Logged out successfully']);
    }

    private function getAllUsers(): void {
        try {
            $users = $this->userDAO->getAllUsers();
            Response::json([
                'success' => true,
                'data'    => $users
            ], 200);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to load users: ' . $e->getMessage()], 500);
        }
    }

    private function getUserById(int $id): void {
        $user = $this->userDAO->findById($id);
        if (!$user) {
            Response::json(['success' => false, 'message' => 'User not found.'], 404);
        }

        unset($user['password']);
        Response::json([
            'success' => true,
            'data'    => $user
        ], 200);
    }

    private function createUser(): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['full_name', 'username', 'email', 'password', 'role']);
        if (!empty($missing)) {
            Response::json([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], 400);
        }

        if (strlen((string)$data['password']) < 6) {
            Response::json(['success' => false, 'message' => 'Password must be at least 6 characters.'], 400);
        }

        try {
            $userId = $this->userDAO->createUser($data);
            $created = $this->userDAO->findById($userId);
            unset($created['password']);

            Response::json([
                'success' => true,
                'message' => 'User created successfully.',
                'data'    => $created
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    private function updateUser(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $missing = Validator::required($data, ['full_name', 'username', 'email', 'role']);
        if (!empty($missing)) {
            Response::json([
                'success' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing)
            ], 400);
        }

        if (!empty($data['password']) && strlen((string)$data['password']) < 6) {
            Response::json(['success' => false, 'message' => 'New password must be at least 6 characters.'], 400);
        }

        try {
            $this->userDAO->updateUser($id, $data);
            $updated = $this->userDAO->findById($id);
            unset($updated['password']);

            Response::json([
                'success' => true,
                'message' => 'User profile updated successfully.',
                'data'    => $updated
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()], 500);
        }
    }

    private function updateStatus(int $id): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['status'])) {
            Response::json(['success' => false, 'message' => 'Status field is required.'], 400);
        }

        $status = (string)$data['status'];

        try {
            $this->userDAO->updateStatus($id, $status);
            Response::json([
                'success' => true,
                'message' => "User status updated to '{$status}'."
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()], 500);
        }
    }

    private function deleteUser(int $id, int $currentUserId): void {
        try {
            $this->userDAO->deleteUser($id, $currentUserId);
            Response::json([
                'success' => true,
                'message' => 'User deleted or deactivated successfully.'
            ], 200);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }
}
