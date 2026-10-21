<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Product Data Access Object
 * 
 * Manages product inventory, dynamic stock status calculation,
 * filtering/pagination, and supplier cost associations.
 */
class ProductDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Compute standardized stock status based on current quantity.
     *
     * Rules:
     * - Quantity <= 0  => 'Out of Stock'
     * - Quantity <= 5  => 'Critical'
     * - Quantity <= 15 => 'Low'
     * - Quantity > 15  => 'Healthy'
     */
    public static function calculateStockStatus(int $quantity): string {
        if ($quantity <= 0) {
            return 'Out of Stock';
        }
        if ($quantity <= 5) {
            return 'Critical';
        }
        if ($quantity <= 15) {
            return 'Low';
        }
        return 'Healthy';
    }

    /**
     * Filtered and paginated product listing.
     *
     * @param array{
     *   search?: ?string,
     *   category_id?: ?int,
     *   brand_id?: ?int,
     *   stock_status?: ?string,
     *   status?: ?string
     * } $filters
     * @param int $page
     * @param int $limit
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function getFiltered(array $filters = [], int $page = 1, int $limit = 20): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(p.name LIKE :search OR p.item_code LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['category_id'])) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = (int)$filters['category_id'];
        }

        if (!empty($filters['brand_id'])) {
            $where[] = "p.brand_id = :brand_id";
            $params[':brand_id'] = (int)$filters['brand_id'];
        }

        if (!empty($filters['stock_status'])) {
            $where[] = "p.stock_status = :stock_status";
            $params[':stock_status'] = trim($filters['stock_status']);
        }

        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = trim($filters['status']);
        }

        $whereClause = implode(" AND ", $where);

        // 1. Total Count Query
        $countSql = "SELECT COUNT(*) FROM products p WHERE {$whereClause}";
        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // 2. Data Query with Joins
        $dataSql = "
            SELECT 
                p.id, 
                p.item_code, 
                p.name, 
                p.category_id, 
                c.name AS category_name,
                p.brand_id, 
                b.name AS brand_name,
                p.price, 
                p.default_cost, 
                p.quantity, 
                p.consigned_stock, 
                p.max_stock, 
                p.stock_status, 
                p.status, 
                p.unit, 
                p.image, 
                p.is_serialized, 
                p.created_at, 
                p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE {$whereClause}
            ORDER BY p.name ASC
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $this->conn->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $products = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $products,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Backward-compatible helper for full catalog listing.
     */
    public function getAllProducts(): array {
        $result = $this->getFiltered([], 1, 1000);
        return $result['data'];
    }

    /**
     * Retrieve a product by ID, including supplier costs and serials.
     *
     * @param int $id
     * @return array<string, mixed>|false
     */
    public function getProductById(int $id): array|false {
        $sql = "
            SELECT 
                p.*, 
                c.name AS category_name, 
                b.name AS brand_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return false;
        }

        // Fetch supplier cost mappings with supplier details
        $costSql = "
            SELECT 
                psc.id, 
                psc.supplier_id, 
                s.name AS supplier_name, 
                psc.cost, 
                psc.note 
            FROM product_supplier_costs psc
            JOIN suppliers s ON psc.supplier_id = s.id
            WHERE psc.product_id = :id
            ORDER BY s.name ASC
        ";
        $costStmt = $this->conn->prepare($costSql);
        $costStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $costStmt->execute();
        $product['supplier_costs'] = $costStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch available serial numbers if serialized
        if ((int)$product['is_serialized'] === 1) {
            $serialStmt = $this->conn->prepare("SELECT id, serial_number, status, date_received FROM serialized_items WHERE product_id = :id ORDER BY id ASC");
            $serialStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $serialStmt->execute();
            $product['serial_numbers'] = $serialStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $product['serial_numbers'] = [];
        }

        return $product;
    }

    /**
     * Check if item_code is taken.
     */
    public function itemCodeExists(string $code, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM products WHERE item_code = :code";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':code', trim($code));
        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Create product with automatic stock_status and supplier cost pivot mapping.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function createProduct(array $data): int {
        $this->conn->beginTransaction();

        try {
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;
            $stockStatus = self::calculateStockStatus($quantity);

            $sql = "
                INSERT INTO products (
                    item_code, name, category_id, brand_id, price, default_cost, 
                    quantity, consigned_stock, max_stock, stock_status, status, 
                    unit, image, is_serialized
                ) VALUES (
                    :item_code, :name, :category_id, :brand_id, :price, :default_cost, 
                    :quantity, :consigned_stock, :max_stock, :stock_status, :status, 
                    :unit, :image, :is_serialized
                )
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':item_code', trim($data['item_code']));
            $stmt->bindValue(':name', trim($data['name']));
            $stmt->bindValue(':category_id', (int)$data['category_id'], PDO::PARAM_INT);
            $stmt->bindValue(':brand_id', (int)$data['brand_id'], PDO::PARAM_INT);
            $stmt->bindValue(':price', (float)$data['price']);
            $stmt->bindValue(':default_cost', (float)($data['default_cost'] ?? 0.00));
            $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindValue(':consigned_stock', (int)($data['consigned_stock'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':max_stock', (int)($data['max_stock'] ?? 100), PDO::PARAM_INT);
            $stmt->bindValue(':stock_status', $stockStatus);
            $stmt->bindValue(':status', $data['status'] ?? 'active');
            $stmt->bindValue(':unit', $data['unit'] ?? 'pc');
            $stmt->bindValue(':image', !empty($data['image']) ? trim($data['image']) : null);
            $stmt->bindValue(':is_serialized', !empty($data['is_serialized']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();

            $productId = (int)$this->conn->lastInsertId();

            // Insert supplier cost pivot mappings if provided
            if (!empty($data['supplier_costs']) && is_array($data['supplier_costs'])) {
                $this->saveSupplierCosts($productId, $data['supplier_costs']);
            }

            $this->conn->commit();
            return $productId;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Update product details, re-evaluate stock_status, and synchronize supplier costs.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateProduct(int $id, array $data): bool {
        $this->conn->beginTransaction();

        try {
            // Determine current quantity if not passed
            $current = $this->getProductById($id);
            if (!$current) {
                $this->conn->rollBack();
                return false;
            }

            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : (int)$current['quantity'];
            $stockStatus = self::calculateStockStatus($quantity);

            $sql = "
                UPDATE products SET 
                    item_code = :item_code, 
                    name = :name, 
                    category_id = :category_id, 
                    brand_id = :brand_id, 
                    price = :price, 
                    default_cost = :default_cost, 
                    quantity = :quantity, 
                    consigned_stock = :consigned_stock, 
                    max_stock = :max_stock, 
                    stock_status = :stock_status, 
                    status = :status, 
                    unit = :unit, 
                    image = :image, 
                    is_serialized = :is_serialized
                WHERE id = :id
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':item_code', trim($data['item_code']));
            $stmt->bindValue(':name', trim($data['name']));
            $stmt->bindValue(':category_id', (int)$data['category_id'], PDO::PARAM_INT);
            $stmt->bindValue(':brand_id', (int)$data['brand_id'], PDO::PARAM_INT);
            $stmt->bindValue(':price', (float)$data['price']);
            $stmt->bindValue(':default_cost', (float)($data['default_cost'] ?? $current['default_cost']));
            $stmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindValue(':consigned_stock', (int)($data['consigned_stock'] ?? $current['consigned_stock']), PDO::PARAM_INT);
            $stmt->bindValue(':max_stock', (int)($data['max_stock'] ?? $current['max_stock']), PDO::PARAM_INT);
            $stmt->bindValue(':stock_status', $stockStatus);
            $stmt->bindValue(':status', $data['status'] ?? $current['status']);
            $stmt->bindValue(':unit', $data['unit'] ?? $current['unit']);
            $stmt->bindValue(':image', !empty($data['image']) ? trim($data['image']) : $current['image']);
            $stmt->bindValue(':is_serialized', isset($data['is_serialized']) ? ((int)$data['is_serialized'] ? 1 : 0) : (int)$current['is_serialized'], PDO::PARAM_INT);
            $stmt->execute();

            // Synchronize supplier costs if explicitly provided in update
            if (isset($data['supplier_costs']) && is_array($data['supplier_costs'])) {
                $this->saveSupplierCosts($id, $data['supplier_costs']);
            }

            $this->conn->commit();
            return true;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a product after verifying referential integrity.
     */
    public function deleteProduct(int $id): bool {
        // Check if product is in sales
        $chkStmt = $this->conn->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = :id");
        $chkStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $chkStmt->execute();
        if ((int)$chkStmt->fetchColumn() > 0) {
            throw new \RuntimeException("Cannot delete product because it has associated sales records.");
        }

        $stmt = $this->conn->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Synchronize product_supplier_costs pivot entries.
     *
     * @param int $productId
     * @param array<int, array{supplier_id: int, cost: float|int|string, note?: ?string}> $costs
     */
    private function saveSupplierCosts(int $productId, array $costs): void {
        // Clear existing mappings
        $del = $this->conn->prepare("DELETE FROM product_supplier_costs WHERE product_id = :id");
        $del->bindValue(':id', $productId, PDO::PARAM_INT);
        $del->execute();

        $ins = $this->conn->prepare("
            INSERT INTO product_supplier_costs (product_id, supplier_id, cost, note) 
            VALUES (:product_id, :supplier_id, :cost, :note)
        ");

        foreach ($costs as $entry) {
            if (!empty($entry['supplier_id']) && isset($entry['cost'])) {
                $ins->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $ins->bindValue(':supplier_id', (int)$entry['supplier_id'], PDO::PARAM_INT);
                $ins->bindValue(':cost', (float)$entry['cost']);
                $ins->bindValue(':note', !empty($entry['note']) ? trim($entry['note']) : null);
                $ins->execute();
            }
        }
    }
}
