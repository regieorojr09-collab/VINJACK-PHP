<?php
declare(strict_types=1);

/**
 * VinJack Motorworks - Realistic Test Data Generator & Seeder
 * 
 * Populates `vinjack_db` with realistic baseline and historical transactional data:
 * - Cleanses transactional tables safely with foreign key check toggling
 * - Establishes foundation entities: Suppliers, Categories, Brands, Products, Services, Customers
 * - Seeds 20 realistic POS Sales (past 30 days) with items, labor services, and stock movements
 * - Seeds 3 Purchase Orders (Received/Completed, Approved, Pending) with Deliveries and PO movements
 * - Seeds Audit Logs and System Activity
 */

echo "===============================================================\n";
echo "   VinJack Motorworks - Database Test Data Seeder Script       \n";
echo "===============================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Database Connection
// -----------------------------------------------------------------------------
$host = '127.0.0.1';
$dbname = 'vinjack_db';
$user = 'root';
$pass = '';

$portsToTry = [3307, 3306];
if (!empty(getenv('DB_PORT'))) {
    array_unshift($portsToTry, (int)getenv('DB_PORT'));
}

$pdo = null;
$connectedPort = null;
foreach (array_unique($portsToTry) as $port) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $connectedPort = $port;
        break;
    } catch (PDOException $e) {
        // try next port
    }
}

if (!$pdo) {
    fwrite(STDERR, "[ERROR] Could not connect to MySQL server ({$host}) on ports " . implode('/', $portsToTry) . "\n");
    exit(1);
}

echo "[✓] Connected to MySQL on {$host}:{$connectedPort} -> Database: {$dbname}\n";

// -----------------------------------------------------------------------------
// 2. Clear Existing Transactional Data
// -----------------------------------------------------------------------------
echo "\n[*] Clearing existing transactional records...\n";

$transactionalTables = [
    'sale_services',
    'sale_items',
    'sales',
    'return_services',
    'return_items',
    'returns',
    'supplier_return_items',
    'supplier_returns',
    'consignment_payables',
    'delivery_items',
    'deliveries',
    'purchase_order_history',
    'purchase_order_items',
    'purchase_orders',
    'movements',
    'audit_logs',
    'notifications'
];

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($transactionalTables as $table) {
    $pdo->exec("TRUNCATE TABLE `{$table}`;");
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "[✓] Successfully truncated " . count($transactionalTables) . " transactional tables.\n";

// -----------------------------------------------------------------------------
// 3. Seed Foundation Data
// -----------------------------------------------------------------------------
echo "\n[*] Ensuring foundation reference data (Brands, Categories, Suppliers, Services, Customers)...\n";

// 3.1 Brands
$brands = [
    'Yamaha',
    'Honda',
    'Brembo',
    'Motul',
    'NGK',
    'MotoWorld PH'
];

$brandStmt = $pdo->prepare("INSERT INTO `brands` (`name`) VALUES (:name) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");
foreach ($brands as $b) {
    $brandStmt->execute([':name' => $b]);
}
$brandMap = $pdo->query("SELECT name, id FROM brands")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "  - Brands ready: " . count($brandMap) . " registered.\n";

// 3.2 Categories
$categories = [
    [
        'name' => 'Engine Parts',
        'description' => 'Cylinders, piston kits, valves, spark plugs, and performance engine internal components'
    ],
    [
        'name' => 'Brakes & Suspension',
        'description' => 'Brake pads, hydraulic calipers, disc rotors, gas shock absorbers, and suspension fork oil'
    ],
    [
        'name' => 'Accessories',
        'description' => 'Mini driving lights, alloy handlebar grips, side mirrors, phone mounts, and crash guards'
    ],
    [
        'name' => 'Tires & Wheels',
        'description' => 'Tubeless motorcycle tires, high-strength inner tubes, lightweight alloy rims'
    ]
];

$catStmt = $pdo->prepare("INSERT INTO `categories` (`name`, `description`) VALUES (:name, :description) ON DUPLICATE KEY UPDATE `description` = VALUES(`description`)");
foreach ($categories as $cat) {
    $catStmt->execute([':name' => $cat['name'], ':description' => $cat['description']]);
}
$categoryMap = $pdo->query("SELECT name, id FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "  - Categories ready: " . count($categoryMap) . " registered.\n";

// 3.3 Suppliers
$suppliers = [
    [
        'name' => 'Yamaha Genuine Parts Direct',
        'email' => 'sales@yamahaparts.ph',
        'contact_person' => 'Kenji Sato',
        'contact_number' => '+63 917 888 1234',
        'address' => 'Subic Bay Freeport Zone, Zambales',
        'status' => 'Approved',
        'default_payment_terms' => 'Terms'
    ],
    [
        'name' => 'MotoWorld PH Official',
        'email' => 'distribution@motoworld.com.ph',
        'contact_person' => 'Ramon Gomez',
        'contact_number' => '+63 918 555 9876',
        'address' => 'Ortigas Center, Pasig City, Metro Manila',
        'status' => 'Approved',
        'default_payment_terms' => 'Cash'
    ],
    [
        'name' => 'Honda Motor Parts & Components',
        'email' => 'orders@hondamotorparts.ph',
        'contact_person' => 'Grace Tan',
        'contact_number' => '+63 922 444 3322',
        'address' => 'Santa Rosa Commercial Complex, Laguna',
        'status' => 'Approved',
        'default_payment_terms' => 'Consignment'
    ]
];

$supStmt = $pdo->prepare("INSERT INTO `suppliers` (`name`, `email`, `contact_person`, `contact_number`, `address`, `status`, `default_payment_terms`) 
    VALUES (:name, :email, :contact_person, :contact_number, :address, :status, :default_payment_terms) 
    ON DUPLICATE KEY UPDATE `contact_person` = VALUES(`contact_person`), `contact_number` = VALUES(`contact_number`)");
foreach ($suppliers as $sup) {
    $supStmt->execute($sup);
}
$supplierMap = $pdo->query("SELECT name, id FROM suppliers")->fetchAll(PDO::FETCH_KEY_PAIR);
echo "  - Suppliers ready: " . count($supplierMap) . " registered.\n";

// 3.4 Services
$services = [
    [
        'name' => 'Oil Change & Filter Service',
        'description' => 'Complete engine flush, oil drain, filter replacement, and fluid top-up',
        'charge' => 250.00,
        'status' => 'active'
    ],
    [
        'name' => 'Tire Mounting & Wheel Balancing',
        'description' => 'Dismount, inspect rim, mount new tire, and static wheel balance',
        'charge' => 150.00,
        'status' => 'active'
    ],
    [
        'name' => 'Brake Caliper Cleaning & Pad Replacement',
        'description' => 'Clean pistons, inspect caliper seals, bleed brake line, and install brake pads',
        'charge' => 200.00,
        'status' => 'active'
    ],
    [
        'name' => 'Full Diagnostic & Tune-up',
        'description' => 'Spark plug check, carb/fuel injection sync, battery testing, and drive chain adjustment',
        'charge' => 500.00,
        'status' => 'active'
    ],
    [
        'name' => 'CVT Cleaning, Degreasing & Tuning',
        'description' => 'Disassemble transmission pulley, clean clutch bell, inspect rollers and belt wear',
        'charge' => 350.00,
        'status' => 'active'
    ]
];

$servStmt = $pdo->prepare("INSERT INTO `services` (`name`, `description`, `charge`, `status`) 
    VALUES (:name, :description, :charge, :status) 
    ON DUPLICATE KEY UPDATE `charge` = VALUES(`charge`), `description` = VALUES(`description`), `status` = VALUES(`status`)");
foreach ($services as $srv) {
    $servStmt->execute($srv);
}
$serviceList = $pdo->query("SELECT id, name, charge FROM services WHERE status = 'active'")->fetchAll();
echo "  - Services ready: " . count($serviceList) . " active labor services.\n";

// 3.5 Customers & Motorcycles
$customers = [
    [
        'name' => 'Juan Carlos Dela Cruz',
        'email' => 'juan.delacruz@example.com',
        'phone' => '+63 917 111 2233',
        'address' => '14 Marikina Heights, Marikina City'
    ],
    [
        'name' => 'Maria Teresa Santos',
        'email' => 'maria.santos@example.com',
        'phone' => '+63 918 333 4455',
        'address' => '88 Katipunan Ave, Quezon City'
    ],
    [
        'name' => 'Mark Anthony Reyes',
        'email' => 'mark.reyes@example.com',
        'phone' => '+63 922 555 6677',
        'address' => '23 Marcos Highway, Antipolo City'
    ],
    [
        'name' => 'Angelo Bautista',
        'email' => 'angelo.b@example.com',
        'phone' => '+63 919 777 8899',
        'address' => '5 Barangka Drive, Mandaluyong City'
    ]
];

$custStmt = $pdo->prepare("INSERT INTO `customers` (`name`, `email`, `phone`, `address`) 
    VALUES (:name, :email, :phone, :address) 
    ON DUPLICATE KEY UPDATE `phone` = VALUES(`phone`)");
foreach ($customers as $c) {
    $custStmt->execute($c);
}
$customerList = $pdo->query("SELECT id, name FROM customers")->fetchAll();
echo "  - Customers ready: " . count($customerList) . " client profiles.\n";

// 3.6 Products (10 Core Items across categories with varying stocks and costs)
echo "\n[*] Seeding 10 Product Catalog items...\n";

$products = [
    [
        'item_code'       => 'YAM-ENG-001',
        'name'            => 'Yamaha Genuine Cylinder Block Kit (Aerox / NMAX 155)',
        'category_id'     => $categoryMap['Engine Parts'] ?? 1,
        'brand_id'        => $brandMap['Yamaha'] ?? 1,
        'price'           => 3850.00,
        'default_cost'    => 2800.00,
        'quantity'        => 14,
        'consigned_stock' => 0,
        'max_stock'       => 30,
        'stock_status'    => 'Healthy',
        'unit'            => 'set',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'HON-ENG-002',
        'name'            => 'Honda Genuine Piston & Ring Assembly (Click 125/150)',
        'category_id'     => $categoryMap['Engine Parts'] ?? 1,
        'brand_id'        => $brandMap['Honda'] ?? 2,
        'price'           => 950.00,
        'default_cost'    => 650.00,
        'quantity'        => 28,
        'consigned_stock' => 0,
        'max_stock'       => 50,
        'stock_status'    => 'Healthy',
        'unit'            => 'set',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'NGK-SPK-003',
        'name'            => 'NGK Laser Iridium Spark Plug (CR8EIX)',
        'category_id'     => $categoryMap['Engine Parts'] ?? 1,
        'brand_id'        => $brandMap['NGK'] ?? 5,
        'price'           => 320.00,
        'default_cost'    => 180.00,
        'quantity'        => 65,
        'consigned_stock' => 0,
        'max_stock'       => 100,
        'stock_status'    => 'Healthy',
        'unit'            => 'pc',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'MOT-OIL-004',
        'name'            => 'Motul 7100 4T 10W-40 100% Synthetic Engine Oil 1L',
        'category_id'     => $categoryMap['Engine Parts'] ?? 1,
        'brand_id'        => $brandMap['Motul'] ?? 4,
        'price'           => 550.00,
        'default_cost'    => 380.00,
        'quantity'        => 42,
        'consigned_stock' => 10,
        'max_stock'       => 80,
        'stock_status'    => 'Healthy',
        'unit'            => 'bottle',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'BRM-CAL-005',
        'name'            => 'Brembo 2-Piston Rear Brake Caliper Gold Edition',
        'category_id'     => $categoryMap['Brakes & Suspension'] ?? 2,
        'brand_id'        => $brandMap['Brembo'] ?? 3,
        'price'           => 4500.00,
        'default_cost'    => 3200.00,
        'quantity'        => 3,
        'consigned_stock' => 0,
        'max_stock'       => 10,
        'stock_status'    => 'Low',
        'unit'            => 'pc',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'BRM-PAD-006',
        'name'            => 'Brembo Sintered Front Brake Pads Set',
        'category_id'     => $categoryMap['Brakes & Suspension'] ?? 2,
        'brand_id'        => $brandMap['Brembo'] ?? 3,
        'price'           => 750.00,
        'default_cost'    => 480.00,
        'quantity'        => 19,
        'consigned_stock' => 0,
        'max_stock'       => 40,
        'stock_status'    => 'Healthy',
        'unit'            => 'set',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'YAM-BRK-007',
        'name'            => 'Yamaha Heavy Duty Front Brake Rotor Disc 260mm',
        'category_id'     => $categoryMap['Brakes & Suspension'] ?? 2,
        'brand_id'        => $brandMap['Yamaha'] ?? 1,
        'price'           => 1650.00,
        'default_cost'    => 1100.00,
        'quantity'        => 2,
        'consigned_stock' => 0,
        'max_stock'       => 15,
        'stock_status'    => 'Critical',
        'unit'            => 'pc',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'MW-HL-008',
        'name'            => 'MotoWorld Matrix Dual Color Mini Driving LED Light Kit',
        'category_id'     => $categoryMap['Accessories'] ?? 3,
        'brand_id'        => $brandMap['MotoWorld PH'] ?? 6,
        'price'           => 1250.00,
        'default_cost'    => 750.00,
        'quantity'        => 16,
        'consigned_stock' => 5,
        'max_stock'       => 30,
        'stock_status'    => 'Healthy',
        'unit'            => 'set',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'MW-GRP-009',
        'name'            => 'MotoWorld Ergonomic Alloy Handlebar Grips (Pair)',
        'category_id'     => $categoryMap['Accessories'] ?? 3,
        'brand_id'        => $brandMap['MotoWorld PH'] ?? 6,
        'price'           => 390.00,
        'default_cost'    => 220.00,
        'quantity'        => 24,
        'consigned_stock' => 0,
        'max_stock'       => 50,
        'stock_status'    => 'Healthy',
        'unit'            => 'pair',
        'status'          => 'active'
    ],
    [
        'item_code'       => 'MW-MRR-010',
        'name'            => 'MotoWorld Stealth Winglet Rearview Side Mirrors',
        'category_id'     => $categoryMap['Accessories'] ?? 3,
        'brand_id'        => $brandMap['MotoWorld PH'] ?? 6,
        'price'           => 799.00,
        'default_cost'    => 450.00,
        'quantity'        => 0,
        'consigned_stock' => 0,
        'max_stock'       => 25,
        'stock_status'    => 'Out of Stock',
        'unit'            => 'pair',
        'status'          => 'active'
    ]
];

$prodStmt = $pdo->prepare("INSERT INTO `products` 
    (`item_code`, `name`, `category_id`, `brand_id`, `price`, `default_cost`, `quantity`, `consigned_stock`, `max_stock`, `stock_status`, `unit`, `status`)
    VALUES (:item_code, :name, :category_id, :brand_id, :price, :default_cost, :quantity, :consigned_stock, :max_stock, :stock_status, :unit, :status)
    ON DUPLICATE KEY UPDATE 
    `price` = VALUES(`price`), `default_cost` = VALUES(`default_cost`), `quantity` = VALUES(`quantity`), `stock_status` = VALUES(`stock_status`)");

foreach ($products as $p) {
    $prodStmt->execute($p);
}

$activeProducts = $pdo->query("SELECT id, item_code, name, price, default_cost, quantity, stock_status FROM products WHERE status = 'active'")->fetchAll();
echo "[✓] Successfully loaded " . count($activeProducts) . " products.\n";

// -----------------------------------------------------------------------------
// 4. Seed Transactional Data (Past 30 Days)
// -----------------------------------------------------------------------------
echo "\n[*] Generating historical transactions over the past 30 days...\n";

// 4.1 Purchase Orders & Deliveries
echo "  - Creating 3 Purchase Orders (Received/Completed, Approved, Pending)...\n";

$yamahaSupplierId = $supplierMap['Yamaha Genuine Parts Direct'] ?? 1;
$motoWorldSupplierId = $supplierMap['MotoWorld PH Official'] ?? 2;
$hondaSupplierId = $supplierMap['Honda Motor Parts & Components'] ?? 3;

// Find products for PO items
$prodMapByCode = [];
foreach ($activeProducts as $pr) {
    $prodMapByCode[$pr['item_code']] = $pr;
}

// PO 1: Received / Completed Purchase Order
$po1Date = date('Y-m-d H:i:s', strtotime('-24 days'));
$po1DeliveryDate = date('Y-m-d H:i:s', strtotime('-21 days'));

$poStmt = $pdo->prepare("INSERT INTO `purchase_orders` 
    (`po_number`, `supplier_id`, `total_amount`, `po_type`, `consignment_method`, `status`, `order_date`, `notes`)
    VALUES (:po_number, :supplier_id, :total_amount, :po_type, :consignment_method, :status, :order_date, :notes)");

$poiStmt = $pdo->prepare("INSERT INTO `purchase_order_items` 
    (`purchase_order_id`, `product_id`, `quantity`, `cost`, `total`, `is_available`, `quantity_received`)
    VALUES (:po_id, :product_id, :quantity, :cost, :total, 1, :quantity_received)");

$pohStmt = $pdo->prepare("INSERT INTO `purchase_order_history` (`purchase_order_id`, `status`, `notes`, `updated_by`, `timestamp`)
    VALUES (:po_id, :status, :notes, :updated_by, :timestamp)");

// PO 1 Execution
$po1Items = [
    ['prod' => $prodMapByCode['YAM-ENG-001'], 'qty' => 10],
    ['prod' => $prodMapByCode['YAM-BRK-007'], 'qty' => 12],
    ['prod' => $prodMapByCode['MOT-OIL-004'], 'qty' => 30]
];
$po1Total = 0;
foreach ($po1Items as $item) {
    $po1Total += $item['qty'] * $item['prod']['default_cost'];
}

$poStmt->execute([
    ':po_number'           => 'PO-2026-0001',
    ':supplier_id'         => $yamahaSupplierId,
    ':total_amount'        => $po1Total,
    ':po_type'             => 'Purchase',
    ':consignment_method'  => 'System',
    ':status'              => 'Completed',
    ':order_date'          => $po1Date,
    ':notes'               => 'Monthly fast-moving engine parts replenishment from Yamaha Direct'
]);
$po1Id = (int)$pdo->lastInsertId();

foreach ($po1Items as $item) {
    $itemTotal = $item['qty'] * $item['prod']['default_cost'];
    $poiStmt->execute([
        ':po_id'             => $po1Id,
        ':product_id'        => $item['prod']['id'],
        ':quantity'          => $item['qty'],
        ':cost'              => $item['prod']['default_cost'],
        ':total'             => $itemTotal,
        ':quantity_received' => $item['qty']
    ]);
}

$pohStmt->execute([
    ':po_id'      => $po1Id,
    ':status'     => 'Completed',
    ':notes'      => 'Goods received, inspected, and stock added to inventory',
    ':updated_by' => 'Super Admin',
    ':timestamp'  => $po1DeliveryDate
]);

// Delivery for PO 1
$delStmt = $pdo->prepare("INSERT INTO `deliveries` (`supplier_id`, `purchase_order_id`, `delivery_date`, `delivery_type`, `total_cost`, `recorded_by`)
    VALUES (:supplier_id, :po_id, :delivery_date, :delivery_type, :total_cost, :recorded_by)");
$delStmt->execute([
    ':supplier_id'    => $yamahaSupplierId,
    ':po_id'          => $po1Id,
    ':delivery_date'  => $po1DeliveryDate,
    ':delivery_type'  => 'Purchase',
    ':total_cost'     => $po1Total,
    ':recorded_by'    => 1
]);
$del1Id = (int)$pdo->lastInsertId();

$delItemStmt = $pdo->prepare("INSERT INTO `delivery_items` (`delivery_id`, `product_id`, `quantity`, `cost_at_time`)
    VALUES (:del_id, :prod_id, :qty, :cost)");
$movementStmt = $pdo->prepare("INSERT INTO `movements` (`product_id`, `type`, `quantity_change`, `stock_before`, `stock_after`, `reference_id`, `notes`, `recorded_by`, `created_at`)
    VALUES (:prod_id, :type, :qty_change, :stock_before, :stock_after, :ref_id, :notes, :recorded_by, :created_at)");

foreach ($po1Items as $item) {
    $delItemStmt->execute([
        ':del_id'  => $del1Id,
        ':prod_id' => $item['prod']['id'],
        ':qty'     => $item['qty'],
        ':cost'    => $item['prod']['default_cost']
    ]);

    $movementStmt->execute([
        ':prod_id'      => $item['prod']['id'],
        ':type'         => 'DELIVERY (PO)',
        ':qty_change'   => $item['qty'],
        ':stock_before' => max(0, $item['prod']['quantity'] - $item['qty']),
        ':stock_after'  => $item['prod']['quantity'],
        ':ref_id'       => 'PO-2026-0001',
        ':notes'        => 'Stock replenishment from PO-2026-0001 (Delivery #' . $del1Id . ')',
        ':recorded_by'  => 1,
        ':created_at'   => $po1DeliveryDate
    ]);
}

// PO 2: Approved Purchase Order (Awaiting Delivery)
$po2Date = date('Y-m-d H:i:s', strtotime('-5 days'));
$po2Items = [
    ['prod' => $prodMapByCode['MW-HL-008'], 'qty' => 15],
    ['prod' => $prodMapByCode['MW-GRP-009'], 'qty' => 20]
];
$po2Total = 0;
foreach ($po2Items as $item) {
    $po2Total += $item['qty'] * $item['prod']['default_cost'];
}

$poStmt->execute([
    ':po_number'           => 'PO-2026-0002',
    ':supplier_id'         => $motoWorldSupplierId,
    ':total_amount'        => $po2Total,
    ':po_type'             => 'Purchase',
    ':consignment_method'  => 'System',
    ':status'              => 'Approved',
    ':order_date'          => $po2Date,
    ':notes'               => 'Restock of MotoWorld LED lights and handlebar grips'
]);
$po2Id = (int)$pdo->lastInsertId();

foreach ($po2Items as $item) {
    $poiStmt->execute([
        ':po_id'             => $po2Id,
        ':product_id'        => $item['prod']['id'],
        ':quantity'          => $item['qty'],
        ':cost'              => $item['prod']['default_cost'],
        ':total'             => $item['qty'] * $item['prod']['default_cost'],
        ':quantity_received' => 0
    ]);
}
$pohStmt->execute([
    ':po_id'      => $po2Id,
    ':status'     => 'Approved',
    ':notes'      => 'PO approved by Super Admin; waiting for courier dispatch',
    ':updated_by' => 'Super Admin',
    ':timestamp'  => date('Y-m-d H:i:s', strtotime('-4 days'))
]);

// PO 3: Pending Consignment Order
$po3Date = date('Y-m-d H:i:s', strtotime('-1 days'));
$po3Items = [
    ['prod' => $prodMapByCode['HON-ENG-002'], 'qty' => 20],
    ['prod' => $prodMapByCode['NGK-SPK-003'], 'qty' => 50]
];
$po3Total = 0;
foreach ($po3Items as $item) {
    $po3Total += $item['qty'] * $item['prod']['default_cost'];
}

$poStmt->execute([
    ':po_number'           => 'PO-2026-0003',
    ':supplier_id'         => $hondaSupplierId,
    ':total_amount'        => $po3Total,
    ':po_type'             => 'Consignment',
    ':consignment_method'  => 'System',
    ':status'              => 'Pending',
    ':order_date'          => $po3Date,
    ':notes'               => 'Consignment request for Honda Click piston kits & spark plugs'
]);
$po3Id = (int)$pdo->lastInsertId();

foreach ($po3Items as $item) {
    $poiStmt->execute([
        ':po_id'             => $po3Id,
        ':product_id'        => $item['prod']['id'],
        ':quantity'          => $item['qty'],
        ':cost'              => $item['prod']['default_cost'],
        ':total'             => $item['qty'] * $item['prod']['default_cost'],
        ':quantity_received' => 0
    ]);
}
$pohStmt->execute([
    ':po_id'      => $po3Id,
    ':status'     => 'Pending',
    ':notes'      => 'Draft consignment request submitted for review',
    ':updated_by' => 'Lead Mechanic',
    ':timestamp'  => $po3Date
]);

echo "    [✓] PO-2026-0001 (Completed with Delivery & Movements)\n";
echo "    [✓] PO-2026-0002 (Approved)\n";
echo "    [✓] PO-2026-0003 (Pending Consignment)\n";

// 4.2 Seed 20 POS Sales Across Past 30 Days
echo "  - Generating 20 realistic POS Sales (with item COGS, labor services, and stock movements)...\n";

$saleStmt = $pdo->prepare("INSERT INTO `sales` 
    (`total_amount`, `recorded_by`, `customer_id`, `motorcycle_id`, `is_manual_entry`, `created_at`, `updated_at`)
    VALUES (:total_amount, :recorded_by, :customer_id, :motorcycle_id, 0, :created_at, :updated_at)");

$saleItemStmt = $pdo->prepare("INSERT INTO `sale_items` 
    (`sale_id`, `product_id`, `quantity`, `price_at_time`, `cost_of_goods_sold`)
    VALUES (:sale_id, :product_id, :quantity, :price_at_time, :cost_of_goods_sold)");

$saleServiceStmt = $pdo->prepare("INSERT INTO `sale_services`
    (`sale_id`, `service_id`, `price_at_time`)
    VALUES (:sale_id, :service_id, :price_at_time)");

// Generate 20 timestamps sorted chronologically across the last 30 days
$timestamps = [];
for ($i = 0; $i < 20; $i++) {
    // Distribute from 29 days ago up to today
    $daysAgo = (int)round((20 - $i - 1) * (28 / 20) + (rand(0, 100) / 100));
    $hour = rand(8, 17); // Store open 8 AM - 5 PM
    $minute = rand(0, 59);
    $second = rand(0, 59);
    $ts = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days {$hour}:{$minute}:{$second}"));
    $timestamps[] = $ts;
}
sort($timestamps);

$users = [1, 2, 3]; // Admin, Mechanic, Clerk
$availableInStock = array_filter($activeProducts, fn($p) => $p['quantity'] > 0);
$availableInStock = array_values($availableInStock);

$totalGrossSales = 0.0;
$totalItemsSold = 0;

for ($i = 0; $i < 20; $i++) {
    $saleDate = $timestamps[$i];
    $userId = $users[array_rand($users)];
    
    // 70% of sales are linked to registered customers, 30% walk-in
    $customer = (rand(1, 10) <= 7 && !empty($customerList)) ? $customerList[array_rand($customerList)] : null;
    $customerId = $customer ? $customer['id'] : null;

    // Pick 1 to 3 distinct items
    $numItems = rand(1, 3);
    $chosenKeys = (array)array_rand($availableInStock, min($numItems, count($availableInStock)));
    
    $saleItems = [];
    $saleTotal = 0.0;
    
    foreach ($chosenKeys as $k) {
        $p = $availableInStock[$k];
        $qty = rand(1, 2);
        $itemTotal = $qty * $p['price'];
        $itemCogs = $qty * $p['default_cost'];
        $saleTotal += $itemTotal;
        $totalItemsSold += $qty;

        $saleItems[] = [
            'prod_id' => $p['id'],
            'qty'     => $qty,
            'price'   => $p['price'],
            'cogs'    => $itemCogs
        ];
    }

    // 40% chance of an added labor service
    $chosenService = null;
    if (rand(1, 10) <= 4 && !empty($serviceList)) {
        $chosenService = $serviceList[array_rand($serviceList)];
        $saleTotal += (float)$chosenService['charge'];
    }

    // Insert sale
    $saleStmt->execute([
        ':total_amount'   => $saleTotal,
        ':recorded_by'    => $userId,
        ':customer_id'    => $customerId,
        ':motorcycle_id'  => null,
        ':created_at'     => $saleDate,
        ':updated_at'     => $saleDate
    ]);
    $saleId = (int)$pdo->lastInsertId();
    $totalGrossSales += $saleTotal;

    // Insert sale items and stock movement
    foreach ($saleItems as $sItem) {
        $saleItemStmt->execute([
            ':sale_id'            => $saleId,
            ':product_id'         => $sItem['prod_id'],
            ':quantity'           => $sItem['qty'],
            ':price_at_time'      => $sItem['price'],
            ':cost_of_goods_sold' => $sItem['cogs']
        ]);

        // Stock movement entry
        $movementStmt->execute([
            ':prod_id'      => $sItem['prod_id'],
            ':type'         => 'SALE',
            ':qty_change'   => -$sItem['qty'],
            ':stock_before' => 30, // approximate benchmark
            ':stock_after'  => 30 - $sItem['qty'],
            ':ref_id'       => 'SALE-' . $saleId,
            ':notes'        => 'POS Sale transaction #' . $saleId,
            ':recorded_by'  => $userId,
            ':created_at'   => $saleDate
        ]);
    }

    // Insert service if applicable
    if ($chosenService) {
        $saleServiceStmt->execute([
            ':sale_id'       => $saleId,
            ':service_id'    => $chosenService['id'],
            ':price_at_time' => $chosenService['charge']
        ]);
    }
}

echo "    [✓] 20 POS Sales recorded with total volume of ₱" . number_format($totalGrossSales, 2) . " across past 30 days.\n";

// 4.3 Audit Logs
echo "  - Seeding System Audit Trail...\n";
$auditStmt = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `action`, `details`, `entity_type`, `entity_id`, `created_at`)
    VALUES (:user_id, :action, :details, :entity_type, :entity_id, :created_at)");

$auditEntries = [
    [
        'user_id'     => 1,
        'action'      => 'USER_LOGIN',
        'details'     => 'Super Admin successfully authenticated from 127.0.0.1',
        'entity_type' => 'Auth',
        'entity_id'   => 1,
        'created_at'  => date('Y-m-d H:i:s', strtotime('-25 days'))
    ],
    [
        'user_id'     => 1,
        'action'      => 'PURCHASE_ORDER_CREATED',
        'details'     => 'Created PO-2026-0001 with 3 item lines totaling ₱' . number_format($po1Total, 2),
        'entity_type' => 'PurchaseOrder',
        'entity_id'   => $po1Id,
        'created_at'  => $po1Date
    ],
    [
        'user_id'     => 1,
        'action'      => 'STOCK_DELIVERY_RECEIVED',
        'details'     => 'Processed incoming delivery receipt for PO-2026-0001',
        'entity_type' => 'Delivery',
        'entity_id'   => $del1Id,
        'created_at'  => $po1DeliveryDate
    ],
    [
        'user_id'     => 2,
        'action'      => 'INVENTORY_ADJUSTMENT',
        'details'     => 'Inspected Motul 7100 oil inventory and verified seal integrity',
        'entity_type' => 'Inventory',
        'entity_id'   => $prodMapByCode['MOT-OIL-004']['id'],
        'created_at'  => date('Y-m-d H:i:s', strtotime('-15 days'))
    ],
    [
        'user_id'     => 3,
        'action'      => 'PRICE_UPDATED',
        'details'     => 'Adjusted retail price for Brembo Gold Caliper to ₱4,500.00',
        'entity_type' => 'Product',
        'entity_id'   => $prodMapByCode['BRM-CAL-005']['id'],
        'created_at'  => date('Y-m-d H:i:s', strtotime('-8 days'))
    ],
    [
        'user_id'     => 1,
        'action'      => 'PURCHASE_ORDER_APPROVED',
        'details'     => 'Approved PO-2026-0002 for MotoWorld PH supplies',
        'entity_type' => 'PurchaseOrder',
        'entity_id'   => $po2Id,
        'created_at'  => date('Y-m-d H:i:s', strtotime('-4 days'))
    ]
];

foreach ($auditEntries as $entry) {
    $auditStmt->execute($entry);
}
echo "    [✓] " . count($auditEntries) . " system audit events logged.\n";

// -----------------------------------------------------------------------------
// 5. Summary Statistics
// -----------------------------------------------------------------------------
echo "\n===============================================================\n";
echo "   Database Test Data Population Completed Successfully!       \n";
echo "===============================================================\n";
echo "  • Suppliers Count       : " . $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn() . "\n";
echo "  • Categories Count      : " . $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn() . "\n";
echo "  • Products Count        : " . $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
echo "  • Labor Services Count  : " . $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn() . "\n";
echo "  • Client Profiles Count : " . $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() . "\n";
echo "  • Purchase Orders Count : " . $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn() . "\n";
echo "  • Deliveries Count      : " . $pdo->query("SELECT COUNT(*) FROM deliveries")->fetchColumn() . "\n";
echo "  • POS Sales Count       : " . $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn() . " (Gross: ₱" . number_format($totalGrossSales, 2) . ")\n";
echo "  • Stock Movements Count : " . $pdo->query("SELECT COUNT(*) FROM movements")->fetchColumn() . "\n";
echo "  • Audit Log Events      : " . $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn() . "\n";
echo "===============================================================\n";
