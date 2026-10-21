<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Brand Data Access Object
 * 
 * Handles CRUD operations and item count aggregation for product brands.
 */
class BrandDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve all brands with aggregated product counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array {
        $sql = "
            SELECT 
                b.id, 
                b.name, 
                b.created_at, 
                b.updated_at,
                COUNT(p.id) AS product_count
            FROM brands b
            LEFT JOIN products p ON b.id = p.brand_id
            GROUP BY b.id, b.name, b.created_at, b.updated_at
            ORDER BY b.name ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve single brand by ID with aggregated product count.
     *
     * @param int $id
     * @return array<string, mixed>|false
     */
    public function getById(int $id): array|false {
        $sql = "
            SELECT 
                b.id, 
                b.name, 
                b.created_at, 
                b.updated_at,
                COUNT(p.id) AS product_count
            FROM brands b
            LEFT JOIN products p ON b.id = p.brand_id
            WHERE b.id = :id
            GROUP BY b.id, b.name, b.created_at, b.updated_at
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if brand name exists.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM brands WHERE LOWER(name) = LOWER(:name)";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', trim($name));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Insert a new brand.
     *
     * @param array{name: string} $data
     * @return int Inserted ID
     */
    public function create(array $data): int {
        $sql = "INSERT INTO brands (name) VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->execute();
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Update an existing brand.
     *
     * @param int $id
     * @param array{name: string} $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE brands SET name = :name WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', trim($data['name']));
        return $stmt->execute();
    }

    /**
     * Delete a brand.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM brands WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
