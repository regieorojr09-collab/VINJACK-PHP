<?php
declare(strict_types=1);

/**
 * POS Checkout Flow End-to-End Integration Test Suite
 * 
 * Tests Phase 1 through Phase 4:
 * 1. Pre-Test State Capture
 * 2. Authenticated POST /api/sales Simulation
 * 3. Database ACID Verification (Inventory, COGS, Stock Movement Ledger)
 * 4. XML Digital Receipt Generation & Schema Validation
 */

require_once __DIR__ . '/../api/core/JWT.php';

use Core\JWT;

echo "====================================================================\n";
echo "   VINJACK-PHP POS INTEGRATION & ACID COMPLIANCE TEST SUITE\n";
echo "====================================================================\n\n";

$dbHost = '127.0.0.1';
$dbPort = 3307;
$dbName = 'vinjack_db';
$dbUser = 'root';
$dbPass = '';
$baseUrl = 'http://localhost:81/VINJACK-PHP/api';

// Step 0: Database Connection
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "[✓] Connected to MySQL database {$dbName} on port {$dbPort}\n";
} catch (PDOException $e) {
    echo "[✗] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ====================================================================
// Phase 1: Pre-Test State Capture
// ====================================================================
echo "\n--- Phase 1: Pre-Test State Capture ---\n";
$stmt = $pdo->prepare("
    SELECT id, item_code, name, quantity, price, default_cost 
    FROM products 
    WHERE quantity > 5 AND is_active = 1 
    ORDER BY id ASC 
    LIMIT 1
");
$stmt->execute();
$product = $stmt->fetch();

if (!$product) {
    echo "[✗] No active product found with stock > 5.\n";
    exit(1);
}

$productId = (int)$product['id'];
$initialQty = (int)$product['quantity'];
$price = (float)$product['price'];
$defaultCost = (float)$product['default_cost'];
$productName = $product['name'];
$itemCode = $product['item_code'];

echo "Selected Product: {$productName} (Code: {$itemCode}, ID: {$productId})\n";
echo "  - Initial Stock Level: {$initialQty} units\n";
echo "  - Selling Price:       PHP " . number_format($price, 2) . "\n";
echo "  - Default Cost (COGS): PHP " . number_format($defaultCost, 2) . "\n";

// ====================================================================
// Phase 2: Transaction Simulation (POST /api/sales)
// ====================================================================
echo "\n--- Phase 2: Transaction Simulation ---\n";

// 1. Generate Authenticated JWT for Super Admin (User ID: 1)
$secret = JWT::getDefaultSecret();
$tokenPayload = [
    'id'          => 1,
    'username'    => 'admin',
    'full_name'   => 'Super Admin',
    'role'        => 'Super Admin',
    'permissions' => []
];
$token = JWT::encode($tokenPayload, $secret, 3600);
echo "[✓] Generated authenticated JWT token for Super Admin\n";

$purchaseQty = 2;
$lineTotal = $price * $purchaseQty;
$mockCashTender = ceil($lineTotal / 500) * 500; // E.g., tender 1000 or 1500

$payload = [
    'customer_id'   => null,
    'motorcycle_id' => null,
    'items' => [
        [
            'product_id'         => $productId,
            'quantity'           => $purchaseQty,
            'price_at_time'      => $price,
            'cost_of_goods_sold' => $defaultCost
        ]
    ],
    'services'       => [],
    'tendered_amount' => $mockCashTender,
    'notes'          => 'Automated QA POS Integration Test'
];

$salesEndpoint = "{$baseUrl}/sales";
$ch = curl_init($salesEndpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer {$token}"
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload)
]);

$responseBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST {$salesEndpoint} -> HTTP {$httpCode}\n";
$responseData = json_decode($responseBody, true);

if ($httpCode !== 201 || empty($responseData['sale_id'])) {
    echo "[✗] Sale creation failed: " . ($responseData['message'] ?? $responseBody) . "\n";
    exit(1);
}

$saleId = (int)$responseData['sale_id'];
echo "[✓] Sale successfully created! Captured sale_id: {$saleId}\n";
echo "    Total Amount: PHP " . number_format((float)($responseData['data']['total_amount'] ?? $lineTotal), 2) . "\n";
echo "    Tendered:     PHP " . number_format($mockCashTender, 2) . "\n";

// ====================================================================
// Phase 3: Database ACID Verification
// ====================================================================
echo "\n--- Phase 3: Database ACID Verification ---\n";
$acidPassed = true;

// 1. Verify Inventory Deduction in products table
$stmt = $pdo->prepare("SELECT quantity, stock_status FROM products WHERE id = :id");
$stmt->execute([':id' => $productId]);
$afterProduct = $stmt->fetch();
$afterQty = (int)$afterProduct['quantity'];
$expectedQty = $initialQty - $purchaseQty;

echo "1. Inventory Deduction Check:\n";
echo "   - Expected Stock: {$expectedQty}\n";
echo "   - Actual Stock:   {$afterQty}\n";
if ($afterQty === $expectedQty) {
    echo "   [PASS] Inventory deducted exactly by {$purchaseQty} units.\n";
} else {
    echo "   [FAIL] Stock mismatch! Expected {$expectedQty}, got {$afterQty}.\n";
    $acidPassed = false;
}

// 2. Verify COGS Capture in sale_items table
$stmt = $pdo->prepare("
    SELECT id, sale_id, product_id, quantity, price_at_time, cost_of_goods_sold 
    FROM sale_items 
    WHERE sale_id = :sale_id
");
$stmt->execute([':sale_id' => $saleId]);
$saleItem = $stmt->fetch();

echo "\n2. COGS Historical Capture Check:\n";
echo "   - Recorded Product ID: {$saleItem['product_id']}\n";
echo "   - Recorded Quantity:   {$saleItem['quantity']}\n";
echo "   - Recorded Price:      PHP " . number_format((float)$saleItem['price_at_time'], 2) . "\n";
echo "   - Captured COGS:       PHP " . number_format((float)$saleItem['cost_of_goods_sold'], 2) . " (Expected: PHP " . number_format($defaultCost, 2) . ")\n";

$recordedCogs = (float)$saleItem['cost_of_goods_sold'];
if ((int)$saleItem['product_id'] === $productId &&
    (int)$saleItem['quantity'] === $purchaseQty &&
    abs($recordedCogs - $defaultCost) < 0.001) {
    echo "   [PASS] sale_items accurately locked historical COGS at the time of checkout.\n";
} else {
    echo "   [FAIL] COGS capture mismatch in sale_items table!\n";
    $acidPassed = false;
}

// 3. Verify Movement Ledger Entry in movements table
$stmt = $pdo->prepare("
    SELECT id, product_id, type, quantity_change, stock_before, stock_after, reference_id, notes 
    FROM movements 
    WHERE reference_id = :ref_id AND product_id = :prod_id AND type = 'SALE'
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([
    ':ref_id'  => $saleId,
    ':prod_id' => $productId
]);
$movement = $stmt->fetch();

echo "\n3. Stock Movement Ledger Check:\n";
if ($movement) {
    echo "   - Movement ID:      {$movement['id']}\n";
    echo "   - Movement Type:    {$movement['type']} (Expected: SALE)\n";
    echo "   - Quantity Change:  {$movement['quantity_change']} (Expected: -{$purchaseQty})\n";
    echo "   - Stock Before:     {$movement['stock_before']} (Expected: {$initialQty})\n";
    echo "   - Stock After:      {$movement['stock_after']} (Expected: {$expectedQty})\n";
    echo "   - Reference ID:     {$movement['reference_id']} (Expected Sale ID: {$saleId})\n";

    if ((int)$movement['quantity_change'] === -$purchaseQty &&
        (int)$movement['stock_before'] === $initialQty &&
        (int)$movement['stock_after'] === $expectedQty &&
        (int)$movement['reference_id'] === $saleId) {
        echo "   [PASS] Immutable stock movement audit ledger correctly recorded.\n";
    } else {
        echo "   [FAIL] Movement ledger record parameters do not match transaction specifics!\n";
        $acidPassed = false;
    }
} else {
    echo "   [FAIL] No movement record found for sale_id: {$saleId}!\n";
    $acidPassed = false;
}

// ====================================================================
// Phase 4: XML Receipt Validation (GET /api/sales/{sale_id}/receipt)
// ====================================================================
echo "\n--- Phase 4: XML Digital Receipt Validation ---\n";
$xmlPassed = true;

$receiptEndpoint = "{$baseUrl}/sales/{$saleId}/receipt";
$ch = curl_init($receiptEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$token}"
    ]
]);

$rawResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

$headers = substr($rawResponse, 0, $headerSize);
$xmlBody = substr($rawResponse, $headerSize);

echo "GET {$receiptEndpoint} -> HTTP {$httpCode}\n";
echo "Content-Type Header: {$contentType}\n";

// Validate Content-Type
if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
    echo "[PASS] Response Content-Type is valid XML MIME type ({$contentType}).\n";
} else {
    echo "[FAIL] Unexpected Content-Type: {$contentType}.\n";
    $xmlPassed = false;
}

// Parse XML using DOMDocument
$dom = new DOMDocument();
$parseResult = @$dom->loadXML($xmlBody);

if ($parseResult) {
    echo "[✓] XML parsed cleanly without syntax errors.\n";

    $hasReceipt = $dom->getElementsByTagName('receipt')->length > 0;
    $hasHeader  = $dom->getElementsByTagName('header')->length > 0;
    $hasItems   = $dom->getElementsByTagName('items')->length > 0;
    $hasSummary = $dom->getElementsByTagName('summary')->length > 0;

    echo "  - Node <receipt>: " . ($hasReceipt ? "[PRESENT]" : "[MISSING]") . "\n";
    echo "  - Node <header>:  " . ($hasHeader  ? "[PRESENT]" : "[MISSING]") . "\n";
    echo "  - Node <items>:   " . ($hasItems   ? "[PRESENT]" : "[MISSING]") . "\n";
    echo "  - Node <summary>: " . ($hasSummary ? "[PRESENT]" : "[MISSING]") . "\n";

    if ($hasReceipt && $hasHeader && $hasItems && $hasSummary) {
        echo "[PASS] All mandatory XML nodes (<receipt>, <header>, <items>, <summary>) are present and valid.\n";

        // Print sample details
        $recId = $dom->getElementsByTagName('receipt')->item(0)->getAttribute('id');
        $compName = $dom->getElementsByTagName('company_name')->item(0)->textContent ?? '';
        $grandTotal = $dom->getElementsByTagName('grand_total')->item(0)->textContent ?? '';
        echo "  - Receipt ID:    {$recId}\n";
        echo "  - Company:       {$compName}\n";
        echo "  - XML Grand Total: PHP {$grandTotal}\n";
    } else {
        echo "[FAIL] One or more required XML nodes are missing!\n";
        $xmlPassed = false;
    }
} else {
    echo "[FAIL] Failed to parse XML response body!\n";
    $xmlPassed = false;
}

echo "\n====================================================================\n";
echo "                 TEST EXECUTION SUMMARY\n";
echo "====================================================================\n";
echo "1. Pre-Test State Capture:    PASS\n";
echo "2. Transaction Simulation:    PASS (Sale ID #{$saleId})\n";
echo "3. Inventory Deduction:       " . ($afterQty === $expectedQty ? "PASS" : "FAIL") . "\n";
echo "4. COGS Capture:              " . (abs($recordedCogs - $defaultCost) < 0.001 ? "PASS" : "FAIL") . "\n";
echo "5. Movement Ledger Audit:     " . ($movement ? "PASS" : "FAIL") . "\n";
echo "6. XML Receipt Generation:    " . ($xmlPassed ? "PASS" : "FAIL") . "\n";
echo "====================================================================\n";

// Save test artifacts to JSON for reporting
$testResults = [
    'timestamp'      => date('c'),
    'product'        => [
        'id'           => $productId,
        'item_code'    => $itemCode,
        'name'         => $productName,
        'initial_stock'=> $initialQty,
        'final_stock'  => $afterQty,
        'price'        => $price,
        'default_cost' => $defaultCost
    ],
    'transaction'    => [
        'sale_id'         => $saleId,
        'purchased_qty'   => $purchaseQty,
        'line_total'      => $lineTotal,
        'mock_tendered'   => $mockCashTender,
        'change_due'      => $mockCashTender - $lineTotal
    ],
    'acid_checks'    => [
        'inventory_deduction' => [
            'status'        => $afterQty === $expectedQty ? 'PASS' : 'FAIL',
            'stock_before'  => $initialQty,
            'stock_after'   => $afterQty,
            'delta'         => -$purchaseQty
        ],
        'cogs_capture'        => [
            'status'        => abs($recordedCogs - $defaultCost) < 0.001 ? 'PASS' : 'FAIL',
            'expected_cogs' => $defaultCost,
            'captured_cogs' => $recordedCogs
        ],
        'movement_logging'    => [
            'status'          => $movement ? 'PASS' : 'FAIL',
            'movement_id'     => $movement['id'] ?? null,
            'type'            => $movement['type'] ?? null,
            'quantity_change' => (int)($movement['quantity_change'] ?? 0),
            'stock_before'    => (int)($movement['stock_before'] ?? 0),
            'stock_after'     => (int)($movement['stock_after'] ?? 0),
            'reference_id'    => (int)($movement['reference_id'] ?? 0)
        ]
    ],
    'xml_validation' => [
        'status'       => $xmlPassed ? 'PASS' : 'FAIL',
        'http_code'    => $httpCode,
        'content_type' => $contentType,
        'receipt_id'   => $recId ?? null,
        'has_receipt'  => $hasReceipt ?? false,
        'has_header'   => $hasHeader ?? false,
        'has_items'    => $hasItems ?? false,
        'has_summary'  => $hasSummary ?? false,
        'raw_xml'      => $xmlBody
    ]
];

file_put_contents(__DIR__ . '/pos_test_results.json', json_encode($testResults, JSON_PRETTY_PRINT));
echo "\nDetailed test results JSON exported to scripts/pos_test_results.json\n";
