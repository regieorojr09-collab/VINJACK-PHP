<?php
declare(strict_types=1);

/**
 * VinJack Motorworks - Retroactive System Audit Log Migration Script
 *
 * One-time, idempotent backfill utility that reads historical records from:
 *   1. `sales`           -> SALE_COMPLETED (Entity: Sale)
 *   2. `purchase_orders` -> PURCHASE_ORDER_CREATED, PURCHASE_ORDER_APPROVED, PO_RECEIVED (Entity: PurchaseOrder)
 *   3. `movements`       -> INVENTORY_ADJUSTMENT, STOCK_DELIVERY_RECEIVED, INVENTORY_RETURN, INVENTORY_MOVEMENT (Entity: Inventory)
 *
 * Sorts all reconstructed events chronologically and inserts missing entries
 * into `audit_logs` inside an atomic database transaction.
 *
 * Usage (CLI):
 *   php scripts/populate_retroactive_audit.php
 */

require_once __DIR__ . '/../api/config/Database.php';

// If invoked over HTTP rather than CLI, strictly enforce Super Admin / Admin JWT authentication
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../api/middleware/AuthMiddleware.php';
    \Middleware\AuthMiddleware::requireRole(['Super Admin', 'Admin'])();
    header('Content-Type: text/plain; charset=utf-8');
}

echo "=====================================================================\n";
echo "   VinJack Motorworks - Retroactive System Audit History Migration   \n";
echo "=====================================================================\n\n";

/**
 * Establish PDO connection via Core\Database with local XAMPP port fallback.
 */
function resolveMigrationConnection(): PDO {
    try {
        return \Core\Database::getConnection();
    } catch (\Throwable $primaryError) {
        $cfg = \Core\Database::getInstance()->loadConfig();
        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1'], true);

        if ($isLocal) {
            $dbname = (string)($cfg['dbname'] ?? 'vinjack_db');
            $user   = (string)($cfg['username'] ?? 'root');
            $pass   = (string)($cfg['password'] ?? '');
            $ports  = array_unique([(int)($cfg['port'] ?? 3307), 3307, 3306]);

            foreach ($ports as $port) {
                try {
                    $dsn = "mysql:host=127.0.0.1;port={$port};dbname={$dbname};charset=utf8mb4";
                    return new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                } catch (\Throwable $fallbackErr) {
                    // Try next candidate local port
                }
            }
        }

        throw $primaryError;
    }
}

/**
 * Check if a given table exists in the connected schema.
 */
function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name"
    );
    $stmt->execute([':table_name' => $tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $pdo = resolveMigrationConnection();
    echo "[OK] Connected to database successfully.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

if (!tableExists($pdo, 'audit_logs')) {
    fwrite(STDERR, "[ERROR] Target table `audit_logs` does not exist. Run database/vinjack_schema.sql first.\n");
    exit(1);
}

// -----------------------------------------------------------------------------
// 1. Load User Directory for Actor Attribution & FK Integrity
// -----------------------------------------------------------------------------
$validUserIds = [];
$userLookupByIdentity = [];
$fallbackUserId = null;

if (tableExists($pdo, 'users')) {
    $userRows = $pdo->query("SELECT id, username, full_name, role FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($userRows as $u) {
        $uid = (int)$u['id'];
        $validUserIds[$uid] = true;

        $uname = strtolower(trim((string)($u['username'] ?? '')));
        $fname = strtolower(trim((string)($u['full_name'] ?? '')));
        if ($uname !== '') {
            $userLookupByIdentity[$uname] = $uid;
        }
        if ($fname !== '') {
            $userLookupByIdentity[$fname] = $uid;
        }

        if ($fallbackUserId === null && ($u['role'] ?? '') === 'Super Admin') {
            $fallbackUserId = $uid;
        }
    }

    if ($fallbackUserId === null && !empty($userRows)) {
        $fallbackUserId = (int)$userRows[0]['id'];
    }
}

echo "[INFO] Loaded " . count($validUserIds) . " user account(s) (Fallback System/Admin ID: " . ($fallbackUserId ?? 'NULL') . ").\n\n";

/**
 * Validate a numeric user_id against `users`, falling back cleanly if missing.
 */
$resolveActorId = static function ($candidateId, ?string $candidateName = null) use ($validUserIds, $userLookupByIdentity, $fallbackUserId): ?int {
    if ($candidateId !== null && is_numeric($candidateId)) {
        $cid = (int)$candidateId;
        if (isset($validUserIds[$cid])) {
            return $cid;
        }
    }

    if ($candidateName !== null) {
        $key = strtolower(trim($candidateName));
        if ($key !== '' && $key !== 'system' && isset($userLookupByIdentity[$key])) {
            return $userLookupByIdentity[$key];
        }
    }

    return $fallbackUserId;
};

/** @var array<int, array{source: string, user_id: ?int, action: string, details: string, entity_type: string, entity_id: ?int, created_at: string, phase: int, movement_id?: int}> $events */
$events = [];

// -----------------------------------------------------------------------------
// 2. Extract Historical POS Sales (`sales` -> SALE_COMPLETED)
// -----------------------------------------------------------------------------
$salesCount = 0;
if (tableExists($pdo, 'sales')) {
    $hasCustomers    = tableExists($pdo, 'customers');
    $hasSaleItems    = tableExists($pdo, 'sale_items');
    $hasSaleServices = tableExists($pdo, 'sale_services');

    $customerJoin = $hasCustomers ? "LEFT JOIN customers c ON s.customer_id = c.id" : "";
    $customerSelect = $hasCustomers ? "c.name AS customer_name" : "NULL AS customer_name";

    $itemJoin = $hasSaleItems
        ? "LEFT JOIN (
               SELECT sale_id, COALESCE(SUM(quantity), 0) AS total_units, COUNT(*) AS line_count
               FROM sale_items
               GROUP BY sale_id
           ) si ON si.sale_id = s.id"
        : "";
    $itemSelect = $hasSaleItems
        ? "COALESCE(si.total_units, 0) AS total_units, COALESCE(si.line_count, 0) AS line_count"
        : "0 AS total_units, 0 AS line_count";

    $serviceJoin = $hasSaleServices
        ? "LEFT JOIN (
               SELECT sale_id, COUNT(*) AS service_count
               FROM sale_services
               GROUP BY sale_id
           ) ss ON ss.sale_id = s.id"
        : "";
    $serviceSelect = $hasSaleServices
        ? "COALESCE(ss.service_count, 0) AS service_count"
        : "0 AS service_count";

    $salesSql = "SELECT
                     s.id,
                     s.total_amount,
                     s.recorded_by,
                     s.customer_id,
                     s.is_manual_entry,
                     s.created_at,
                     {$customerSelect},
                     {$itemSelect},
                     {$serviceSelect}
                 FROM sales s
                 {$customerJoin}
                 {$itemJoin}
                 {$serviceJoin}
                 ORDER BY s.created_at ASC, s.id ASC";

    $salesRows = $pdo->query($salesSql)->fetchAll(PDO::FETCH_ASSOC);
    $salesCount = count($salesRows);

    foreach ($salesRows as $sale) {
        $saleId = (int)$sale['id'];
        $actorId = $resolveActorId($sale['recorded_by'] ?? null);
        $totalFormatted = number_format((float)($sale['total_amount'] ?? 0), 2, '.', ',');
        $customerLabel = !empty($sale['customer_name'])
            ? trim((string)$sale['customer_name'])
            : (!empty($sale['customer_id']) ? 'Customer #' . (int)$sale['customer_id'] : 'Walk-in Customer');
        $units = (int)($sale['total_units'] ?? 0);
        $services = (int)($sale['service_count'] ?? 0);
        $entryMode = !empty($sale['is_manual_entry']) ? 'Manual POS Sale' : 'POS Sale';

        $details = sprintf(
            "Completed %s #%d for %s — Total: PHP %s (%d part unit(s), %d service(s)).",
            $entryMode,
            $saleId,
            $customerLabel,
            $totalFormatted,
            $units,
            $services
        );

        $events[] = [
            'source'      => 'sales',
            'user_id'     => $actorId,
            'action'      => 'SALE_COMPLETED',
            'details'     => $details,
            'entity_type' => 'Sale',
            'entity_id'   => $saleId,
            'created_at'  => (string)($sale['created_at'] ?? date('Y-m-d H:i:s')),
            'phase'       => 40,
        ];
    }
}
echo "[SCAN] Extracted {$salesCount} historical record(s) from `sales`.\n";

// -----------------------------------------------------------------------------
// 3. Extract Historical Purchase Orders (`purchase_orders` -> CREATED / APPROVED / PO_RECEIVED)
// -----------------------------------------------------------------------------
$poRowCount = 0;
if (tableExists($pdo, 'purchase_orders')) {
    $hasSuppliers = tableExists($pdo, 'suppliers');
    $hasPoItems   = tableExists($pdo, 'purchase_order_items');
    $hasPoHistory = tableExists($pdo, 'purchase_order_history');
    $hasDeliveries = tableExists($pdo, 'deliveries');
    $hasDeliveryItems = tableExists($pdo, 'delivery_items');

    // Preload PO history by purchase_order_id
    $historyByPo = [];
    if ($hasPoHistory) {
        $histRows = $pdo->query(
            "SELECT purchase_order_id, status, notes, updated_by, timestamp
             FROM purchase_order_history
             ORDER BY timestamp ASC, id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($histRows as $h) {
            $historyByPo[(int)$h['purchase_order_id']][] = $h;
        }
    }

    // Preload deliveries linked to purchase_orders
    $deliveriesByPo = [];
    if ($hasDeliveries) {
        $delItemJoin = $hasDeliveryItems
            ? "LEFT JOIN (
                   SELECT delivery_id, COALESCE(SUM(quantity), 0) AS qty_received, COUNT(*) AS item_lines
                   FROM delivery_items
                   GROUP BY delivery_id
               ) di ON di.delivery_id = d.id"
            : "";
        $delItemSelect = $hasDeliveryItems
            ? "COALESCE(di.qty_received, 0) AS qty_received, COALESCE(di.item_lines, 0) AS item_lines"
            : "0 AS qty_received, 0 AS item_lines";

        $delRows = $pdo->query(
            "SELECT d.id, d.purchase_order_id, d.delivery_date, d.total_cost, d.recorded_by, d.created_at,
                    {$delItemSelect}
             FROM deliveries d
             {$delItemJoin}
             WHERE d.purchase_order_id IS NOT NULL
             ORDER BY d.delivery_date ASC, d.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($delRows as $d) {
            $deliveriesByPo[(int)$d['purchase_order_id']][] = $d;
        }
    }

    $supJoin = $hasSuppliers ? "LEFT JOIN suppliers sup ON po.supplier_id = sup.id" : "";
    $supSelect = $hasSuppliers ? "sup.name AS supplier_name" : "NULL AS supplier_name";

    $poItemJoin = $hasPoItems
        ? "LEFT JOIN (
               SELECT purchase_order_id,
                      COALESCE(SUM(quantity), 0) AS ordered_qty,
                      COALESCE(SUM(quantity_received), 0) AS received_qty,
                      COUNT(*) AS line_count
               FROM purchase_order_items
               GROUP BY purchase_order_id
           ) poi ON poi.purchase_order_id = po.id"
        : "";
    $poItemSelect = $hasPoItems
        ? "COALESCE(poi.ordered_qty, 0) AS ordered_qty, COALESCE(poi.received_qty, 0) AS received_qty, COALESCE(poi.line_count, 0) AS line_count"
        : "0 AS ordered_qty, 0 AS received_qty, 0 AS line_count";

    $poSql = "SELECT
                  po.id,
                  po.po_number,
                  po.supplier_id,
                  po.total_amount,
                  po.po_type,
                  po.status,
                  po.order_date,
                  po.notes,
                  po.created_at,
                  po.updated_at,
                  {$supSelect},
                  {$poItemSelect}
              FROM purchase_orders po
              {$supJoin}
              {$poItemJoin}
              ORDER BY po.order_date ASC, po.id ASC";

    $poRows = $pdo->query($poSql)->fetchAll(PDO::FETCH_ASSOC);
    $poRowCount = count($poRows);

    foreach ($poRows as $po) {
        $poId = (int)$po['id'];
        $poNumber = !empty($po['po_number']) ? (string)$po['po_number'] : ('PO #' . $poId);
        $poType = (string)($po['po_type'] ?? 'Purchase');
        $status = (string)($po['status'] ?? 'Pending');
        $supplierName = !empty($po['supplier_name'])
            ? trim((string)$po['supplier_name'])
            : ('Supplier #' . (int)($po['supplier_id'] ?? 0));
        $totalFormatted = number_format((float)($po['total_amount'] ?? 0), 2, '.', ',');
        $orderedQty = (int)($po['ordered_qty'] ?? 0);
        $lineCount = (int)($po['line_count'] ?? 0);

        $poHistory = $historyByPo[$poId] ?? [];
        $poDeliveries = $deliveriesByPo[$poId] ?? [];

        // Determine creator actor from earliest PO history or first delivery or fallback admin
        $creatorActor = null;
        if (!empty($poHistory)) {
            $creatorActor = $resolveActorId(null, (string)($poHistory[0]['updated_by'] ?? ''));
        } elseif (!empty($poDeliveries)) {
            $creatorActor = $resolveActorId($poDeliveries[0]['recorded_by'] ?? null);
        } else {
            $creatorActor = $fallbackUserId;
        }

        $orderTimestamp = !empty($po['order_date'])
            ? (string)$po['order_date']
            : (string)($po['created_at'] ?? date('Y-m-d H:i:s'));

        // 3a. PURCHASE_ORDER_CREATED
        $events[] = [
            'source'      => 'purchase_orders',
            'user_id'     => $creatorActor,
            'action'      => 'PURCHASE_ORDER_CREATED',
            'details'     => sprintf(
                "Created %s Order %s for supplier '%s' — Total: PHP %s (%d line item(s), %d unit(s)).",
                $poType,
                $poNumber,
                $supplierName,
                $totalFormatted,
                $lineCount,
                $orderedQty
            ),
            'entity_type' => 'PurchaseOrder',
            'entity_id'   => $poId,
            'created_at'  => $orderTimestamp,
            'phase'       => 10,
        ];

        // 3b. PURCHASE_ORDER_APPROVED (if approved or already progressed to received/completed)
        $approvedHist = null;
        foreach ($poHistory as $h) {
            if (stripos((string)($h['status'] ?? ''), 'Approved') !== false) {
                $approvedHist = $h;
                break;
            }
        }

        if ($approvedHist !== null || in_array($status, ['Approved', 'Partially Received', 'Completed'], true)) {
            $approvalActor = $approvedHist !== null
                ? $resolveActorId(null, (string)($approvedHist['updated_by'] ?? ''))
                : $creatorActor;
            $approvalTime = $approvedHist !== null && !empty($approvedHist['timestamp'])
                ? (string)$approvedHist['timestamp']
                : (!empty($poDeliveries) && !empty($poDeliveries[0]['delivery_date'])
                    ? (string)$poDeliveries[0]['delivery_date']
                    : (string)($po['updated_at'] ?? $orderTimestamp));

            $events[] = [
                'source'      => 'purchase_orders',
                'user_id'     => $approvalActor,
                'action'      => 'PURCHASE_ORDER_APPROVED',
                'details'     => sprintf(
                    "Approved %s Order %s for supplier '%s' (PHP %s).",
                    $poType,
                    $poNumber,
                    $supplierName,
                    $totalFormatted
                ),
                'entity_type' => 'PurchaseOrder',
                'entity_id'   => $poId,
                'created_at'  => $approvalTime,
                'phase'       => 20,
            ];
        }

        // 3c. PO_RECEIVED (from linked deliveries or received PO status)
        if (!empty($poDeliveries)) {
            foreach ($poDeliveries as $del) {
                $delId = (int)$del['id'];
                $delActor = $resolveActorId($del['recorded_by'] ?? null);
                $delTime = !empty($del['delivery_date'])
                    ? (string)$del['delivery_date']
                    : (string)($del['created_at'] ?? $po['updated_at'] ?? $orderTimestamp);
                $delQty = (int)($del['qty_received'] ?? 0);
                $delLines = (int)($del['item_lines'] ?? 0);

                $events[] = [
                    'source'      => 'purchase_orders',
                    'user_id'     => $delActor,
                    'action'      => 'PO_RECEIVED',
                    'details'     => sprintf(
                        "Received stock delivery #%d against %s from '%s' (%d product line(s), %d unit(s) received; PO Status: %s).",
                        $delId,
                        $poNumber,
                        $supplierName,
                        $delLines,
                        $delQty,
                        $status
                    ),
                    'entity_type' => 'PurchaseOrder',
                    'entity_id'   => $poId,
                    'created_at'  => $delTime,
                    'phase'       => 30,
                ];
            }
        } elseif (in_array($status, ['Partially Received', 'Completed'], true)) {
            $recQty = (int)($po['received_qty'] ?? 0);
            $events[] = [
                'source'      => 'purchase_orders',
                'user_id'     => $creatorActor,
                'action'      => 'PO_RECEIVED',
                'details'     => sprintf(
                    "Received items against %s from '%s' (%d unit(s) received; PO Status: %s).",
                    $poNumber,
                    $supplierName,
                    $recQty,
                    $status
                ),
                'entity_type' => 'PurchaseOrder',
                'entity_id'   => $poId,
                'created_at'  => (string)($po['updated_at'] ?? $orderTimestamp),
                'phase'       => 30,
            ];
        }
    }
}
echo "[SCAN] Extracted {$poRowCount} historical record(s) from `purchase_orders`.\n";

// -----------------------------------------------------------------------------
// 4. Extract Historical Stock Ledger Movements (`movements` -> Inventory Audit Trail)
// -----------------------------------------------------------------------------
$movementCount = 0;
if (tableExists($pdo, 'movements')) {
    $hasProducts = tableExists($pdo, 'products');
    $prodJoin = $hasProducts ? "LEFT JOIN products p ON m.product_id = p.id" : "";
    $prodSelect = $hasProducts
        ? "p.name AS product_name, p.item_code AS item_code"
        : "NULL AS product_name, NULL AS item_code";

    $movSql = "SELECT
                   m.id,
                   m.product_id,
                   m.type,
                   m.quantity_change,
                   m.stock_before,
                   m.stock_after,
                   m.reference_id,
                   m.notes,
                   m.recorded_by,
                   m.created_at,
                   {$prodSelect}
               FROM movements m
               {$prodJoin}
               ORDER BY m.created_at ASC, m.id ASC";

    $movRows = $pdo->query($movSql)->fetchAll(PDO::FETCH_ASSOC);
    $movementCount = count($movRows);

    foreach ($movRows as $m) {
        $movId = (int)$m['id'];
        $prodId = (int)$m['product_id'];
        $movType = strtoupper(trim((string)($m['type'] ?? 'ADJUSTMENT')));
        $qtyChange = (int)($m['quantity_change'] ?? 0);
        $signedDelta = ($qtyChange >= 0 ? '+' : '') . (string)$qtyChange;
        $stockBefore = (int)($m['stock_before'] ?? 0);
        $stockAfter  = (int)($m['stock_after'] ?? 0);
        $actorId     = $resolveActorId($m['recorded_by'] ?? null);

        $productLabel = !empty($m['product_name'])
            ? trim((string)$m['product_name']) . (!empty($m['item_code']) ? " (SKU: {$m['item_code']})" : '')
            : "Product #{$prodId}";

        $actionCode = match ($movType) {
            'ADJUSTMENT'                => 'INVENTORY_ADJUSTMENT',
            'DELIVERY', 'DELIVERY (PO)' => 'STOCK_DELIVERY_RECEIVED',
            'RETURN'                    => 'INVENTORY_RETURN',
            default                     => 'INVENTORY_MOVEMENT',
        };

        $refSuffix = !empty($m['reference_id']) ? " [Ref: {$m['reference_id']}]" : '';
        $noteSuffix = !empty($m['notes']) ? " — " . trim((string)$m['notes']) : '';

        $details = sprintf(
            "[Movement #%d] %s for %s: %s unit(s) (Stock: %d -> %d)%s%s",
            $movId,
            $movType,
            $productLabel,
            $signedDelta,
            $stockBefore,
            $stockAfter,
            $refSuffix,
            $noteSuffix
        );

        $events[] = [
            'source'      => 'movements',
            'user_id'     => $actorId,
            'action'      => $actionCode,
            'details'     => $details,
            'entity_type' => 'Inventory',
            'entity_id'   => $prodId,
            'created_at'  => (string)($m['created_at'] ?? date('Y-m-d H:i:s')),
            'phase'       => 50,
            'movement_id' => $movId,
        ];
    }
}
echo "[SCAN] Extracted {$movementCount} historical record(s) from `movements`.\n\n";

// -----------------------------------------------------------------------------
// 5. Sort Events Chronologically & Insert Idempotently Inside Transaction
// -----------------------------------------------------------------------------
usort($events, static function (array $a, array $b): int {
    $cmp = strcmp($a['created_at'], $b['created_at']);
    if ($cmp !== 0) {
        return $cmp;
    }
    return ($a['phase'] <=> $b['phase']);
});

$stats = [
    'sales'           => ['inserted' => 0, 'skipped' => 0],
    'purchase_orders' => ['inserted' => 0, 'skipped' => 0],
    'movements'       => ['inserted' => 0, 'skipped' => 0],
];

// Duplicate detection prepared statements for strict idempotency
$checkSingletonEntityStmt = $pdo->prepare(
    "SELECT id FROM audit_logs
     WHERE action = :action
       AND entity_type = :entity_type
       AND entity_id = :entity_id
     LIMIT 1"
);

$checkPoReceivedStmt = $pdo->prepare(
    "SELECT id FROM audit_logs
     WHERE action = 'PO_RECEIVED'
       AND entity_type = 'PurchaseOrder'
       AND entity_id = :entity_id
       AND (details = :details OR ABS(TIMESTAMPDIFF(SECOND, created_at, :created_at)) <= 5)
     LIMIT 1"
);

$checkMovementStmt = $pdo->prepare(
    "SELECT id FROM audit_logs
     WHERE entity_type = 'Inventory'
       AND (details LIKE :movement_tag OR (action = :action AND entity_id = :entity_id AND created_at = :created_at AND details = :details))
     LIMIT 1"
);

$insertStmt = $pdo->prepare(
    "INSERT INTO audit_logs (user_id, action, details, entity_type, entity_id, created_at)
     VALUES (:user_id, :action, :details, :entity_type, :entity_id, :created_at)"
);

try {
    $pdo->beginTransaction();

    foreach ($events as $ev) {
        $source     = $ev['source'];
        $action     = substr($ev['action'], 0, 50);
        $entityType = substr($ev['entity_type'], 0, 50);
        $entityId   = $ev['entity_id'];
        $details    = $ev['details'];
        $createdAt  = $ev['created_at'];
        $userId     = $ev['user_id'];

        $alreadyExists = false;

        if (in_array($action, ['SALE_COMPLETED', 'PURCHASE_ORDER_CREATED', 'PURCHASE_ORDER_APPROVED'], true) && $entityId !== null) {
            $checkSingletonEntityStmt->execute([
                ':action'      => $action,
                ':entity_type' => $entityType,
                ':entity_id'   => $entityId,
            ]);
            $alreadyExists = (bool)$checkSingletonEntityStmt->fetchColumn();
        } elseif ($action === 'PO_RECEIVED' && $entityId !== null) {
            $checkPoReceivedStmt->execute([
                ':entity_id'  => $entityId,
                ':details'    => $details,
                ':created_at' => $createdAt,
            ]);
            $alreadyExists = (bool)$checkPoReceivedStmt->fetchColumn();
        } elseif (isset($ev['movement_id'])) {
            $checkMovementStmt->execute([
                ':movement_tag' => '[Movement #' . (int)$ev['movement_id'] . ']%',
                ':action'       => $action,
                ':entity_id'    => $entityId,
                ':created_at'   => $createdAt,
                ':details'      => $details,
            ]);
            $alreadyExists = (bool)$checkMovementStmt->fetchColumn();
        }

        if ($alreadyExists) {
            $stats[$source]['skipped']++;
            continue;
        }

        if ($userId !== null) {
            $insertStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        } else {
            $insertStmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        }
        $insertStmt->bindValue(':action', $action, PDO::PARAM_STR);
        $insertStmt->bindValue(':details', $details, PDO::PARAM_STR);
        $insertStmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        if ($entityId !== null) {
            $insertStmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        } else {
            $insertStmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
        }
        $insertStmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
        $insertStmt->execute();

        $stats[$source]['inserted']++;
    }

    $pdo->commit();
} catch (\Throwable $txErr) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Retroactive migration rolled back due to error: " . $txErr->getMessage() . "\n");
    exit(1);
}

$totalInserted = $stats['sales']['inserted'] + $stats['purchase_orders']['inserted'] + $stats['movements']['inserted'];
$totalSkipped  = $stats['sales']['skipped']  + $stats['purchase_orders']['skipped']  + $stats['movements']['skipped'];

echo "---------------------------------------------------------------------\n";
echo "Retroactive Audit Backfill Summary:\n";
echo sprintf("  - Sales (`sales`)                 : %4d inserted, %4d skipped\n", $stats['sales']['inserted'], $stats['sales']['skipped']);
echo sprintf("  - Purchase Orders (`purchase_orders`): %4d inserted, %4d skipped\n", $stats['purchase_orders']['inserted'], $stats['purchase_orders']['skipped']);
echo sprintf("  - Stock Ledger (`movements`)      : %4d inserted, %4d skipped\n", $stats['movements']['inserted'], $stats['movements']['skipped']);
echo "---------------------------------------------------------------------\n";
echo sprintf("[DONE] Total Audit Logs Inserted: %d | Duplicates Skipped: %d\n", $totalInserted, $totalSkipped);
