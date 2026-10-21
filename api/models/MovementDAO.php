<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ProductDAO.php';

/**
 * Stock Movement Ledger Data Access Object
 * 
 * Provides an immutable audit trail of all inventory alterations
 * (Sales, PO Deliveries, Adjustments, Customer Returns, Supplier Returns).
 */
class MovementDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve paginated and filtered stock movements ledger.
     *
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $limit
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function getMovements(array $filters = [], int $page = 1, int $limit = 20): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[] = "m.product_id = :product_id";
            $params[':product_id'] = (int)$filters['product_id'];
        }

        if (!empty($filters['type'])) {
            $where[] = "m.type = :type";
            $params[':type'] = trim((string)$filters['type']);
        }

        if (!empty($filters['start_date'])) {
            $where[] = "m.created_at >= :start_date";
            $params[':start_date'] = trim((string)$filters['start_date']) . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where[] = "m.created_at <= :end_date";
            $params[':end_date'] = trim((string)$filters['end_date']) . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.name LIKE :search OR p.item_code LIKE :search OR m.reference_id LIKE :search OR m.notes LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereClause = implode(" AND ", $where);

        // Count total matching records
        $countSql = "
            SELECT COUNT(*) 
            FROM movements m
            JOIN products p ON m.product_id = p.id
            JOIN users u ON m.recorded_by = u.id
            WHERE {$whereClause}
        ";
        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Fetch paginated movement records
        $dataSql = "
            SELECT 
                m.id,
                m.product_id,
                p.name AS product_name,
                p.item_code,
                p.unit,
                m.type,
                m.quantity_change,
                m.stock_before,
                m.stock_after,
                m.reference_id,
                m.notes,
                m.recorded_by,
                u.full_name AS recorded_by_name,
                u.username AS recorded_by_username,
                m.created_at
            FROM movements m
            JOIN products p ON m.product_id = p.id
            JOIN users u ON m.recorded_by = u.id
            WHERE {$whereClause}
            ORDER BY m.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $this->conn->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $records = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $records,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Atomically adjust a product's physical stock count, update stock_status,
     * and generate an immutable ledger entry in the movements table.
     *
     * @param int $productId
     * @param int $newQuantity
     * @param string $notes
     * @param int $userId
     * @return array<string, mixed> Updated product and adjustment details
     * @throws InvalidArgumentException|RuntimeException
     */
    public function createAdjustment(int $productId, int $newQuantity, string $notes, int $userId): array {
        if ($newQuantity < 0) {
            throw new \InvalidArgumentException("Physical inventory stock quantity cannot be negative.");
        }

        $this->conn->beginTransaction();

        try {
            // Lock product record for update
            $prodStmt = $this->conn->prepare("
                SELECT id, name, item_code, quantity, unit, stock_status 
                FROM products 
                WHERE id = :id 
                FOR UPDATE
            ");
            $prodStmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $prodStmt->execute();
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new \RuntimeException("Product ID {$productId} not found.");
            }

            $stockBefore = (int)$product['quantity'];
            $delta = $newQuantity - $stockBefore;

            if ($delta === 0) {
                $this->conn->rollBack();
                return [
                    'product_id'      => $productId,
                    'stock_before'    => $stockBefore,
                    'stock_after'     => $stockBefore,
                    'quantity_change' => 0,
                    'message'         => 'No stock change detected.'
                ];
            }

            // Recalculate dynamic stock status
            $newStatus = ProductDAO::calculateStockStatus($newQuantity);

            // Update physical quantity in products table
            $updStmt = $this->conn->prepare("
                UPDATE products 
                SET quantity = :quantity, stock_status = :stock_status, updated_at = NOW() 
                WHERE id = :id
            ");
            $updStmt->bindValue(':quantity', $newQuantity, PDO::PARAM_INT);
            $updStmt->bindValue(':stock_status', $newStatus);
            $updStmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $updStmt->execute();

            // Generate unique adjustment reference code
            $referenceId = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $formattedNotes = trim($notes);
            if ($formattedNotes === '') {
                $direction = $delta > 0 ? "manual increment (+{$delta})" : "manual deduction ({$delta})";
                $formattedNotes = "Physical inventory count adjustment: {$direction}";
            }

            // Insert into immutable stock movements ledger
            $movStmt = $this->conn->prepare("
                INSERT INTO movements (
                    product_id, type, quantity_change, stock_before, stock_after, 
                    reference_id, notes, recorded_by, created_at
                ) VALUES (
                    :product_id, 'ADJUSTMENT', :quantity_change, :stock_before, :stock_after, 
                    :reference_id, :notes, :recorded_by, NOW()
                )
            ");
            $movStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $movStmt->bindValue(':quantity_change', $delta, PDO::PARAM_INT);
            $movStmt->bindValue(':stock_before', $stockBefore, PDO::PARAM_INT);
            $movStmt->bindValue(':stock_after', $newQuantity, PDO::PARAM_INT);
            $movStmt->bindValue(':reference_id', $referenceId);
            $movStmt->bindValue(':notes', $formattedNotes);
            $movStmt->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
            $movStmt->execute();

            $movementId = (int)$this->conn->lastInsertId();

            $this->conn->commit();

            return [
                'movement_id'     => $movementId,
                'reference_id'    => $referenceId,
                'product_id'      => $productId,
                'product_name'    => $product['name'],
                'item_code'       => $product['item_code'],
                'stock_before'    => $stockBefore,
                'stock_after'     => $newQuantity,
                'quantity_change' => $delta,
                'stock_status'    => $newStatus,
                'notes'           => $formattedNotes
            ];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Compute aggregated inventory movement metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array {
        $sql = "
            SELECT 
                COUNT(*) AS total_movements,
                SUM(CASE WHEN type = 'ADJUSTMENT' THEN 1 ELSE 0 END) AS total_adjustments,
                SUM(CASE WHEN type = 'SALE' THEN 1 ELSE 0 END) AS total_sales,
                SUM(CASE WHEN type LIKE '%DELIVERY%' THEN 1 ELSE 0 END) AS total_deliveries,
                SUM(CASE WHEN type = 'RETURN' THEN 1 ELSE 0 END) AS total_returns,
                COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) AS units_received,
                COALESCE(SUM(CASE WHEN quantity_change < 0 THEN ABS(quantity_change) ELSE 0 END), 0) AS units_dispensed
            FROM movements
        ";
        $stmt = $this->conn->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
