<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../models/AuditLogDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * User Management Controller
 * 
 * Manages user authentication introspection, listing, creation, updates,
 * status toggles, and administrative audit logging.
 * Only Super Admin possesses permission to manage user accounts.
 */
class UserController {
    private UserDAO $userDAO;

    public function __construct() {
        $this->userDAO = new UserDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $actorId = (int)($user['id'] ?? 0);

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

        // Strictly block Salesperson accounts from accessing User Management endpoints
        if (!in_array($user['role'] ?? '', ['Super Admin', 'Admin'], true)) {
            Response::json([
                'success' => false,
                'message' => '403 Forbidden: Access Denied. Only Admins and Super Admins can access User Management.'
            ], 403);
            return;
        }

        // Write mutations (create, update, status toggle, delete) require Super Admin or explicit canManageUsers
        if ($method !== 'GET' && ($user['role'] ?? '') !== 'Super Admin' && !in_array('canManageUsers', $user['permissions'] ?? [], true)) {
            Response::json([
                'success' => false,
                'message' => '403 Forbidden: Only Super Admin can create, modify, or delete user accounts.'
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
                $this->createUser($actorId);
                break;

            case 'PUT':
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'User ID is required.'], 400);
                }
                if ($subresource === 'status') {
                    $this->updateStatus($id, $actorId);
                } else {
                    $this->updateUser($id, $actorId);
                }
                break;

            case 'DELETE':
                if ($id === null) {
                    Response::json(['success' => false, 'message' => 'User ID is required.'], 400);
                }
                $this->deleteUser($id, $actorId);
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

    private function createUser(int $actorId): void {
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

            AuditLogDAO::createLog(
                $actorId,
                'USER_CREATED',
                "Created user account '@{$created['username']}' ({$created['full_name']}) with role '{$created['role']}' and status '{$created['status']}'.",
                'User',
                $userId
            );

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

    private function updateUser(int $id, int $actorId): void {
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
            $before = $this->userDAO->findById($id);
            if (!$before) {
                Response::json(['success' => false, 'message' => "User ID {$id} not found."], 404);
            }

            $this->userDAO->updateUser($id, $data);
            if (($before['status'] ?? '') === 'locked' && ($data['status'] ?? '') === 'active') {
                $this->userDAO->resetFailedAttempts($id);
            }

            $updated = $this->userDAO->findById($id);
            unset($updated['password']);

            $roleChanged = ($before['role'] ?? '') !== ($updated['role'] ?? '');
            $statusChanged = ($before['status'] ?? '') !== ($updated['status'] ?? '');

            if ($roleChanged) {
                $actionCode = 'USER_ROLE_UPDATED';
                $statusNote = $statusChanged ? " and status '{$before['status']}' -> '{$updated['status']}'" : '';
                $details = "Updated role for '@{$updated['username']}' from '{$before['role']}' to '{$updated['role']}'{$statusNote}.";
            } elseif ($statusChanged) {
                $actionCode = 'USER_STATUS_UPDATED';
                $details = "Changed account status for '@{$updated['username']}' from '{$before['status']}' to '{$updated['status']}'.";
            } else {
                $actionCode = 'USER_UPDATED';
                $pwdNote = !empty($data['password']) ? ' (including password reset)' : '';
                $details = "Updated account profile for '@{$updated['username']}' ({$updated['role']}){$pwdNote}.";
            }

            AuditLogDAO::createLog(
                $actorId,
                $actionCode,
                $details,
                'User',
                $id
            );

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

    private function updateStatus(int $id, int $actorId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data) || empty($data['status'])) {
            Response::json(['success' => false, 'message' => 'Status field is required.'], 400);
        }

        $status = (string)$data['status'];

        try {
            $before = $this->userDAO->findById($id);
            if (!$before) {
                Response::json(['success' => false, 'message' => 'User not found.'], 404);
            }

            $this->userDAO->updateStatus($id, $status);
            if ($status === 'active') {
                $this->userDAO->resetFailedAttempts($id);
            }

            $oldStatus = $before['status'] ?? 'unknown';
            AuditLogDAO::createLog(
                $actorId,
                'USER_STATUS_UPDATED',
                "Changed account status for '@{$before['username']}' ({$before['role']}) from '{$oldStatus}' to '{$status}'.",
                'User',
                $id
            );

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
            $before = $this->userDAO->findById($id);
            $this->userDAO->deleteUser($id, $currentUserId);
            $after = $this->userDAO->findById($id);

            $targetUsername = $before['username'] ?? "user#{$id}";
            if ($after) {
                AuditLogDAO::createLog(
                    $currentUserId,
                    'USER_STATUS_UPDATED',
                    "Deactivated user account '@{$targetUsername}' to preserve historical transaction records.",
                    'User',
                    $id
                );
            } else {
                AuditLogDAO::createLog(
                    $currentUserId,
                    'USER_DELETED',
                    "Deleted user account '@{$targetUsername}'.",
                    'User',
                    $id
                );
            }

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
