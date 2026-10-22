<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * System Audit Log Data Access Object
 * 
 * Manages queries and logging of system-wide administrative, inventory,
 * and security events in the immutable audit trail.
 */
class AuditLogDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve paginated and filtered audit logs.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = [], int $limit = 100): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = "a.action = :action";
            $params[':action'] = trim((string)$filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = :user_id";
            $params[':user_id'] = (int)$filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = "a.entity_type = :entity_type";
            $params[':entity_type'] = trim((string)$filters['entity_type']);
        }

        if (!empty($filters['search'])) {
            $where[] = "(a.action LIKE :search OR a.details LIKE :search OR u.username LIKE :search OR u.full_name LIKE :search OR a.entity_type LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereClause = implode(" AND ", $where);
        $sql = "SELECT 
                    a.id,
                    a.user_id,
                    COALESCE(u.full_name, 'System') as user_name,
                    COALESCE(u.username, 'system') as username,
                    COALESCE(u.role, 'System') as user_role,
                    a.action,
                    a.details,
                    a.entity_type,
                    a.entity_id,
                    a.created_at
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE {$whereClause}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log a new audit event.
     */
    public function log(int $userId, string $action, string $details, ?string $entityType = null, ?int $entityId = null): bool {
        $sql = "INSERT INTO audit_logs (user_id, action, details, entity_type, entity_id, created_at)
                VALUES (:user_id, :action, :details, :entity_type, :entity_id, NOW())";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':details'     => $details,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId
        ]);
    }
}
