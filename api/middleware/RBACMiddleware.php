<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/Response.php';

class RBACMiddleware {
    /**
     * Verify user permission.
     * Supports single permission string or array of permissions (grants access if user possesses ANY of them).
     * Super Admin bypasses all checks.
     *
     * @param array $user
     * @param string|array $requiredPermission
     * @return bool
     */
    public static function checkPermission(array $user, string|array $requiredPermission): bool {
        if (($user['role'] ?? '') === 'Super Admin') {
            return true;
        }
        
        $userPerms = $user['permissions'] ?? [];

        if (is_array($requiredPermission)) {
            foreach ($requiredPermission as $perm) {
                if (in_array($perm, $userPerms, true)) {
                    return true;
                }
            }
            Response::json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to perform this action.'
            ], 403);
            return false;
        }
        
        if (!in_array($requiredPermission, $userPerms, true)) {
            Response::json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to perform this action.'
            ], 403);
            return false;
        }
        
        return true;
    }
}

