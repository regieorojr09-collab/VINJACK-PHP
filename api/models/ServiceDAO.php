<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Workshop Labor & Services Data Access Object
 * 
 * Provides database operations for workshop services catalog.
 */
class ServiceDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve all services, optionally filtered by status.
     *
     * @param string|null $status
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $status = null): array {
        $sql = "SELECT id, name, description, charge, status, created_at, updated_at FROM services";
        if ($status !== null && $status !== '') {
            $sql .= " WHERE status = :status";
        }
        $sql .= " ORDER BY name ASC";

        $stmt = $this->conn->prepare($sql);
        if ($status !== null && $status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a service by primary ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM services WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a service name is already taken.
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function isNameTaken(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM services WHERE name = :name" . ($excludeId ? " AND id != :excludeId" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', trim($name));
        if ($excludeId) {
            $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Create a new labor service record.
     *
     * @param array $data
     * @return int Inserted ID
     */
    public function create(array $data): int {
        $name = trim($data['name'] ?? '');
        if ($this->isNameTaken($name)) {
            throw new \InvalidArgumentException("A service named '{$name}' already exists.");
        }

        $charge = isset($data['charge']) ? (float)$data['charge'] : 0.00;
        if ($charge < 0) {
            throw new \InvalidArgumentException("Service charge cannot be negative.");
        }

        $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $description = !empty($data['description']) ? trim($data['description']) : null;

        $stmt = $this->conn->prepare("
            INSERT INTO services (name, description, charge, status) 
            VALUES (:name, :description, :charge, :status)
        ");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':charge', $charge);
        $stmt->bindValue(':status', $status);
        $stmt->execute();

        return (int)$this->conn->lastInsertId();
    }

    /**
     * Update an existing labor service.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $existing = $this->getById($id);
        if (!$existing) {
            throw new \RuntimeException("Service ID {$id} not found.");
        }

        $name = isset($data['name']) ? trim($data['name']) : $existing['name'];
        if ($this->isNameTaken($name, $id)) {
            throw new \InvalidArgumentException("A service named '{$name}' already exists.");
        }

        $charge = isset($data['charge']) ? (float)$data['charge'] : (float)$existing['charge'];
        if ($charge < 0) {
            throw new \InvalidArgumentException("Service charge cannot be negative.");
        }

        $status = isset($data['status']) ? (($data['status'] === 'inactive') ? 'inactive' : 'active') : $existing['status'];
        $description = array_key_exists('description', $data) ? (!empty($data['description']) ? trim($data['description']) : null) : $existing['description'];

        $stmt = $this->conn->prepare("
            UPDATE services 
            SET name = :name, description = :description, charge = :charge, status = :status 
            WHERE id = :id
        ");
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':charge', $charge);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Delete or deactivate a service with referential check.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        // Check if service was included in sales
        $chk = $this->conn->prepare("SELECT COUNT(*) FROM sale_services WHERE service_id = :id");
        $chk->bindValue(':id', $id, PDO::PARAM_INT);
        $chk->execute();

        if ((int)$chk->fetchColumn() > 0) {
            // Soft deactivate to protect transactional integrity
            $stmt = $this->conn->prepare("UPDATE services SET status = 'inactive' WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        }

        $stmt = $this->conn->prepare("DELETE FROM services WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
