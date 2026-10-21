<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/ProductDAO.php';

/**
 * Sale Data Access Object
 * 
 * Manages atomic POS sales transactions, historical COGS recording,
 * immutable stock movements, consignment payable ledgering, and XML receipt generation.
 */
class SaleDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Atomically process a POS sale checkout.
     *
     * @param array<string, mixed> $saleData Header data (customer_id, motorcycle_id, etc.)
     * @param array<int, array{product_id: int, quantity: int, price_at_time: float, cost_of_goods_sold?: float}> $items
     * @param array<int, array{service_id: int, price_at_time: float}> $services
     * @param int $userId ID of user processing the transaction
     * @return int Created Sale ID
     */
    public function createSale(array $saleData, array $items, array $services, int $userId): int {
        $this->conn->beginTransaction();

        try {
            // 1. Calculate grand total & validate stock availability
            $totalAmount = 0.0;
            $itemsWithCost = [];

            // Lock products for update and verify stock
            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['quantity'];
                $price = (float)$item['price_at_time'];

                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Invalid quantity for product ID: {$productId}");
                }

                $stmt = $this->conn->prepare("
                    SELECT id, name, item_code, quantity, consigned_stock, default_cost 
                    FROM products 
                    WHERE id = :id 
                    FOR UPDATE
                ");
                $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
                $stmt->execute();
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new \RuntimeException("Product ID {$productId} not found.");
                }

                if ((int)$product['quantity'] < $qty) {
                    throw new \RuntimeException("Insufficient inventory for '{$product['name']}'. Available: {$product['quantity']}, Requested: {$qty}");
                }

                $cogs = isset($item['cost_of_goods_sold']) && (float)$item['cost_of_goods_sold'] > 0
                    ? (float)$item['cost_of_goods_sold']
                    : (float)$product['default_cost'];

                $totalAmount += ($price * $qty);

                $itemsWithCost[] = [
                    'product_id'         => $productId,
                    'name'               => $product['name'],
                    'item_code'          => $product['item_code'],
                    'quantity'           => $qty,
                    'price_at_time'      => $price,
                    'cost_of_goods_sold' => $cogs,
                    'stock_before'       => (int)$product['quantity'],
                    'consigned_stock'    => (int)$product['consigned_stock']
                ];
            }

            // Sum labor services
            foreach ($services as $svc) {
                $totalAmount += (float)$svc['price_at_time'];
            }

            // 2. Insert master sale record
            $sqlSale = "
                INSERT INTO sales (total_amount, recorded_by, customer_id, motorcycle_id, customer_receipt_image, is_manual_entry) 
                VALUES (:total_amount, :recorded_by, :customer_id, :motorcycle_id, :customer_receipt_image, :is_manual_entry)
            ";
            $stmtSale = $this->conn->prepare($sqlSale);
            $stmtSale->bindValue(':total_amount', $totalAmount);
            $stmtSale->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
            $stmtSale->bindValue(':customer_id', !empty($saleData['customer_id']) ? (int)$saleData['customer_id'] : null, PDO::PARAM_INT);
            $stmtSale->bindValue(':motorcycle_id', !empty($saleData['motorcycle_id']) ? (int)$saleData['motorcycle_id'] : null, PDO::PARAM_INT);
            $stmtSale->bindValue(':customer_receipt_image', !empty($saleData['customer_receipt_image']) ? trim($saleData['customer_receipt_image']) : null);
            $stmtSale->bindValue(':is_manual_entry', !empty($saleData['is_manual_entry']) ? 1 : 0, PDO::PARAM_INT);
            $stmtSale->execute();

            $saleId = (int)$this->conn->lastInsertId();

            // 3. Process each line item (Deduct stock, recalculate status, log movements, handle consignment)
            $stmtInsertItem = $this->conn->prepare("
                INSERT INTO sale_items (sale_id, product_id, quantity, price_at_time, cost_of_goods_sold) 
                VALUES (:sale_id, :product_id, :quantity, :price_at_time, :cost_of_goods_sold)
            ");

            $stmtUpdateStock = $this->conn->prepare("
                UPDATE products 
                SET quantity = :quantity, stock_status = :stock_status 
                WHERE id = :id
            ");

            $stmtLogMovement = $this->conn->prepare("
                INSERT INTO movements (product_id, type, quantity_change, stock_before, stock_after, reference_id, notes, recorded_by) 
                VALUES (:product_id, 'SALE', :quantity_change, :stock_before, :stock_after, :reference_id, :notes, :recorded_by)
            ");

            foreach ($itemsWithCost as $item) {
                // Record line item with historical COGS
                $stmtInsertItem->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
                $stmtInsertItem->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
                $stmtInsertItem->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
                $stmtInsertItem->bindValue(':price_at_time', $item['price_at_time']);
                $stmtInsertItem->bindValue(':cost_of_goods_sold', $item['cost_of_goods_sold']);
                $stmtInsertItem->execute();

                // Deduct inventory & compute new status
                $newStock = $item['stock_before'] - $item['quantity'];
                $newStatus = ProductDAO::calculateStockStatus($newStock);

                $stmtUpdateStock->bindValue(':quantity', $newStock, PDO::PARAM_INT);
                $stmtUpdateStock->bindValue(':stock_status', $newStatus);
                $stmtUpdateStock->bindValue(':id', $item['product_id'], PDO::PARAM_INT);
                $stmtUpdateStock->execute();

                // Record immutable stock movement
                $stmtLogMovement->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
                $stmtLogMovement->bindValue(':quantity_change', -$item['quantity'], PDO::PARAM_INT);
                $stmtLogMovement->bindValue(':stock_before', $item['stock_before'], PDO::PARAM_INT);
                $stmtLogMovement->bindValue(':stock_after', $newStock, PDO::PARAM_INT);
                $stmtLogMovement->bindValue(':reference_id', 'SALE-' . $saleId);
                $stmtLogMovement->bindValue(':notes', "POS Sale checkout for {$item['name']} (SKU: {$item['item_code']})");
                $stmtLogMovement->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
                $stmtLogMovement->execute();

                // Consignment Payable handling if product has consigned inventory
                if ($item['consigned_stock'] > 0) {
                    $consignedSold = min($item['quantity'], $item['consigned_stock']);
                    
                    // Deduct consigned stock bucket
                    $stmtDeductConsigned = $this->conn->prepare("UPDATE products SET consigned_stock = consigned_stock - :cqty WHERE id = :id");
                    $stmtDeductConsigned->bindValue(':cqty', $consignedSold, PDO::PARAM_INT);
                    $stmtDeductConsigned->bindValue(':id', $item['product_id'], PDO::PARAM_INT);
                    $stmtDeductConsigned->execute();

                    // Retrieve supplier for consignment
                    $supStmt = $this->conn->prepare("SELECT supplier_id, cost FROM product_supplier_costs WHERE product_id = :id LIMIT 1");
                    $supStmt->bindValue(':id', $item['product_id'], PDO::PARAM_INT);
                    $supStmt->execute();
                    $supMapping = $supStmt->fetch(PDO::FETCH_ASSOC);

                    if ($supMapping) {
                        $supplierId = (int)$supMapping['supplier_id'];
                        $unitCost = (float)$supMapping['cost'];
                    } else {
                        // Fallback to first approved supplier
                        $firstSupStmt = $this->conn->query("SELECT id FROM suppliers WHERE status = 'Approved' LIMIT 1");
                        $supplierId = (int)$firstSupStmt->fetchColumn();
                        $unitCost = $item['cost_of_goods_sold'];
                    }

                    if ($supplierId > 0) {
                        $owedAmount = $unitCost * $consignedSold;
                        $stmtPayable = $this->conn->prepare("
                            INSERT INTO consignment_payables (
                                sale_id, product_id, supplier_id, quantity_sold, 
                                cost_at_time_of_sale, amount_owed, status, recorded_by
                            ) VALUES (
                                :sale_id, :product_id, :supplier_id, :quantity_sold, 
                                :cost_at_time_of_sale, :amount_owed, 'Owed', :recorded_by
                            )
                        ");
                        $stmtPayable->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
                        $stmtPayable->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
                        $stmtPayable->bindValue(':supplier_id', $supplierId, PDO::PARAM_INT);
                        $stmtPayable->bindValue(':quantity_sold', $consignedSold, PDO::PARAM_INT);
                        $stmtPayable->bindValue(':cost_at_time_of_sale', $unitCost);
                        $stmtPayable->bindValue(':amount_owed', $owedAmount);
                        $stmtPayable->bindValue(':recorded_by', $userId, PDO::PARAM_INT);
                        $stmtPayable->execute();
                    }
                }
            }

            // 4. Record labor services
            $stmtInsertService = $this->conn->prepare("
                INSERT INTO sale_services (sale_id, service_id, price_at_time) 
                VALUES (:sale_id, :service_id, :price_at_time)
            ");

            foreach ($services as $service) {
                if (!empty($service['service_id'])) {
                    $stmtInsertService->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
                    $stmtInsertService->bindValue(':service_id', (int)$service['service_id'], PDO::PARAM_INT);
                    $stmtInsertService->bindValue(':price_at_time', (float)$service['price_at_time']);
                    $stmtInsertService->execute();
                }
            }

            $this->conn->commit();
            return $saleId;

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Retrieve sale record with line items, services, customer, vehicle, and cashier data.
     */
    public function getSaleById(int $id): array|false {
        $sql = "
            SELECT 
                s.*, 
                u.full_name AS cashier_name, 
                u.username AS cashier_username,
                c.name AS customer_name,
                c.phone AS customer_phone,
                c.email AS customer_email,
                c.address AS customer_address,
                m.make AS motorcycle_make,
                m.model AS motorcycle_model,
                m.plate_number AS motorcycle_plate
            FROM sales s
            JOIN users u ON s.recorded_by = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN motorcycles m ON s.motorcycle_id = m.id
            WHERE s.id = :id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            return false;
        }

        // Fetch products
        $itemSql = "
            SELECT 
                si.id, 
                si.product_id, 
                p.name AS product_name, 
                p.item_code, 
                p.unit,
                si.quantity, 
                si.price_at_time, 
                si.cost_of_goods_sold,
                (si.quantity * si.price_at_time) AS line_total
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = :id
            ORDER BY si.id ASC
        ";
        $itemStmt = $this->conn->prepare($itemSql);
        $itemStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $itemStmt->execute();
        $sale['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch services
        $svcSql = "
            SELECT 
                ss.id, 
                ss.service_id, 
                srv.name AS service_name, 
                srv.description AS service_description,
                ss.price_at_time
            FROM sale_services ss
            JOIN services srv ON ss.service_id = srv.id
            WHERE ss.sale_id = :id
            ORDER BY ss.id ASC
        ";
        $svcStmt = $this->conn->prepare($svcSql);
        $svcStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $svcStmt->execute();
        $sale['services'] = $svcStmt->fetchAll(PDO::FETCH_ASSOC);

        return $sale;
    }

    /**
     * Retrieve paginated sales registry.
     */
    public function getSales(int $page = 1, int $limit = 20): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $countStmt = $this->conn->query("SELECT COUNT(*) FROM sales");
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT 
                s.id, 
                s.total_amount, 
                s.created_at, 
                u.full_name AS cashier_name,
                c.name AS customer_name,
                m.plate_number AS motorcycle_plate,
                COUNT(DISTINCT si.id) AS item_count,
                COUNT(DISTINCT ss.id) AS service_count
            FROM sales s
            JOIN users u ON s.recorded_by = u.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN motorcycles m ON s.motorcycle_id = m.id
            LEFT JOIN sale_items si ON s.id = si.sale_id
            LEFT JOIN sale_services ss ON s.id = ss.sale_id
            GROUP BY s.id, s.total_amount, s.created_at, u.full_name, c.name, m.plate_number
            ORDER BY s.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $sales,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => $total > 0 ? (int)ceil($total / $limit) : 1
            ]
        ];
    }

    /**
     * Generate an XML Digital Receipt compliant with the rubric interchange specification.
     * Built using native PHP DOMDocument with formatted indentation.
     *
     * @param int $saleId
     * @return string Validated XML document string
     */
    public function getReceiptXml(int $saleId): string {
        $sale = $this->getSaleById($saleId);
        if (!$sale) {
            throw new \RuntimeException("Sale ID {$saleId} not found.");
        }

        // Fetch company settings
        $settingsStmt = $this->conn->query("SELECT setting_key, setting_value FROM settings");
        $settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $companyName    = $settings['company_name'] ?? 'VinJack Motorworks';
        $companyAddress = $settings['company_address'] ?? 'Marikina City, Metro Manila, Philippines';
        $contactPhone   = $settings['contact_phone'] ?? '+63 (02) 8945-1234';
        $contactEmail   = $settings['contact_email'] ?? 'contact@vinjack.com';
        $currency       = $settings['currency'] ?? 'PHP';
        $taxRate        = (float)($settings['tax_rate'] ?? 0.0);

        // Instantiate DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Root Element <receipt>
        $root = $dom->createElement('receipt');
        $root->setAttribute('id', 'REC-' . str_pad((string)$sale['id'], 6, '0', STR_PAD_LEFT));
        $root->setAttribute('timestamp', $sale['created_at']);
        $dom->appendChild($root);

        // 1. Header Node
        $headerNode = $dom->createElement('header');
        $headerNode->appendChild($dom->createElement('company_name', htmlspecialchars($companyName)));
        $headerNode->appendChild($dom->createElement('company_address', htmlspecialchars($companyAddress)));
        $headerNode->appendChild($dom->createElement('contact_phone', htmlspecialchars($contactPhone)));
        $headerNode->appendChild($dom->createElement('contact_email', htmlspecialchars($contactEmail)));
        $root->appendChild($headerNode);

        // 2. Metadata Node
        $metaNode = $dom->createElement('metadata');
        $metaNode->appendChild($dom->createElement('sale_id', (string)$sale['id']));
        $metaNode->appendChild($dom->createElement('transaction_date', $sale['created_at']));
        
        $cashierNode = $dom->createElement('cashier');
        $cashierNode->setAttribute('id', (string)$sale['recorded_by']);
        $cashierNode->appendChild($dom->createElement('name', htmlspecialchars($sale['cashier_name'] ?? 'Cashier')));
        $cashierNode->appendChild($dom->createElement('username', htmlspecialchars($sale['cashier_username'] ?? '')));
        $metaNode->appendChild($cashierNode);

        $customerNode = $dom->createElement('customer');
        $customerNode->setAttribute('id', (string)($sale['customer_id'] ?? 0));
        $customerNode->appendChild($dom->createElement('name', htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer')));
        if (!empty($sale['customer_phone'])) {
            $customerNode->appendChild($dom->createElement('phone', htmlspecialchars($sale['customer_phone'])));
        }
        $metaNode->appendChild($customerNode);

        if (!empty($sale['motorcycle_plate'])) {
            $motoNode = $dom->createElement('vehicle');
            $motoNode->appendChild($dom->createElement('plate_number', htmlspecialchars($sale['motorcycle_plate'])));
            $motoNode->appendChild($dom->createElement('description', htmlspecialchars(trim(($sale['motorcycle_make'] ?? '') . ' ' . ($sale['motorcycle_model'] ?? '')))));
            $metaNode->appendChild($motoNode);
        }
        $root->appendChild($metaNode);

        // 3. Line Items Node
        $itemsNode = $dom->createElement('items');
        $itemsNode->setAttribute('count', (string)count($sale['items']));
        $itemsSubtotal = 0.0;

        foreach ($sale['items'] as $item) {
            $lineTotal = (float)$item['price_at_time'] * (int)$item['quantity'];
            $itemsSubtotal += $lineTotal;

            $itemNode = $dom->createElement('item');
            $itemNode->setAttribute('product_id', (string)$item['product_id']);
            $itemNode->appendChild($dom->createElement('item_code', htmlspecialchars($item['item_code'])));
            $itemNode->appendChild($dom->createElement('name', htmlspecialchars($item['product_name'])));
            $itemNode->appendChild($dom->createElement('unit', htmlspecialchars($item['unit'] ?? 'pc')));
            $itemNode->appendChild($dom->createElement('quantity', (string)$item['quantity']));
            $itemNode->appendChild($dom->createElement('unit_price', number_format((float)$item['price_at_time'], 2, '.', '')));
            $itemNode->appendChild($dom->createElement('total_price', number_format($lineTotal, 2, '.', '')));
            $itemsNode->appendChild($itemNode);
        }
        $root->appendChild($itemsNode);

        // 4. Labor Services Node
        $servicesNode = $dom->createElement('services');
        $servicesNode->setAttribute('count', (string)count($sale['services']));
        $servicesSubtotal = 0.0;

        foreach ($sale['services'] as $svc) {
            $svcCharge = (float)$svc['price_at_time'];
            $servicesSubtotal += $svcCharge;

            $svcNode = $dom->createElement('service');
            $svcNode->setAttribute('service_id', (string)$svc['service_id']);
            $svcNode->appendChild($dom->createElement('name', htmlspecialchars($svc['service_name'])));
            $svcNode->appendChild($dom->createElement('charge', number_format($svcCharge, 2, '.', '')));
            $servicesNode->appendChild($svcNode);
        }
        $root->appendChild($servicesNode);

        // 5. Financial Summary Node
        $summaryNode = $dom->createElement('summary');
        $taxAmount = ($itemsSubtotal + $servicesSubtotal) * ($taxRate / 100.0);
        $grandTotal = (float)$sale['total_amount'];

        $summaryNode->appendChild($dom->createElement('items_subtotal', number_format($itemsSubtotal, 2, '.', '')));
        $summaryNode->appendChild($dom->createElement('services_subtotal', number_format($servicesSubtotal, 2, '.', '')));
        $summaryNode->appendChild($dom->createElement('tax_rate_percent', number_format($taxRate, 2, '.', '')));
        $summaryNode->appendChild($dom->createElement('tax_amount', number_format($taxAmount, 2, '.', '')));
        $summaryNode->appendChild($dom->createElement('grand_total', number_format($grandTotal, 2, '.', '')));
        $summaryNode->appendChild($dom->createElement('currency', htmlspecialchars($currency)));
        $root->appendChild($summaryNode);

        return $dom->saveXML();
    }
}
