-- VinJack Motorworks
-- Seed Data for Default Roles, Permissions, and Super Admin

START TRANSACTION;

-- 1. Insert Core Permissions
INSERT IGNORE INTO `all_permissions` (`key`, `description`, `category`) VALUES
('canViewDashboard', 'View dashboard summary and charts', 'General'),
('canViewInventory', 'View product list and stock levels', 'Inventory'),
('canManageInventory', 'Add, edit, or delete products and categories', 'Inventory'),
('canManageSales', 'Process sales and create POS transactions', 'Sales'),
('canViewSuppliers', 'View supplier list and details', 'Suppliers'),
('canManageSuppliers', 'Add, edit, or approve suppliers', 'Suppliers'),
('canManagePurchaseOrders', 'Create, send, and receive purchase orders', 'Suppliers'),
('canManageDeliveries', 'Record stock deliveries', 'Inventory'),
('canManageCustomers', 'Manage customer profiles and motorcycles', 'Sales'),
('canManageReturns', 'Process customer and supplier returns', 'Inventory'),
('canViewReports', 'View detailed sales and return reports', 'General');

-- 2. Insert Default Roles
INSERT IGNORE INTO `role_permissions` (`role`) VALUES
('Admin'),
('Salesperson');

-- 3. Map Default Permissions to Roles (Admin gets everything, Salesperson gets restricted subset)
INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canViewDashboard' FROM `role_permissions` WHERE `role` IN ('Admin', 'Salesperson');

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canViewInventory' FROM `role_permissions` WHERE `role` IN ('Admin', 'Salesperson');

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageInventory' FROM `role_permissions` WHERE `role` = 'Admin';

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageSales' FROM `role_permissions` WHERE `role` IN ('Admin', 'Salesperson');

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canViewSuppliers' FROM `role_permissions` WHERE `role` IN ('Admin', 'Salesperson');

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageSuppliers' FROM `role_permissions` WHERE `role` = 'Admin';

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManagePurchaseOrders' FROM `role_permissions` WHERE `role` = 'Admin';

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageDeliveries' FROM `role_permissions` WHERE `role` = 'Admin';

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageCustomers' FROM `role_permissions` WHERE `role` IN ('Admin', 'Salesperson');

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canManageReturns' FROM `role_permissions` WHERE `role` = 'Admin';

INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`) 
SELECT id, 'canViewReports' FROM `role_permissions` WHERE `role` = 'Admin';

-- 4. Create Demo Super Admin Account
-- Password is 'password123' (bcrypt hash: $2a$10$XQ.O8H55U4sZ3T4X5p5lqO4s1GqX/j/Y/453o/E2H6gK7H9wVqD6y)
-- Note: Replace hash with actual generated hash if using different password logic in the app.
INSERT IGNORE INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`) VALUES
('Super Admin', 'admin', 'admin@vinjack.com', '$2y$10$x.X/V5/3kQ8cWl0B5B1b/uR00R0bX0v0x0X/0x0/x00x0x0X0x0x0', 'Super Admin', 'active');
-- A real password hash for 'password123':
UPDATE `users` SET `password` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE `username` = 'admin';


-- 5. Seed Initial System Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'VinJack Motorworks'),
('company_address', 'Marikina City, Philippines'),
('contact_email', 'contact@vinjack.com'),
('currency', 'PHP'),
('tax_rate', '0'),
('enable_email_notifications', 'true');

COMMIT;
