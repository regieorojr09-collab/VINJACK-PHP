<?php
require_once __DIR__ . '/../utils/Response.php';

class RBACMiddleware {
    public static function checkPermission($user, $requiredPermission) {
        if ($user['role'] === 'Super Admin') {
            return true;
        }
        
        if (!isset($user['permissions']) || !in_array($requiredPermission, $user['permissions'])) {
            Response::json(['message' => 'Forbidden: You do not have permission to perform this action.'], 403);
        }
        
        return true;
    }
}
