<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Customer & Motorcycle Data Access Object
 * 
 * Manages customer profiles and their linked vehicles in compliance with ACID transactions.
 */
class CustomerDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve all customers with their motorcycles.
     *
     * @param string|null $search
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $search = null): array {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.email, 
                c.phone, 
                c.address, 
                c.created_at, 
                c.updated_at,
                COUNT(s.id) AS total_purchases
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (c.name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= " GROUP BY c.id, c.name, c.email, c.phone, c.address, c.created_at, c.updated_at";
        $sql .= " ORDER BY c.name ASC";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch motorcycles for all retrieved customers
        if (!empty($customers)) {
            $customerIds = array_column($customers, 'id');
            $inPlaceholders = implode(',', array_fill(0, count($customerIds), '?'));
            
            $motoSql = "SELECT * FROM motorcycles WHERE owner_id IN ({$inPlaceholders}) ORDER BY id ASC";
            $motoStmt = $this->conn->prepare($motoSql);
            foreach ($customerIds as $idx => $cid) {
                $motoStmt->bindValue($idx + 1, (int)$cid, PDO::PARAM_INT);
            }
            $motoStmt->execute();
            $allMotos = $motoStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group motorcycles by owner_id
            $motosByCustomer = [];
            foreach ($allMotos as $moto) {
                $motosByCustomer[$moto['owner_id']][] = $moto;
            }

            foreach ($customers as &$cust) {
                $cust['motorcycles'] = $motosByCustomer[$cust['id']] ?? [];
            }
        }

        return $customers;
    }

    /**
     * Retrieve single customer by ID.
     */
    public function getById(int $id): array|false {
        $sql = "
            SELECT 
                c.id, 
                c.name, 
                c.email, 
                c.phone, 
                c.address, 
                c.created_at, 
                c.updated_at,
                COUNT(s.id) AS total_purchases
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            WHERE c.id = :id
            GROUP BY c.id, c.name, c.email, c.phone, c.address, c.created_at, c.updated_at
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            $motoStmt = $this->conn->prepare("SELECT * FROM motorcycles WHERE owner_id = :id ORDER BY id ASC");
            $motoStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $motoStmt->execute();
            $customer['motorcycles'] = $motoStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $customer;
    }

    /**
     * Create customer with optional initial motorcycles in a transaction.
     *
     * @param array<string, mixed> $data
     * @return int New Customer ID
     */
    public function create(array $data): int {
        $this->conn->beginTransaction();

        try {
            $sql = "INSERT INTO customers (name, email, phone, address) VALUES (:name, :email, :phone, :address)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':name', trim($data['name']));
            $stmt->bindValue(':email', !empty($data['email']) ? trim($data['email']) : null);
            $stmt->bindValue(':phone', !empty($data['phone']) ? trim($data['phone']) : null);
            $stmt->bindValue(':address', !empty($data['address']) ? trim($data['address']) : null);
            $stmt->execute();

            $customerId = (int)$this->conn->lastInsertId();

            // Insert motorcycles if supplied
            if (!empty($data['motorcycles']) && is_array($data['motorcycles'])) {
                foreach ($data['motorcycles'] as $moto) {
                    if (!empty($moto['make']) && !empty($moto['model'])) {
                        $this->insertMotorcycleRecord($customerId, $moto);
                    }
                }
            }

            $this->conn->commit();
            return $customerId;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Update customer profile.
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE customers SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':name', trim($data['name']));
        $stmt->bindValue(':email', !empty($data['email']) ? trim($data['email']) : null);
        $stmt->bindValue(':phone', !empty($data['phone']) ? trim($data['phone']) : null);
        $stmt->bindValue(':address', !empty($data['address']) ? trim($data['address']) : null);
        return $stmt->execute();
    }

    /**
     * Delete customer profile.
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM customers WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Link a new motorcycle to a customer.
     */
    public function addMotorcycle(int $customerId, array $motoData): int {
        return $this->insertMotorcycleRecord($customerId, $motoData);
    }

    /**
     * Helper to insert a motorcycle.
     */
    private function insertMotorcycleRecord(int $customerId, array $moto): int {
        $sql = "
            INSERT INTO motorcycles (owner_id, make, model, year, color, plate_number, vin) 
            VALUES (:owner_id, :make, :model, :year, :color, :plate_number, :vin)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':owner_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':make', trim($moto['make']));
        $stmt->bindValue(':model', trim($moto['model']));
        $stmt->bindValue(':year', !empty($moto['year']) ? (int)$moto['year'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':color', !empty($moto['color']) ? trim($moto['color']) : null);
        $stmt->bindValue(':plate_number', !empty($moto['plate_number']) ? strtoupper(trim($moto['plate_number'])) : null);
        $stmt->bindValue(':vin', !empty($moto['vin']) ? strtoupper(trim($moto['vin'])) : null);
        $stmt->execute();

        return (int)$this->conn->lastInsertId();
    }

    /**
     * Remove a motorcycle.
     */
    public function deleteMotorcycle(int $motorcycleId): bool {
        $stmt = $this->conn->prepare("DELETE FROM motorcycles WHERE id = :id");
        $stmt->bindValue(':id', $motorcycleId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
