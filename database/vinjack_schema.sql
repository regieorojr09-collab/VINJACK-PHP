-- =============================================================================
-- VinJack Motorworks - Web-Based Sales & Inventory Management System
-- Database Schema & Initial Seed DDL Script (MySQL 8.0+ / MariaDB 10.4+)
-- Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Normalized to Third Normal Form (3NF) with Strict Referential Integrity
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- -----------------------------------------------------------------------------
-- 1. Reference & Classification Tables
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `charge` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL UNIQUE,
    `email` VARCHAR(255) NULL,
    `contact_person` VARCHAR(150) NULL,
    `contact_number` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Approved',
    `default_payment_terms` ENUM('Cash', 'Consignment', 'Terms') NOT NULL DEFAULT 'Cash',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_suppliers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. User Authentication & Role-Based Access Control (RBAC)
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Super Admin', 'Admin', 'Salesperson') NOT NULL DEFAULT 'Salesperson',
    `status` ENUM('active', 'pending', 'locked', 'inactive') NOT NULL DEFAULT 'active',
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_failed_attempt` DATETIME NULL,
    `notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `notification_time` VARCHAR(5) NOT NULL DEFAULT '08:00',
    `daily_report_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `daily_report_time` VARCHAR(5) NOT NULL DEFAULT '08:30',
    `dashboard_time_range` VARCHAR(20) NOT NULL DEFAULT 'all',
    `dashboard_category_id` INT UNSIGNED NULL,
    `dashboard_supplier_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`),
    CONSTRAINT `fk_users_dash_category` FOREIGN KEY (`dashboard_category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_dash_supplier` FOREIGN KEY (`dashboard_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `all_permissions`;
CREATE TABLE `all_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NOT NULL,
    `category` ENUM('General', 'Inventory', 'Sales', 'Suppliers', 'Admin') NOT NULL DEFAULT 'General',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role` ENUM('Super Admin', 'Admin', 'Salesperson') NOT NULL UNIQUE,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permission_entries`;
CREATE TABLE `role_permission_entries` (
    `role_permission_id` INT UNSIGNED NOT NULL,
    `permission_key` VARCHAR(50) NOT NULL,
    PRIMARY KEY (`role_permission_id`, `permission_key`),
    CONSTRAINT `fk_role_perm_id` FOREIGN KEY (`role_permission_id`) REFERENCES `role_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Product Catalog & Inventory
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `brand_id` INT UNSIGNED NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `default_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 0,
    `consigned_stock` INT NOT NULL DEFAULT 0,
    `max_stock` INT UNSIGNED NOT NULL DEFAULT 100,
    `stock_status` ENUM('Healthy', 'Low', 'Critical', 'Out of Stock') NOT NULL DEFAULT 'Out of Stock',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `unit` VARCHAR(20) NOT NULL DEFAULT 'pc',
    `image` VARCHAR(500) NULL,
    `is_serialized` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_products_category` (`category_id`),
    INDEX `idx_products_brand` (`brand_id`),
    INDEX `idx_products_stock_status` (`stock_status`),
    INDEX `idx_products_name` (`name`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_supplier_costs`;
CREATE TABLE `product_supplier_costs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `cost` DECIMAL(10, 2) NOT NULL,
    `note` TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_prod_supplier` (`product_id`, `supplier_id`),
    CONSTRAINT `fk_psc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_psc_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `serialized_items`;
CREATE TABLE `serialized_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `serial_number` VARCHAR(100) NOT NULL,
    `status` ENUM('Available', 'Sold', 'Returned', 'Defective', 'Lost') NOT NULL DEFAULT 'Available',
    `purchase_order_id` INT UNSIGNED NULL,
    `date_received` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_prod_serial` (`product_id`, `serial_number`),
    INDEX `idx_serial_status` (`status`),
    CONSTRAINT `fk_serial_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Customer & Motorcycle Profiles
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(255) NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_customers_name` (`name`),
    INDEX `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `motorcycles`;
CREATE TABLE `motorcycles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_id` INT UNSIGNED NOT NULL,
    `make` VARCHAR(100) NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `year` SMALLINT UNSIGNED NULL,
    `color` VARCHAR(50) NULL,
    `plate_number` VARCHAR(20) NULL UNIQUE,
    `vin` VARCHAR(50) NULL UNIQUE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_motorcycles_owner` (`owner_id`),
    INDEX `idx_motorcycles_plate` (`plate_number`),
    CONSTRAINT `fk_motorcycles_owner` FOREIGN KEY (`owner_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Procurement & Purchase Orders
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `po_number` VARCHAR(30) NOT NULL UNIQUE,
    `supplier_id` INT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `po_type` ENUM('Purchase', 'Consignment') NOT NULL DEFAULT 'Purchase',
    `consignment_method` ENUM('System', 'Manual') NOT NULL DEFAULT 'System',
    `terms_and_conditions` TEXT NULL,
    `status` ENUM('Pending', 'Awaiting Approval', 'Approved', 'Partially Received', 'Completed', 'Cancelled', 'Agreement Uploaded - Awaiting Delivery') NOT NULL DEFAULT 'Pending',
    `order_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `supplier_notes` TEXT NULL,
    `supplier_response_token` VARCHAR(255) NULL UNIQUE,
    `delivery_receipt_url` VARCHAR(500) NULL,
    `signed_agreement_url` VARCHAR(500) NULL,
    `countersigned_agreement_url` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_po_supplier` (`supplier_id`),
    INDEX `idx_po_status` (`status`),
    INDEX `idx_po_date` (`order_date`),
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `cost` DECIMAL(10, 2) NOT NULL,
    `total` DECIMAL(10, 2) NOT NULL,
    `supplier_updated_cost` DECIMAL(10, 2) NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `quantity_received` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_poi_po` (`purchase_order_id`),
    INDEX `idx_poi_product` (`product_id`),
    CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_poi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_order_history`;
CREATE TABLE `purchase_order_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(100) NOT NULL,
    `notes` TEXT NULL,
    `updated_by` VARCHAR(100) NOT NULL DEFAULT 'System',
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_poh_po` (`purchase_order_id`),
    CONSTRAINT `fk_poh_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `deliveries`;
CREATE TABLE `deliveries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` INT UNSIGNED NULL,
    `purchase_order_id` INT UNSIGNED NULL,
    `delivery_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `delivery_type` ENUM('Purchase', 'Consignment') NOT NULL DEFAULT 'Purchase',
    `total_cost` DECIMAL(10, 2) NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_deliveries_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_deliveries_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_items`;
CREATE TABLE `delivery_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `delivery_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `cost_at_time` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_del_items_del` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_del_items_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. POS Sales Transactions
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `recorded_by` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NULL,
    `motorcycle_id` INT UNSIGNED NULL,
    `customer_receipt_image` TEXT NULL,
    `is_manual_entry` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sales_user` (`recorded_by`),
    INDEX `idx_sales_customer` (`customer_id`),
    INDEX `idx_sales_created` (`created_at`),
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_motorcycle` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `price_at_time` DECIMAL(10, 2) NOT NULL,
    `cost_of_goods_sold` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    INDEX `idx_sale_items_sale` (`sale_id`),
    INDEX `idx_sale_items_product` (`product_id`),
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sale_services`;
CREATE TABLE `sale_services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `price_at_time` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_sale_services_sale` (`sale_id`),
    INDEX `idx_sale_services_service` (`service_id`),
    CONSTRAINT `fk_sale_services_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sale_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Returns Management
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `returns`;
CREATE TABLE `returns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_sale_id` INT UNSIGNED NOT NULL,
    `reason` TEXT NOT NULL,
    `outcome` ENUM('Restocked', 'Refunded', 'Replaced', 'Discarded') NOT NULL DEFAULT 'Restocked',
    `total_refund_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_returns_sale` FOREIGN KEY (`original_sale_id`) REFERENCES `sales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_returns_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `return_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `price_at_time` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ret_items_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ret_items_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `return_services`;
CREATE TABLE `return_services` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `return_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `price_at_time` DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ret_serv_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ret_serv_serv` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_returns`;
CREATE TABLE `supplier_returns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` INT UNSIGNED NOT NULL,
    `return_date` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sup_ret_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sup_ret_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_return_items`;
CREATE TABLE `supplier_return_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_return_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `reason` ENUM('Defective', 'Wrong Item', 'Overstock', 'Other') NOT NULL,
    `was_consigned` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sri_return` FOREIGN KEY (`supplier_return_id`) REFERENCES `supplier_returns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sri_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Consignment Payables Ledger
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `consignment_payables`;
CREATE TABLE `consignment_payables` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `quantity_sold` INT UNSIGNED NOT NULL,
    `cost_at_time_of_sale` DECIMAL(10, 2) NOT NULL,
    `amount_owed` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('Owed', 'Paid') NOT NULL DEFAULT 'Owed',
    `paid_date` DATETIME NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cp_status` (`status`),
    CONSTRAINT `fk_cp_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cp_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Immutable Stock Ledger & System Audit
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `movements`;
CREATE TABLE `movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `type` ENUM('SALE', 'DELIVERY', 'ADJUSTMENT', 'RETURN', 'DELIVERY (PO)') NOT NULL,
    `quantity_change` INT NOT NULL,
    `stock_before` INT NOT NULL,
    `stock_after` INT NOT NULL,
    `reference_id` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_movements_prod` (`product_id`),
    INDEX `idx_movements_type` (`type`),
    INDEX `idx_movements_date` (`created_at`),
    CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_created` (`created_at` DESC),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('LOW_STOCK', 'CRITICAL_STOCK', 'OUT_OF_STOCK', 'USER_ACTION', 'REQUEST_STATUS') NOT NULL,
    `link` VARCHAR(255) NULL,
    `image` VARCHAR(500) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notif_user` (`user_id`),
    INDEX `idx_notif_read` (`is_read`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 10. Initial Turnkey Seed Data
-- =============================================================================

START TRANSACTION;

-- Seed System Permissions
INSERT INTO `all_permissions` (`key`, `description`, `category`) VALUES
('canViewDashboard', 'View dashboard summary metrics and statistics', 'General'),
('canViewInventory', 'Browse product catalog and view stock levels', 'Inventory'),
('canManageInventory', 'Create, update, or remove inventory items and categories', 'Inventory'),
('canManageSales', 'Execute POS transactions, generate receipts, and checkout', 'Sales'),
('canViewSuppliers', 'Access supplier directory and profiles', 'Suppliers'),
('canManageSuppliers', 'Create and modify supplier agreements and profiles', 'Suppliers'),
('canManagePurchaseOrders', 'Issue and manage supplier purchase orders', 'Suppliers'),
('canManageDeliveries', 'Process incoming goods receipts and stock deliveries', 'Inventory'),
('canManageCustomers', 'Maintain customer directory and motorcycle records', 'Sales'),
('canManageReturns', 'Process customer warranty claims and returns', 'Inventory'),
('canViewReports', 'Access business financial, COGS, and sales reports', 'General');

-- Seed System Roles
INSERT INTO `role_permissions` (`id`, `role`) VALUES
(1, 'Super Admin'),
(2, 'Admin'),
(3, 'Salesperson');

-- Seed Role Permission Mappings
-- Admin Permissions
INSERT INTO `role_permission_entries` (`role_permission_id`, `permission_key`) VALUES
(2, 'canViewDashboard'),
(2, 'canViewInventory'),
(2, 'canManageInventory'),
(2, 'canManageSales'),
(2, 'canViewSuppliers'),
(2, 'canManageSuppliers'),
(2, 'canManagePurchaseOrders'),
(2, 'canManageDeliveries'),
(2, 'canManageCustomers'),
(2, 'canManageReturns'),
(2, 'canViewReports');

-- Salesperson Permissions (Restricted)
INSERT INTO `role_permission_entries` (`role_permission_id`, `permission_key`) VALUES
(3, 'canViewDashboard'),
(3, 'canViewInventory'),
(3, 'canManageSales'),
(3, 'canViewSuppliers'),
(3, 'canManageCustomers');

-- Seed Default Super Admin Account & Demo Accounts
-- Passwords: 'password123' hashed with native PASSWORD_BCRYPT:
-- '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `password`, `role`, `status`) VALUES
(1, 'Super Admin', 'admin', 'admin@vinjack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'active'),
(2, 'Lead Mechanic', 'mechanic', 'mechanic@vinjack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'active'),
(3, 'Counter Clerk', 'clerk', 'clerk@vinjack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Salesperson', 'active');

-- Seed Default Product Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Tires & Wheels', 'Performance tires, inner tubes, rims, and wheel accessories'),
(2, 'Brakes & Suspension', 'Brake pads, discs, calipers, shock absorbers, and fork oil'),
(3, 'Engine Oils & Lubricants', 'Synthetic engine oils, chain lube, coolant, and brake fluid'),
(4, 'Electrical & Lighting', 'Batteries, spark plugs, LED headlights, and wiring components'),
(5, 'Accessories & Gear', 'Helmets, riding gloves, saddle bags, and phone mounts');

-- Seed Default Brands
INSERT INTO `brands` (`id`, `name`) VALUES
(1, 'Motul'),
(2, 'Brembo'),
(3, 'Michelin'),
(4, 'Pirelli'),
(5, 'NGK');

-- Seed Sample Approved Suppliers
INSERT INTO `suppliers` (`id`, `name`, `email`, `contact_person`, `contact_number`, `address`, `status`, `default_payment_terms`) VALUES
(1, 'Apex Moto Supplies Philippines', 'orders@apexmotoph.com', 'Marco Santos', '+63 917 123 4567', '128 Rizal Avenue, Caloocan City, Metro Manila', 'Approved', 'Terms'),
(2, 'Brembo Philippines Distribution', 'sales@bremboph.com', 'Elena Cruz', '+63 918 987 6543', '45 EDSA, Mandaluyong City, Metro Manila', 'Approved', 'Cash'),
(3, 'Motul Lubricants Direct Phils', 'supply@motuldirect.ph', 'Roberto Tan', '+63 922 456 7890', 'BGC Taguig City, Metro Manila', 'Approved', 'Consignment');

-- Seed Common Shop Services
INSERT INTO `services` (`id`, `name`, `description`, `charge`, `status`) VALUES
(1, 'Oil Change & Filter Service', 'Complete engine flush, oil drain, filter replacement, and fluid top-up', 250.00, 'active'),
(2, 'Tire Mounting & Wheel Balancing', 'Dismount, inspect rim, mount new tire, and static balance', 150.00, 'active'),
(3, 'Brake Caliper Cleaning & Pad Replacement', 'Clean pistons, inspect caliper seals, bleed brake line, and install pads', 200.00, 'active'),
(4, 'Full Diagnostic & Tune-up', 'Spark plug check, carb/fuel injection sync, battery testing, chain lube', 500.00, 'active');

-- Seed Initial System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'VinJack Motorworks'),
('company_address', 'Marikina City, Metro Manila, Philippines'),
('contact_email', 'contact@vinjack.com'),
('contact_phone', '+63 (02) 8945-1234'),
('currency', 'PHP'),
('tax_rate', '0.00'),
('enable_xml_receipts', 'true'),
('enable_email_notifications', 'true');

COMMIT;
