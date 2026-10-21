<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ProductDAO.php';

/**
 * Returns & Warranty Claims Data Access Object
 * 
 * Manages customer returns (restock, replacement, refunds) and vendor warranty dispatches.
 */
class ReturnDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Atomically process customer return against an original sale ticket.
     * Updates return records, optionally restocks inventory, and logs to movements ledger.
     *
     * @param int $saleId
     * @param array<int, array{product_id: int, quantity: int, price_at_time?: float}> $items
     * @param array<int, array{service_id: int, price_at_time?: float}> $services
     * @param string $reason
     * @param string $outcome 'Restocked' | 'Refunded' | 'Replaced' | 'Discarded'
     * @param int $userId
     * @return array<string, mixed>
     * @throws InvalidArgumentException|RuntimeException
     */
    public function processCustomerReturn(
        int $saleId,
        array $items,
        array $services,
        string $reason,
        string $outcome,
        int $userId
    ): array {
        $validOutcomes = ['Restocked', 'Refunded', 'Replaced', 'Discarded'];
        if (!in_array($outcome, $validOutcomes, true)) {
            throw new \InvalidArgumentException("Invalid return outcome disposition: {$outcome}");
        }

        if (empty($items) && empty($services)) {
            throw new \InvalidArgumentException("Return ticket must include at least one product item or shop service.");
        }

        $this->conn->beginTransaction();

        try {
            // Verify sale exists
            $saleStmt = $this->conn->prepare("SELECT id, total_amount, created_at FROM sales WHERE id = :id");
            $saleStmt->bindValue(':id', $saleId, PDO::PARAM_INT);
            $saleStmt->execute();
            $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new \RuntimeException("Original sale ticket #{$saleId} does not exist.");
            }

            // Calculate total refund amount from verified sold prices
            $totalRefund = 0.0;
            $verifiedItems = [];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $returnQty = (int)$item['quantity'];

                if ($returnQty <= 0) {
                    continue;
                }

                // Query price at time of sale from sale_items
                $itemStmt = $this->conn->prepare("
                    SELECT price_at_time, quantity 
                    FROM sale_items 
                    WHERE sale_id = :sale_id AND product_id = :product_id 
                    LIMIT 1
                ");
                $itemStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
                $itemStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $itemStmt->execute();
                $soldItem = $itemStmt->fetch(PDO::FETCH_ASSOC);

                $priceAtTime = $soldItem ? (float)$soldItem['price_at_time'] : (float)($item['price_at_time'] ?? 0.0);
                $itemRefund = $priceAtTime * $returnQty;
                $totalRefund += $itemRefund;

                $verifiedItems[] = [
                    'product_id'    => $productId,
                    'quantity'      => $returnQty,
                    'price_at_time' => $priceAtTime,
                    'total_refund'  => $itemRefund
                ];
            }

            $verifiedServices = [];
            foreach ($services as $svc) {
                $serviceId = (int)$svc['service_id'];

                $svcStmt = $this->conn->prepare("
                    SELECT price_at_time 
                    FROM sale_services 
                    WHERE sale_id = :sale_id AND service_id = :service_id 
                    LIMIT 1
                ");
                $svcStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
                $svcStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
                $svcStmt->execute();
                $soldSvc = $svcStmt->fetch(PDO::FETCH_ASSOC);

                $priceAtTime = $soldSvc ? (float)$soldSvc['price_at_time'] : (float)($svc['price_at_time'] ?? 0.0);
                $totalRefund += $priceAtTime;

                $verifiedServices[] = [
                    'service_id'    => $serviceId,
                    'price_at_time' => $priceAtTime
                ];
            }

            // 1. Insert parent return record
            $retSql = "
                INSERT INTO returns (
                    original_sale_id, reason, outcome, total_refund_amount, recorded_by, created_at
                ) VALUES (
                    :original_sale_id, :reason, :outcome, :total_refund_amount, :recorded_by, NOW()
                )
            ";
            $retStmt = $this->conn->prepare($retSql);
            $retStmt->bindValue(':original_sale_id', $saleId, PDO::PARAM_INT);
            $retStmt->bindValue(':reason', trim($reason));
            $retStmt->bindValue(':outcome', $outcome);
            $retStmt->bindValue(':total_refund_amount', $totalRefund);
            $retStmt->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
            $retStmt->execute();

            $returnId = (int)$this->conn->lastInsertId();

            // 2. Insert return_items
            $itemInsert = $this->conn->prepare("
                INSERT INTO return_items (return_id, product_id, quantity, price_at_time) 
                VALUES (:return_id, :product_id, :quantity, :price_at_time)
            ");
            foreach ($verifiedItems as $vi) {
                $itemInsert->bindValue(':return_id', $returnId, PDO::PARAM_INT);
                $itemInsert->bindValue(':product_id', $vi['product_id'], PDO::PARAM_INT);
                $itemInsert->bindValue(':quantity', $vi['quantity'], PDO::PARAM_INT);
                $itemInsert->bindValue(':price_at_time', $vi['price_at_time']);
                $itemInsert->execute();
            }

            // 3. Insert return_services
            $svcInsert = $this->conn->prepare("
                INSERT INTO return_services (return_id, service_id, price_at_time) 
                VALUES (:return_id, :service_id, :price_at_time)
            ");
            foreach ($verifiedServices as $vs) {
                $svcInsert->bindValue(':return_id', $returnId, PDO::PARAM_INT);
                $svcInsert->bindValue(':service_id', $vs['service_id'], PDO::PARAM_INT);
                $svcInsert->bindValue(':price_at_time', $vs['price_at_time']);
                $svcInsert->execute();
            }

            // 4. Physical Restock & Ledger Logging if outcome is Restocked or Replaced
            $shouldRestock = ($outcome === 'Restocked' || $outcome === 'Replaced');

            if ($shouldRestock) {
                $updStock = $this->conn->prepare("
                    UPDATE products 
                    SET quantity = :quantity, stock_status = :stock_status, updated_at = NOW() 
                    WHERE id = :id
                ");

                $logMov = $this->conn->prepare("
                    INSERT INTO movements (
                        product_id, type, quantity_change, stock_before, stock_after, 
                        reference_id, notes, recorded_by, created_at
                    ) VALUES (
                        :product_id, 'RETURN', :quantity_change, :stock_before, :stock_after, 
                        :reference_id, :notes, :recorded_by, NOW()
                    )
                ");

                foreach ($verifiedItems as $vi) {
                    // Lock product
                    $pStmt = $this->conn->prepare("SELECT id, name, item_code, quantity FROM products WHERE id = :id FOR UPDATE");
                    $pStmt->bindValue(':id', $vi['product_id'], PDO::PARAM_INT);
                    $pStmt->execute();
                    $prod = $pStmt->fetch(PDO::FETCH_ASSOC);

                    if ($prod) {
                        $stockBefore = (int)$prod['quantity'];
                        $stockAfter = $stockBefore + $vi['quantity'];
                        $newStatus = ProductDAO::calculateStockStatus($stockAfter);

                        $updStock->bindValue(':quantity', $stockAfter, PDO::PARAM_INT);
                        $updStock->bindValue(':stock_status', $newStatus);
                        $updStock->bindValue(':id', $vi['product_id'], PDO::PARAM_INT);
                        $updStock->execute();

                        $refCode = 'RET-' . $returnId;
                        $note = "Customer return ({$outcome}) for Sale #{$saleId}: +{$vi['quantity']} units of {$prod['name']}";

                        $logMov->bindValue(':product_id', $vi['product_id'], PDO::PARAM_INT);
                        $logMov->bindValue(':quantity_change', $vi['quantity'], PDO::PARAM_INT);
                        $logMov->bindValue(':stock_before', $stockBefore, PDO::PARAM_INT);
                        $logMov->bindValue(':stock_after', $stockAfter, PDO::PARAM_INT);
                        $logMov->bindValue(':reference_id', $refCode);
                        $logMov->bindValue(':notes', $note);
                        $logMov->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
                        $logMov->execute();
                    }
                }
            }

            $this->conn->commit();

            return [
                'return_id'           => $returnId,
                'original_sale_id'    => $saleId,
                'outcome'             => $outcome,
                'total_refund_amount' => $totalRefund,
                'items_count'         => count($verifiedItems),
                'services_count'      => count($verifiedServices),
                'restocked'           => $shouldRestock
            ];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Atomically process return of inventory goods back to supplier/distributor.
     * Decrements physical stock or consigned stock and logs audit movement.
     *
     * @param int $supplierId
     * @param array<int, array{product_id: int, quantity: int, reason: string, was_consigned?: bool}> $items
     * @param string $notes
     * @param int $userId
     * @return array<string, mixed>
     * @throws InvalidArgumentException|RuntimeException
     */
    public function processSupplierReturn(int $supplierId, array $items, string $notes, int $userId): array {
        if (empty($items)) {
            throw new \InvalidArgumentException("Supplier return must contain at least one item.");
        }

        $this->conn->beginTransaction();

        try {
            // Verify supplier exists
            $supStmt = $this->conn->prepare("SELECT id, name FROM suppliers WHERE id = :id");
            $supStmt->bindValue(':id', $supplierId, PDO::PARAM_INT);
            $supStmt->execute();
            $supplier = $supStmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                throw new \RuntimeException("Supplier ID {$supplierId} not found.");
            }

            // 1. Insert into supplier_returns
            $srSql = "
                INSERT INTO supplier_returns (
                    supplier_id, return_date, notes, recorded_by, created_at
                ) VALUES (
                    :supplier_id, NOW(), :notes, :recorded_by, NOW()
                )
            ";
            $srStmt = $this->conn->prepare($srSql);
            $srStmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_INT);
            $srStmt->bindValue(':notes', trim($notes));
            $srStmt->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
            $srStmt->execute();

            $supReturnId = (int)$this->conn->lastInsertId();

            // 2. Insert items, deduct inventory, and record movements
            $itemSql = "
                INSERT INTO supplier_return_items (
                    supplier_return_id, product_id, quantity, reason, was_consigned
                ) VALUES (
                    :supplier_return_id, :product_id, :quantity, :reason, :was_consigned
                )
            ";
            $itemStmt = $this->conn->prepare($itemSql);

            $updStock = $this->conn->prepare("
                UPDATE products 
                SET quantity = :quantity, consigned_stock = :consigned_stock, stock_status = :stock_status, updated_at = NOW() 
                WHERE id = :id
            ");

            $logMov = $this->conn->prepare("
                INSERT INTO movements (
                    product_id, type, quantity_change, stock_before, stock_after, 
                    reference_id, notes, recorded_by, created_at
                ) VALUES (
                    :product_id, 'RETURN', :quantity_change, :stock_before, :stock_after, 
                    :reference_id, :notes, :recorded_by, NOW()
                )
            ");

            $processedCount = 0;

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $reason = $item['reason'] ?? 'Defective';
                $wasConsigned = !empty($item['was_consigned']) ? 1 : 0;

                if ($qty <= 0) {
                    continue;
                }

                // Insert line record
                $itemStmt->bindValue(':supplier_return_id', $supReturnId, PDO::PARAM_INT);
                $itemStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $itemStmt->bindValue(':quantity', $qty, PDO::PARAM_INT);
                $itemStmt->bindValue(':reason', $reason);
                $itemStmt->bindValue(':was_consigned', $wasConsigned, PDO::PARAM_INT);
                $itemStmt->execute();

                // Lock product row
                $prodStmt = $this->conn->prepare("
                    SELECT id, name, item_code, quantity, consigned_stock 
                    FROM products 
                    WHERE id = :id 
                    FOR UPDATE
                ");
                $prodStmt->bindValue(':id', $productId, PDO::PARAM_INT);
                $prodStmt->execute();
                $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);

                if (!$prod) {
                    throw new \RuntimeException("Product ID {$productId} not found.");
                }

                $stockBefore = (int)$prod['quantity'];
                $consignedBefore = (int)$prod['consigned_stock'];

                $stockAfter = max(0, $stockBefore - $qty);
                $consignedAfter = $wasConsigned ? max(0, $consignedBefore - $qty) : $consignedBefore;
                $newStatus = ProductDAO::calculateStockStatus($stockAfter);

                $updStock->bindValue(':quantity', $stockAfter, PDO::PARAM_INT);
                $updStock->bindValue(':consigned_stock', $consignedAfter, PDO::PARAM_INT);
                $updStock->bindValue(':stock_status', $newStatus);
                $updStock->bindValue(':id', $productId, PDO::PARAM_INT);
                $updStock->execute();

                // Log movement ledger
                $refCode = 'SRET-' . $supReturnId;
                $movNotes = "Supplier return dispatch to {$supplier['name']} - Reason: {$reason}";

                $logMov->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $logMov->bindValue(':quantity_change', -$qty, PDO::PARAM_INT);
                $logMov->bindValue(':stock_before', $stockBefore, PDO::PARAM_INT);
                $logMov->bindValue(':stock_after', $stockAfter, PDO::PARAM_INT);
                $logMov->bindValue(':reference_id', $refCode);
                $logMov->bindValue(':notes', $movNotes);
                $logMov->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
                $logMov->execute();

                $processedCount++;
            }

            $this->conn->commit();

            return [
                'supplier_return_id' => $supReturnId,
                'supplier_id'        => $supplierId,
                'supplier_name'      => $supplier['name'],
                'items_count'        => $processedCount
            ];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve paginated list of customer returns.
     */
    public function getCustomerReturns(int $page = 1, int $limit = 20, ?string $search = null): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $where[] = "(r.reason LIKE :search OR r.outcome LIKE :search OR u.full_name LIKE :search OR c.name LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countSql = "
            SELECT COUNT(*) 
            FROM returns r
            JOIN sales s ON r.original_sale_id = s.id
            JOIN users u ON r.recorded_by = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE {$whereClause}
        ";
        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataSql = "
            SELECT 
                r.id,
                r.original_sale_id,
                r.reason,
                r.outcome,
                r.total_refund_amount,
                r.created_at,
                u.full_name AS recorded_by_name,
                c.name AS customer_name,
                COUNT(DISTINCT ri.id) AS items_count,
                COUNT(DISTINCT rs.id) AS services_count
            FROM returns r
            JOIN sales s ON r.original_sale_id = s.id
            JOIN users u ON r.recorded_by = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN return_items ri ON r.id = ri.return_id
            LEFT JOIN return_services rs ON r.id = rs.return_id
            WHERE {$whereClause}
            GROUP BY r.id, r.original_sale_id, r.reason, r.outcome, r.total_refund_amount, r.created_at, u.full_name, c.name
            ORDER BY r.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $dataStmt = $this->conn->prepare($dataSql);
        foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $returns = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $returns,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Retrieve paginated list of supplier return dispatches.
     */
    public function getSupplierReturns(int $page = 1, int $limit = 20, ?string $search = null): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $where[] = "(s.name LIKE :search OR sr.notes LIKE :search OR u.full_name LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countSql = "
            SELECT COUNT(*) 
            FROM supplier_returns sr
            JOIN suppliers s ON sr.supplier_id = s.id
            JOIN users u ON sr.recorded_by = u.id
            WHERE {$whereClause}
        ";
        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $k => $v) $countStmt->bindValue($k, $v);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataSql = "
            SELECT 
                sr.id,
                sr.supplier_id,
                s.name AS supplier_name,
                sr.return_date,
                sr.notes,
                sr.created_at,
                u.full_name AS recorded_by_name,
                COUNT(sri.id) AS items_count,
                SUM(sri.quantity) AS total_units_returned
            FROM supplier_returns sr
            JOIN suppliers s ON sr.supplier_id = s.id
            JOIN users u ON sr.recorded_by = u.id
            LEFT JOIN supplier_return_items sri ON sr.id = sri.supplier_return_id
            WHERE {$whereClause}
            GROUP BY sr.id, sr.supplier_id, s.name, sr.return_date, sr.notes, sr.created_at, u.full_name
            ORDER BY sr.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $dataStmt = $this->conn->prepare($dataSql);
        foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $returns = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $returns,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Look up sale ticket details and line items eligible for return.
     *
     * @param int $saleId
     * @return array<string, mixed>|false
     */
    public function getSaleForReturn(int $saleId): array|false {
        $sql = "
            SELECT 
                s.id, s.total_amount, s.created_at,
                c.name AS customer_name, c.phone AS customer_phone,
                m.plate_number, m.make, m.model
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN motorcycles m ON s.motorcycle_id = m.id
            WHERE s.id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            return false;
        }

        // Fetch purchased items
        $itemSql = "
            SELECT 
                si.product_id,
                p.name AS product_name,
                p.item_code,
                p.unit,
                si.quantity AS quantity_sold,
                si.price_at_time,
                COALESCE(SUM(ri.quantity), 0) AS quantity_already_returned,
                (si.quantity - COALESCE(SUM(ri.quantity), 0)) AS quantity_available_for_return
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            LEFT JOIN returns r ON r.original_sale_id = si.sale_id
            LEFT JOIN return_items ri ON ri.return_id = r.id AND ri.product_id = si.product_id
            WHERE si.sale_id = :sale_id
            GROUP BY si.product_id, p.name, p.item_code, p.unit, si.quantity, si.price_at_time
        ";
        $itemStmt = $this->conn->prepare($itemSql);
        $itemStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $itemStmt->execute();
        $sale['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch services
        $svcSql = "
            SELECT 
                ss.service_id,
                srv.name AS service_name,
                ss.price_at_time
            FROM sale_services ss
            JOIN services srv ON ss.service_id = srv.id
            WHERE ss.sale_id = :sale_id
        ";
        $svcStmt = $this->conn->prepare($svcSql);
        $svcStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $svcStmt->execute();
        $sale['services'] = $svcStmt->fetchAll(PDO::FETCH_ASSOC);

        return $sale;
    }
}
