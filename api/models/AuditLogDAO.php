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
     * Statically insert an immutable system audit log entry.
     * Fault-tolerant global write-hook safe for injection across all controllers.
     *
     * @param int|string|null $userId     Acting user ID, or null for unauthenticated/system events
     * @param string          $action     Standardized action identifier (e.g. USER_LOGIN, SALE_COMPLETED)
     * @param string          $details    Human-readable description of the event
     * @param string|null     $entityType Optional target domain entity (e.g. Auth, Sale, Product, PurchaseOrder, User)
     * @param int|string|null $entityId   Optional target entity primary key
     * @return bool
     */
    public static function createLog($userId, $action, $details, $entityType = null, $entityId = null): bool {
        try {
            $conn = Database::getConnection();

            $normalizedUserId = ($userId !== null && $userId !== '' && is_numeric($userId) && (int)$userId > 0)
                ? (int)$userId
                : null;

            $normalizedAction = substr(trim((string)$action), 0, 50);
            if ($normalizedAction === '') {
                $normalizedAction = 'SYSTEM_EVENT';
            }

            $normalizedDetails = trim((string)$details);

            $normalizedEntityType = ($entityType !== null && trim((string)$entityType) !== '')
                ? substr(trim((string)$entityType), 0, 50)
                : null;

            $normalizedEntityId = ($entityId !== null && $entityId !== '' && is_numeric($entityId) && (int)$entityId > 0)
                ? (int)$entityId
                : null;

            $sql = "INSERT INTO audit_logs (user_id, action, details, entity_type, entity_id, created_at)
                    VALUES (:user_id, :action, :details, :entity_type, :entity_id, NOW())";

            try {
                $stmt = $conn->prepare($sql);
                if ($normalizedUserId !== null) {
                    $stmt->bindValue(':user_id', $normalizedUserId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
                }
                $stmt->bindValue(':action', $normalizedAction, PDO::PARAM_STR);
                $stmt->bindValue(':details', $normalizedDetails, PDO::PARAM_STR);
                if ($normalizedEntityType !== null) {
                    $stmt->bindValue(':entity_type', $normalizedEntityType, PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':entity_type', null, PDO::PARAM_NULL);
                }
                if ($normalizedEntityId !== null) {
                    $stmt->bindValue(':entity_id', $normalizedEntityId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
                }

                return $stmt->execute();
            } catch (\PDOException $pdoEx) {
                // Graceful fallback if live schema still enforces audit_logs.user_id NOT NULL
                if ($normalizedUserId === null) {
                    $fallbackId = self::resolveFallbackUserId($conn);
                    if ($fallbackId !== null) {
                        $stmt = $conn->prepare($sql);
                        $stmt->bindValue(':user_id', $fallbackId, PDO::PARAM_INT);
                        $stmt->bindValue(':action', $normalizedAction, PDO::PARAM_STR);
                        $stmt->bindValue(':details', '[Unauthenticated/System] ' . $normalizedDetails, PDO::PARAM_STR);
                        if ($normalizedEntityType !== null) {
                            $stmt->bindValue(':entity_type', $normalizedEntityType, PDO::PARAM_STR);
                        } else {
                            $stmt->bindValue(':entity_type', null, PDO::PARAM_NULL);
                        }
                        if ($normalizedEntityId !== null) {
                            $stmt->bindValue(':entity_id', $normalizedEntityId, PDO::PARAM_INT);
                        } else {
                            $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
                        }
                        return $stmt->execute();
                    }
                }
                throw $pdoEx;
            }
        } catch (\Throwable $e) {
            error_log("AuditLogDAO::createLog failure [{$action}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve primary Super Admin / System user ID when schema requires non-null user_id.
     */
    private static function resolveFallbackUserId(PDO $conn): ?int {
        try {
            $stmt = $conn->query("SELECT id FROM users ORDER BY FIELD(role, 'Super Admin', 'Admin', 'Salesperson'), id ASC LIMIT 1");
            $id = $stmt->fetchColumn();
            return $id !== false ? (int)$id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Instance wrapper around createLog() for backward compatibility.
     */
    public function log(int $userId, string $action, string $details, ?string $entityType = null, ?int $entityId = null): bool {
        return self::createLog($userId, $action, $details, $entityType, $entityId);
    }
}
