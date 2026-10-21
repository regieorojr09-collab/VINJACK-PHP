<?php
declare(strict_types=1);

namespace Middleware;

use Core\JWT;
use Response;

require_once __DIR__ . '/../core/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Authentication & Role-Based Access Control Middleware
 */
class AuthMiddleware {

    /**
     * Authenticate incoming request via Bearer token in Authorization header.
     * 
     * @return array<string, mixed> Decoded user token payload
     */
    public static function authenticate(): array {
        $token = self::extractBearerToken();

        if (!$token) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized: Missing or malformed Authorization header.'
            ], 401);
        }

        $secret = JWT::getDefaultSecret();
        $payload = JWT::decode($token, $secret);

        if (!$payload) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized: Token is invalid or has expired.'
            ], 401);
        }

        // Return the authenticated claims
        return $payload;
    }

    /**
     * Middleware rule: Require one of the specified roles.
     * Super Admin always passes.
     *
     * @param array<string>|string $roles Allowed roles (e.g., ['Super Admin', 'Admin'])
     * @return \Closure Route-compatible middleware handler
     */
    public static function requireRole(array|string $roles): \Closure {
        $allowed = is_array($roles) ? $roles : [$roles];

        return function(array $params = [], ?array $user = null) use ($allowed) {
            $currentUser = $user ?? self::authenticate();

            if (($currentUser['role'] ?? '') === 'Super Admin') {
                return $currentUser;
            }

            if (!in_array($currentUser['role'] ?? '', $allowed, true)) {
                Response::json([
                    'success' => false,
                    'message' => 'Forbidden: Insufficient role privileges for this resource.'
                ], 403);
            }

            return $currentUser;
        };
    }

    /**
     * Middleware rule: Require a specific permission key.
     * Super Admin always passes.
     *
     * @param string $permissionKey E.g. 'canManageInventory'
     * @return \Closure Route-compatible middleware handler
     */
    public static function requirePermission(string $permissionKey): \Closure {
        return function(array $params = [], ?array $user = null) use ($permissionKey) {
            $currentUser = $user ?? self::authenticate();

            if (($currentUser['role'] ?? '') === 'Super Admin') {
                return $currentUser;
            }

            $userPermissions = $currentUser['permissions'] ?? [];
            if (!in_array($permissionKey, $userPermissions, true)) {
                Response::json([
                    'success' => false,
                    'message' => "Forbidden: Missing required permission [{$permissionKey}]."
                ], 403);
            }

            return $currentUser;
        };
    }

    /**
     * Backward-compatible alias for existing codebase callers.
     */
    public static function protect(): array {
        return self::authenticate();
    }

    /**
     * Robust extraction of Bearer token from Apache/Nginx/CGI headers.
     */
    private static function extractBearerToken(): ?string {
        $header = null;

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            // Handle case-insensitive header lookup
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $header = trim($value);
                    break;
                }
            }
        }

        if (!$header) {
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $header = trim($_SERVER['HTTP_AUTHORIZATION']);
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $header = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            }
        }

        if ($header && preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

// Global namespace alias for existing callers
if (!class_exists('AuthMiddleware', false)) {
    class_alias('Middleware\AuthMiddleware', 'AuthMiddleware');
}
