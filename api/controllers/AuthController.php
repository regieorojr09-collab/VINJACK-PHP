<?php
declare(strict_types=1);

namespace Controllers;

use Core\JWT;
use Middleware\AuthMiddleware;
use Response;
use UserDAO;
use Validator;

require_once __DIR__ . '/../models/UserDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../core/JWT.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

/**
 * Authentication Controller
 * 
 * Manages user authentication, token issuance, active session introspection,
 * and demo credentials.
 */
class AuthController {
    private ?UserDAO $userDAO = null;

    public function __construct(?UserDAO $userDAO = null) {
        $this->userDAO = $userDAO;
    }

    /**
     * Lazy getter for UserDAO to prevent premature database connection on script bootstrapping.
     */
    private function getUserDAO(): UserDAO {
        if ($this->userDAO === null) {
            $this->userDAO = new UserDAO();
        }
        return $this->userDAO;
    }

    /**
     * POST /api/auth/login
     * Authenticate user credentials and return signed JWT.
     *
     * @param array<string, string> $params
     * @param array<string, mixed>|null $userContext
     */
    public function login(array $params = [], ?array $userContext = null): void {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);

        if (!is_array($data)) {
            Response::json([
                'success' => false,
                'message' => 'Invalid JSON payload provided.'
            ], 400);
        }

        $missing = Validator::required($data, ['username', 'password']);
        if (!empty($missing)) {
            Response::json([
                'success' => false,
                'message' => 'Please provide both username and password.'
            ], 400);
        }

        $username = Validator::sanitize($data['username']);
        $password = (string)$data['password'];

        try {
            $user = $this->getUserDAO()->findByUsername($username);
        } catch (\Throwable $e) {
            error_log("Database connection failure during login: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Database connection error: Unable to connect to MySQL database server. Please verify database credentials in Render.',
                'error'   => $e->getMessage()
            ], 503);
        }

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Account Lock Status Check
        if (($user['status'] ?? '') === 'locked') {
            Response::json([
                'success' => false,
                'message' => 'Account is locked. Please contact the administrator.'
            ], 403);
        }

        if (($user['failed_attempts'] ?? 0) >= 5) {
            try {
                $this->getUserDAO()->lockAccount((int)$user['id']);
            } catch (\Throwable $e) {
                error_log("Failed to lock account: " . $e->getMessage());
            }
            Response::json([
                'success' => false,
                'message' => 'Account is locked due to too many failed attempts.'
            ], 403);
        }

        // Verify Bcrypt Password Hash
        if (password_verify($password, $user['password'])) {
            try {
                $this->getUserDAO()->resetFailedAttempts((int)$user['id']);

                $permissions = [];
                if ($user['role'] !== 'Super Admin') {
                    $permissions = $this->getUserDAO()->getUserPermissions($user['role']);
                }
            } catch (\Throwable $e) {
                error_log("Database error loading permissions: " . $e->getMessage());
                $permissions = [];
            }

            $payload = [
                'id'          => (int)$user['id'],
                'username'    => $user['username'],
                'full_name'   => $user['full_name'],
                'role'        => $user['role'],
                'permissions' => $permissions
            ];

            $token = JWT::encode($payload, JWT::getDefaultSecret(), 86400);

            unset($user['password']); // Never expose password hash
            $user['permissions'] = $permissions;

            Response::json([
                'success' => true,
                'message' => 'Authentication successful.',
                'token'   => $token,
                'user'    => $user
            ], 200);
        } else {
            try {
                $this->getUserDAO()->incrementFailedAttempts((int)$user['id']);
            } catch (\Throwable $e) {
                error_log("Failed to increment failed attempts: " . $e->getMessage());
            }
            Response::json([
                'success' => false,
                'message' => 'Invalid credentials.'
            ], 401);
        }
    }

    /**
     * GET /api/auth/me
     * Introspect active session claims and return the authenticated user's profile.
     *
     * @param array<string, string> $params
     * @param array<string, mixed>|null $userContext
     */
    public function me(array $params = [], ?array $userContext = null): void {
        $authenticatedClaims = $userContext ?? AuthMiddleware::authenticate();

        $userId = (int)($authenticatedClaims['id'] ?? 0);

        try {
            $user = $this->getUserDAO()->findById($userId);
        } catch (\Throwable $e) {
            error_log("Database error in auth/me: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Database error retrieving user profile.',
                'error'   => $e->getMessage()
            ], 503);
        }

        if (!$user) {
            Response::json([
                'success' => false,
                'message' => 'Authenticated user profile not found.'
            ], 404);
        }

        unset($user['password']);
        $user['permissions'] = $authenticatedClaims['permissions'] ?? [];

        Response::json([
            'success' => true,
            'user'    => $user,
            'session' => [
                'issued_at'  => $authenticatedClaims['iat'] ?? null,
                'expires_at' => $authenticatedClaims['exp'] ?? null,
                'role'       => $authenticatedClaims['role'] ?? null
            ]
        ], 200);
    }

    /**
     * POST /api/auth/demo-login
     * Helper for quick demo role selection.
     */
    public function demoLogin(array $params = [], ?array $userContext = null): void {
        $rawInput = file_get_contents("php://input");
        $data = json_decode($rawInput, true);
        $role = isset($data['role']) ? Validator::sanitize($data['role']) : 'Super Admin';

        $usernameMap = [
            'Super Admin' => 'admin',
            'Admin'       => 'mechanic',
            'Salesperson' => 'clerk'
        ];

        $targetUsername = $usernameMap[$role] ?? 'admin';

        try {
            $user = $this->getUserDAO()->findByUsername($targetUsername);

            if ($user) {
                $permissions = [];
                if ($user['role'] !== 'Super Admin') {
                    $permissions = $this->getUserDAO()->getUserPermissions($user['role']);
                }

                $payload = [
                    'id'          => (int)$user['id'],
                    'username'    => $user['username'],
                    'full_name'   => $user['full_name'],
                    'role'        => $user['role'],
                    'permissions' => $permissions
                ];

                $token = JWT::encode($payload, JWT::getDefaultSecret(), 86400);
                unset($user['password']);
                $user['permissions'] = $permissions;

                Response::json([
                    'success' => true,
                    'message' => 'Demo authentication successful.',
                    'token'   => $token,
                    'user'    => $user
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => "Demo user for role '{$role}' not found in database."
                ], 404);
            }
        } catch (\Throwable $e) {
            error_log("Database error in demo-login: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Database connection error: Unable to connect to MySQL database for demo login.',
                'error'   => $e->getMessage()
            ], 503);
        }
    }

    /**
     * Legacy route dispatch adapter.
     */
    public function handleRequest(string $method, array $uriParts): void {
        $action = $uriParts[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $this->login();
        } elseif ($method === 'GET' && $action === 'me') {
            $this->me();
        } elseif ($method === 'POST' && $action === 'demo-login') {
            $this->demoLogin();
        } else {
            Response::json(['message' => 'Endpoint not found'], 404);
        }
    }
}

// Global alias for compatibility
if (!class_exists('AuthController', false)) {
    class_alias('Controllers\AuthController', 'AuthController');
}
