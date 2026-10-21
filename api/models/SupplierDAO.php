<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Supplier Data Access Object
 * 
 * Manages vendor directories, payment terms, and approval workflows.
 */
class SupplierDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve all suppliers with optional search and status filters.
     *
     * @param string|null $status E.g. 'Approved', 'Pending', 'Rejected'
     * @param string|null $search Search string matching name, contact, or email
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $status = null, ?string $search = null): array {
        $sql = "
            SELECT 
                s.id, 
                s.name, 
                s.email, 
                s.contact_person, 
                s.contact_number, 
                s.address, 
                s.status, 
                s.default_payment_terms, 
                s.created_at, 
                s.updated_at,
                COUNT(DISTINCT po.id) AS total_orders,
                COUNT(DISTINCT psc.product_id) AS products_supplied
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            LEFT JOIN product_supplier_costs psc ON s.id = psc.supplier_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($status)) {
            $sql .= " AND s.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (s.name LIKE :search OR s.contact_person LIKE :search OR s.email LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= " GROUP BY s.id, s.name, s.email, s.contact_person, s.contact_number, s.address, s.status, s.default_payment_terms, s.created_at, s.updated_at";
        $sql .= " ORDER BY s.name ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve single supplier by ID with aggregate order info.
     *
     * @param int $id
     * @return array<string, mixed>|false
     */
    public function getById(int $id): array|false {
        $sql = "
            SELECT 
                s.id, 
                s.name, 
                s.email, 
                s.contact_person, 
                s.contact_number, 
                s.address, 
                s.status, 
                s.default_payment_terms, 
                s.created_at, 
                s.updated_at,
                COUNT(DISTINCT po.id) AS total_orders,
                COUNT(DISTINCT psc.product_id) AS products_supplied
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            LEFT JOIN product_supplier_costs psc ON s.id = psc.supplier_id
            WHERE s.id = :id
            GROUP BY s.id, s.name, s.email, s.contact_person, s.contact_number, s.address, s.status, s.default_payment_terms, s.created_at, s.updated_at
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if supplier name already exists.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM suppliers WHERE LOWER(name) = LOWER(:name)";
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
     * Insert new supplier.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function create(array $data): int {
        $sql = "
            INSERT INTO suppliers (name, email, contact_person, contact_number, address, status, default_payment_terms) 
            VALUES (:name, :email, :contact_person, :contact_number, :address, :status, :default_payment_terms)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->bindValue(':email', !empty($data['email']) ? trim($data['email']) : null);
        $stmt->bindValue(':contact_person', !empty($data['contact_person']) ? trim($data['contact_person']) : null);
        $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? trim($data['contact_number']) : null);
        $stmt->bindValue(':address', !empty($data['address']) ? trim($data['address']) : null);
        $stmt->bindValue(':status', $data['status'] ?? 'Approved');
        $stmt->bindValue(':default_payment_terms', $data['default_payment_terms'] ?? 'Cash');
        $stmt->execute();

        return (int)$this->conn->lastInsertId();
    }

    /**
     * Update existing supplier profile.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $sql = "
            UPDATE suppliers 
            SET name = :name, 
                email = :email, 
                contact_person = :contact_person, 
                contact_number = :contact_number, 
                address = :address, 
                status = :status, 
                default_payment_terms = :default_payment_terms
            WHERE id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->bindValue(':email', !empty($data['email']) ? trim($data['email']) : null);
        $stmt->bindValue(':contact_person', !empty($data['contact_person']) ? trim($data['contact_person']) : null);
        $stmt->bindValue(':contact_number', !empty($data['contact_number']) ? trim($data['contact_number']) : null);
        $stmt->bindValue(':address', !empty($data['address']) ? trim($data['address']) : null);
        $stmt->bindValue(':status', $data['status'] ?? 'Approved');
        $stmt->bindValue(':default_payment_terms', $data['default_payment_terms'] ?? 'Cash');
        return $stmt->execute();
    }

    /**
     * Update supplier status only.
     */
    public function updateStatus(int $id, string $status): bool {
        $sql = "UPDATE suppliers SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        return $stmt->execute();
    }

    /**
     * Delete supplier.
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM suppliers WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
