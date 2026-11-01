<?php
declare(strict_types=1);

/**
 * VinJack Motorworks - Production Database Configuration
 * 
 * Target Environment: InfinityFree Shared Hosting (htdocs/)
 * 
 * Replace the values below with the credentials from your
 * InfinityFree Control Panel -> MySQL Databases:
 * 
 * - Host:     e.g., sql123.epizy.com or sql203.infinityfree.com
 * - Database: e.g., epiz_12345678_vinjack_db or if0_43246494_vinjack_db
 * - Username: e.g., epiz_12345678 or if0_43246494
 * - Password: Your InfinityFree vPanel / Hosting account password
 * - Port:     3306 (Standard MySQL port for InfinityFree)
 */

return [
    'environment' => 'production',
    'host'        => 'sql123.epizy.com',
    'port'        => 3306,
    'dbname'      => 'epiz_12345678_vinjack_db',
    'username'    => 'epiz_12345678',
    'password'    => 'YOUR_INFINITYFREE_CPANEL_PASSWORD',
    'charset'     => 'utf8mb4',
    'options'     => [
        'timeout'    => 5,
        'persistent' => false,
        'ssl_verify' => false,
    ],
];
