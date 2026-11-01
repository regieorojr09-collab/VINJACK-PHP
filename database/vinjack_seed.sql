-- VinJack Motorworks
-- Seed Data for Default Roles, Permissions, Accounts, and System Settings

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
('Super Admin'),
('Admin'),
('Salesperson');

-- 3. Map Default Permissions to Roles
-- 3a. Admin (Lead Mechanic) restricted strictly to Dashboard, POS, and Inventory
INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`)
SELECT r.id, p.`key`
FROM `role_permissions` r
JOIN `all_permissions` p ON p.`key` IN (
    'canViewDashboard',
    'canViewInventory',
    'canManageInventory',
    'canManageSales'
)
WHERE r.`role` = 'Admin';

-- 3b. Salesperson (Counter Clerk) restricted strictly to Dashboard, POS, and read-only Inventory
INSERT IGNORE INTO `role_permission_entries` (`role_permission_id`, `permission_key`)
SELECT r.id, p.`key`
FROM `role_permissions` r
JOIN `all_permissions` p ON p.`key` IN (
    'canViewDashboard',
    'canViewInventory',
    'canManageSales'
)
WHERE r.`role` = 'Salesperson';

-- 4. Seed Default System & Role Accounts (Password: 'password123')
INSERT IGNORE INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`) VALUES
('Super Admin', 'admin', 'admin@vinjack.com', '$2y$10$XA4NQ26WMahGiLUBbk/oW.5bpwBqmppMsVfmCe5kKgWLhqIuEe9JO', 'Super Admin', 'active'),
('Lead Mechanic', 'mechanic', 'mechanic@vinjack.com', '$2y$10$XA4NQ26WMahGiLUBbk/oW.5bpwBqmppMsVfmCe5kKgWLhqIuEe9JO', 'Admin', 'active'),
('Counter Clerk', 'clerk', 'clerk@vinjack.com', '$2y$10$XA4NQ26WMahGiLUBbk/oW.5bpwBqmppMsVfmCe5kKgWLhqIuEe9JO', 'Salesperson', 'active');

-- 5. Seed Initial System Settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'VinJack Motorworks'),
('company_address', 'Marikina City, Philippines'),
('contact_email', 'contact@vinjack.com'),
('currency', 'PHP'),
('tax_rate', '0'),
('enable_email_notifications', 'true');

COMMIT;
