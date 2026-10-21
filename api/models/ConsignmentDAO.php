<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Consignment Payables & Supplier Liabilities Data Access Object
 * 
 * Manages tracking of sold consigned inventory, calculation of amounts owed to suppliers,
 * and settlement transactions.
 */
class ConsignmentDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Retrieve paginated and filtered list of consignment payables.
     *
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $limit
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function getPayables(array $filters = [], int $page = 1, int $limit = 50): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($filters['supplier_id'])) {
            $where[] = "cp.supplier_id = :supplier_id";
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "cp.status = :status";
            $params[':status'] = trim((string)$filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $where[] = "cp.created_at >= :start_date";
            $params[':start_date'] = trim((string)$filters['start_date']) . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where[] = "cp.created_at <= :end_date";
            $params[':end_date'] = trim((string)$filters['end_date']) . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = "(s.name LIKE :search OR p.name LIKE :search OR p.item_code LIKE :search)";
            $params[':search'] = '%' . trim((string)$filters['search']) . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countSql = "
            SELECT COUNT(*) 
            FROM consignment_payables cp
            JOIN suppliers s ON cp.supplier_id = s.id
            JOIN products p ON cp.product_id = p.id
            JOIN users u ON cp.recorded_by = u.id
            WHERE {$whereClause}
        ";
        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataSql = "
            SELECT 
                cp.id,
                cp.sale_id,
                cp.product_id,
                p.name AS product_name,
                p.item_code,
                p.unit,
                cp.supplier_id,
                s.name AS supplier_name,
                s.contact_person AS supplier_contact_person,
                s.contact_number AS supplier_contact_number,
                cp.quantity_sold,
                cp.cost_at_time_of_sale,
                cp.amount_owed,
                cp.status,
                cp.paid_date,
                cp.created_at,
                u.full_name AS recorded_by_name
            FROM consignment_payables cp
            JOIN suppliers s ON cp.supplier_id = s.id
            JOIN products p ON cp.product_id = p.id
            JOIN users u ON cp.recorded_by = u.id
            WHERE {$whereClause}
            ORDER BY (CASE WHEN cp.status = 'Owed' THEN 0 ELSE 1 END), cp.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $dataStmt = $this->conn->prepare($dataSql);
        foreach ($params as $k => $v) {
            $dataStmt->bindValue($k, $v);
        }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $payables = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $payables,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Compute aggregated vendor liability statistics and summary cards.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array {
        // Overall KPIs
        $kpiSql = "
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'Owed' THEN amount_owed ELSE 0 END), 0) AS total_owed,
                COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount_owed ELSE 0 END), 0) AS total_paid,
                SUM(CASE WHEN status = 'Owed' THEN 1 ELSE 0 END) AS pending_records_count,
                COUNT(DISTINCT supplier_id) AS total_vendors_count
            FROM consignment_payables
        ";
        $kpiStmt = $this->conn->query($kpiSql);
        $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_owed'            => 0,
            'total_paid'            => 0,
            'pending_records_count' => 0,
            'total_vendors_count'   => 0
        ];

        // Vendor Breakdown
        $vendorSql = "
            SELECT 
                s.id AS supplier_id,
                s.name AS supplier_name,
                s.contact_person,
                s.contact_number,
                s.email,
                COALESCE(SUM(CASE WHEN cp.status = 'Owed' THEN cp.amount_owed ELSE 0 END), 0) AS total_owed,
                COALESCE(SUM(CASE WHEN cp.status = 'Paid' THEN cp.amount_owed ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(cp.amount_owed), 0) AS lifetime_value,
                SUM(CASE WHEN cp.status = 'Owed' THEN 1 ELSE 0 END) AS pending_items_count,
                SUM(cp.quantity_sold) AS total_units_sold
            FROM suppliers s
            JOIN consignment_payables cp ON s.id = cp.supplier_id
            GROUP BY s.id, s.name, s.contact_person, s.contact_number, s.email
            ORDER BY total_owed DESC, s.name ASC
        ";
        $vendorStmt = $this->conn->query($vendorSql);
        $vendorBreakdown = $vendorStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'kpis'    => $kpis,
            'vendors' => $vendorBreakdown
        ];
    }

    /**
     * Atomically settle an individual consignment payable record.
     *
     * @param int $payableId
     * @param int $userId
     * @return bool
     */
    public function settlePayable(int $payableId, int $userId): bool {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("
                UPDATE consignment_payables 
                SET status = 'Paid', paid_date = NOW(), updated_at = NOW() 
                WHERE id = :id AND status = 'Owed'
            ");
            $stmt->bindValue(':id', $payableId, PDO::PARAM_INT);
            $stmt->execute();

            $affected = $stmt->rowCount();
            $this->conn->commit();

            return $affected > 0;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Atomically settle a batch of consignment payable records.
     *
     * @param array<int> $payableIds
     * @param int $userId
     * @return int Number of settled rows
     */
    public function settleBatch(array $payableIds, int $userId): int {
        if (empty($payableIds)) {
            return 0;
        }

        $intIds = array_map('intval', $payableIds);
        $placeholders = implode(',', array_fill(0, count($intIds), '?'));

        $this->conn->beginTransaction();

        try {
            $sql = "
                UPDATE consignment_payables 
                SET status = 'Paid', paid_date = NOW(), updated_at = NOW() 
                WHERE id IN ({$placeholders}) AND status = 'Owed'
            ";
            $stmt = $this->conn->prepare($sql);
            foreach ($intIds as $i => $id) {
                $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();

            $settledCount = $stmt->rowCount();
            $this->conn->commit();

            return $settledCount;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Atomically settle all outstanding payables for a specific supplier.
     *
     * @param int $supplierId
     * @param int $userId
     * @return int Number of settled records
     */
    public function settleBySupplier(int $supplierId, int $userId): int {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("
                UPDATE consignment_payables 
                SET status = 'Paid', paid_date = NOW(), updated_at = NOW() 
                WHERE supplier_id = :supplier_id AND status = 'Owed'
            ");
            $stmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_INT);
            $stmt->execute();

            $settledCount = $stmt->rowCount();
            $this->conn->commit();

            return $settledCount;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
