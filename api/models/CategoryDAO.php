<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Category Data Access Object
 * 
 * Handles CRUD operations and item count aggregation for product categories.
 */
class CategoryDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve all categories with aggregated product count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
                c.created_at, 
                c.updated_at,
                COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            GROUP BY c.id, c.name, c.description, c.created_at, c.updated_at
            ORDER BY c.name ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve single category by ID with aggregated product count.
     *
     * @param int $id
     * @return array<string, mixed>|false
     */
    public function getById(int $id): array|false {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.description, 
                c.created_at, 
                c.updated_at,
                COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id
            WHERE c.id = :id
            GROUP BY c.id, c.name, c.description, c.created_at, c.updated_at
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if category name exists (excluding given ID for updates).
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(:name)";
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
     * Insert a new category.
     *
     * @param array{name: string, description?: ?string} $data
     * @return int Inserted ID
     */
    public function create(array $data): int {
        $sql = "INSERT INTO categories (name, description) VALUES (:name, :description)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->bindValue(':description', !empty($data['description']) ? trim($data['description']) : null);
        $stmt->execute();
        return (int)$this->conn->lastInsertId();
    }

    /**
     * Update an existing category.
     *
     * @param int $id
     * @param array{name: string, description?: ?string} $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE categories SET name = :name, description = :description WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->bindValue(':description', !empty($data['description']) ? trim($data['description']) : null);
        return $stmt->execute();
    }

    /**
     * Delete a category.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM categories WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
