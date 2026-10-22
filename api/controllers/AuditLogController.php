<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/AuditLogDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * System Audit Log Controller
 * 
 * Provides authorized administrative access to immutable user audit trails
 * and action history across all system modules.
 */
class AuditLogController {
    private AuditLogDAO $auditLogDAO;

    public function __construct() {
        $this->auditLogDAO = new AuditLogDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();

        switch ($method) {
            case 'GET':
                // Check if user has permission to view reports or is Super Admin/Admin
                if (!in_array($user['role'] ?? '', ['Super Admin', 'Admin'])) {
                    RBACMiddleware::checkPermission($user, 'canViewReports');
                }
                $this->getAll();
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed."], 405);
                break;
        }
    }

    /**
     * GET /api/audit-logs or /api/audit_logs
     */
    public function getAll(): void {
        $filters = [
            'search'      => isset($_GET['search']) ? trim((string)$_GET['search']) : '',
            'action'      => isset($_GET['action']) ? trim((string)$_GET['action']) : '',
            'user_id'     => isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null,
            'entity_type' => isset($_GET['entity_type']) ? trim((string)$_GET['entity_type']) : ''
        ];

        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 100;

        try {
            $logs = $this->auditLogDAO->getAll($filters, $limit);
            Response::json([
                'success' => true,
                'data'    => $logs,
                'count'   => count($logs)
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to fetch audit logs: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Failed to retrieve audit log trail.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
