<?php
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=vinjack_db', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$tables = [
    'categories',
    'brands',
    'suppliers',
    'products',
    'product_supplier_costs',
    'services',
    'customers',
    'motorcycles',
    'purchase_orders',
    'purchase_order_items',
    'deliveries',
    'delivery_items',
    'sales',
    'sale_items',
    'sale_services',
    'movements'
];

$sql = "-- VinJack Motorworks Sample Production Data\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
$sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT * FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        continue;
    }

    $cols = array_keys($rows[0]);
    $colList = '`' . implode('`, `', $cols) . '`';

    $sql .= "-- Table: {$table}\n";
    foreach ($rows as $row) {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = $pdo->quote((string)$v);
            }
        }
        $sql .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $vals) . ");\n";
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents(__DIR__ . '/../database/vinjack_sample_data.sql', $sql);
echo "Clean SQL generated successfully! File size: " . strlen($sql) . " bytes\n";
