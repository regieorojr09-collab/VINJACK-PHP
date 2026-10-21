# Web-Based Sales & Inventory Management System for Vinjack Motorworks
## Comprehensive Architectural Analysis & Migration Blueprint

---

## 1. Executive Summary of Audited Codebase

### Source System Profile

| Dimension | Value |
|---|---|
| **Stack** | MERN (MongoDB 6, Express 4, React 19, Node.js 18+) |
| **Mongoose Models** | 20 schemas across 20 files |
| **Express Routes** | 22 route files → **~85+ individual endpoints** |
| **Controllers** | 22 controllers (~166 KB of business logic) |
| **Middleware** | 3 files: JWT auth/RBAC (`protect`, `authorize`, `checkPermission`), Multer upload |
| **Utility Services** | 8 files (stock manager, movement logger, notification manager, email service, backup service, GCS storage, logger, migration) |
| **Cron Jobs** | 1 file (low-stock email, daily sales report, scheduled backup) |
| **Client Pages** | 23 page-level React components (~460 KB) |
| **Client Components** | 35 reusable components + 3 report sub-components |
| **Client API Modules** | 15 Axios-based API wrappers |
| **Client Context** | 3 React Contexts: `AuthContext`, `ConfirmationContext`, `WarningContext` |
| **Real-Time** | Socket.IO for stock alerts and notification push |
| **Authentication** | JWT + bcryptjs + login throttling + forced password change |
| **Authorization** | 3-tier RBAC: Super Admin (bypass all), Admin, Salesperson — permission keys stored in DB |

### Domain Entities Identified (20 Collections → 25+ Target Tables)

`User`, `Product`, `Category`, `Brand`, `Sale` (+ embedded `items`/`services`), `Service`, `Customer`, `Motorcycle`, `Supplier`, `PurchaseOrder` (+ embedded `items`/`history`), `Delivery` (+ embedded `productsReceived`), `ConsignmentPayable`, `Return` (+ embedded `itemsReturned`/`servicesReturned`), `SupplierReturn` (+ embedded `productsReturned`), `Movement`, `AuditLog`, `Notification`, `Setting`, `Counter`, `RolePermission`, `AllPermission`

### Legacy Pain Points & Structural Debt Flagged

| Issue | Severity | Details |
|---|---|---|
| **Embedded arrays as pseudo-tables** | 🔴 Critical | `Sale.items`, `PO.items`, `PO.history`, `Return.itemsReturned`, `Delivery.productsReceived`, `SupplierReturn.productsReturned`, `Product.supplierCosts`, `Product.serializedItems` — all must become proper relational pivot/junction tables |
| **MongoDB aggregation pipelines** | 🔴 Critical | `reportController.js` and `DashboardPage.js` use complex `$lookup`, `$unwind`, `$group`, `$dateToString` pipelines that must be converted to SQL `JOIN`/`GROUP BY`/`DATE_FORMAT` |
| **No backend validation layer** | 🟡 Medium | Validation is scattered across controllers with inconsistent patterns — should be centralized |
| **Receipt images stored as Base64 in DB** | 🟡 Medium | `customerReceiptImage` field stores full Base64 data strings in MongoDB — should use file storage |
| **Tight Socket.IO coupling** | 🟡 Medium | Stock notifications are tightly coupled to controller logic via `req.app.get('socketio')` — not directly portable to PHP but will be handled via polling/SSE in the target |
| **No API versioning** | 🟠 Low | All routes are `/api/*` without version prefix |
| **GCS backup dependency** | 🟠 Low | Backup/restore uses `mongodump` + GCS — replaced with MySQL dump + local storage for target |

---

## 2. Target Architecture Overview

```
Target Stack:
├── Backend:   PHP 8.2+ (PDO/MySQL), Apache/XAMPP
├── Database:  MySQL 8.0+ (InnoDB, strict FK constraints)
├── Config:    XML for system configuration & data interchange
├── Frontend:  Vanilla ES6+ SPA (no React, no framework)
├── Styling:   Vanilla CSS (premium, modern design system)
└── Real-Time: Server-Sent Events (SSE) or Polling (replaces Socket.IO)
```

### Proposed Directory Structure

```
d:\WEB System\vinjack/
├── index.html                     # SPA entry point
├── manifest.xml                   # App metadata (XML interchange)
├── config/
│   ├── database.xml               # DB connection config (XML)
│   └── app.xml                    # App settings (XML)
├── api/                           # PHP REST API
│   ├── index.php                  # Front controller / router
│   ├── .htaccess                  # URL rewriting
│   ├── config/
│   │   ├── Database.php           # PDO singleton
│   │   └── Config.php             # XML config reader
│   ├── middleware/
│   │   ├── AuthMiddleware.php     # JWT verify + protect
│   │   ├── RBACMiddleware.php     # Permission check
│   │   └── CorsMiddleware.php     # CORS headers
│   ├── models/                    # Data Access Objects (DAO)
│   │   ├── UserDAO.php
│   │   ├── ProductDAO.php
│   │   ├── SaleDAO.php
│   │   ├── PurchaseOrderDAO.php
│   │   ├── DeliveryDAO.php
│   │   ├── SupplierDAO.php
│   │   ├── CustomerDAO.php
│   │   ├── MotorcycleDAO.php
│   │   ├── ReturnDAO.php
│   │   ├── SupplierReturnDAO.php
│   │   ├── MovementDAO.php
│   │   ├── ConsignmentPayableDAO.php
│   │   ├── NotificationDAO.php
│   │   ├── AuditLogDAO.php
│   │   ├── CategoryDAO.php
│   │   ├── BrandDAO.php
│   │   ├── ServiceDAO.php
│   │   ├── SettingDAO.php
│   │   ├── PermissionDAO.php
│   │   └── CounterDAO.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── ProductController.php
│   │   ├── SaleController.php
│   │   ├── PurchaseOrderController.php
│   │   ├── DeliveryController.php
│   │   ├── SupplierController.php
│   │   ├── CustomerController.php
│   │   ├── MotorcycleController.php
│   │   ├── ReturnController.php
│   │   ├── SupplierReturnController.php
│   │   ├── ConsignmentController.php
│   │   ├── MovementController.php
│   │   ├── ReportController.php
│   │   ├── DashboardController.php
│   │   ├── NotificationController.php
│   │   ├── AuditLogController.php
│   │   ├── CategoryController.php
│   │   ├── BrandController.php
│   │   ├── ServiceController.php
│   │   ├── SettingsController.php
│   │   ├── PermissionController.php
│   │   └── AdjustmentController.php
│   ├── services/                  # Shared business logic
│   │   ├── StockManager.php
│   │   ├── MovementLogger.php
│   │   ├── NotificationManager.php
│   │   └── AuditLogger.php
│   ├── utils/
│   │   ├── JWTHelper.php
│   │   ├── Response.php
│   │   └── Validator.php
│   └── uploads/                   # File uploads (receipts, agreements)
├── assets/
│   ├── css/
│   │   ├── index.css              # Design system & tokens
│   │   ├── layout.css             # Sidebar, navbar, main shell
│   │   ├── components.css         # Reusable UI components
│   │   ├── pages.css              # Page-specific styles
│   │   └── utilities.css          # Utility classes
│   ├── js/
│   │   ├── app.js                 # SPA bootstrap & router
│   │   ├── router.js              # Hash-based SPA router
│   │   ├── state.js               # Centralized state store
│   │   ├── api/
│   │   │   ├── client.js          # Fetch wrapper (replaces Axios)
│   │   │   ├── auth.js
│   │   │   ├── products.js
│   │   │   ├── sales.js
│   │   │   ├── suppliers.js
│   │   │   ├── purchaseOrders.js
│   │   │   ├── deliveries.js
│   │   │   ├── customers.js
│   │   │   ├── motorcycles.js
│   │   │   ├── returns.js
│   │   │   ├── supplierReturns.js
│   │   │   ├── consignment.js
│   │   │   ├── movements.js
│   │   │   ├── reports.js
│   │   │   ├── notifications.js
│   │   │   ├── settings.js
│   │   │   ├── permissions.js
│   │   │   └── services.js
│   │   ├── pages/                 # Page rendering modules
│   │   │   ├── login.js
│   │   │   ├── dashboard.js
│   │   │   ├── inventory.js
│   │   │   ├── sales.js
│   │   │   ├── suppliers.js
│   │   │   ├── purchaseOrders.js
│   │   │   ├── purchaseOrderCreate.js
│   │   │   ├── purchaseOrderDetail.js
│   │   │   ├── deliveries.js
│   │   │   ├── customers.js
│   │   │   ├── returns.js
│   │   │   ├── supplierReturns.js
│   │   │   ├── consignmentPayouts.js
│   │   │   ├── transactions.js
│   │   │   ├── reports.js
│   │   │   ├── alerts.js
│   │   │   ├── settings.js
│   │   │   ├── userManagement.js
│   │   │   ├── dataManagement.js
│   │   │   ├── auditLog.js
│   │   │   ├── permissionManagement.js
│   │   │   └── supplierPOReview.js
│   │   ├── components/            # Reusable UI components
│   │   │   ├── navbar.js
│   │   │   ├── sidebar.js
│   │   │   ├── modal.js
│   │   │   ├── dataTable.js
│   │   │   ├── toast.js
│   │   │   ├── forms.js
│   │   │   ├── charts.js
│   │   │   ├── stockGauge.js
│   │   │   ├── spinner.js
│   │   │   └── receipt.js
│   │   └── utils/
│   │       ├── helpers.js
│   │       ├── validators.js
│   │       └── formatters.js
│   ├── fonts/
│   └── img/
│       └── vinjack_logo.png
└── database/
    ├── vinjack_schema.sql         # DDL: Full relational schema
    ├── vinjack_seed.sql           # Seed data (roles, permissions, demo user)
    └── vinjack_views.sql          # Reporting views
```

---

## 3. Relational Schema Blueprint (MySQL DDL)

> All tables use InnoDB engine with `utf8mb4_unicode_ci` collation. All primary keys are `INT UNSIGNED AUTO_INCREMENT`. All timestamps use `DATETIME DEFAULT CURRENT_TIMESTAMP`.

### 3.1 Core Lookup / Reference Tables

#### `categories`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `name` | VARCHAR(100) | UNIQUE NOT NULL |
| `description` | TEXT | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `brands`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `name` | VARCHAR(100) | UNIQUE NOT NULL |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `services`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `name` | VARCHAR(150) | UNIQUE NOT NULL |
| `description` | TEXT | NULLABLE |
| `charge` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 |
| `status` | ENUM('active','inactive') | DEFAULT 'active' |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

---

### 3.2 User & Authentication Tables

#### `users`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `full_name` | VARCHAR(150) | NOT NULL |
| `username` | VARCHAR(100) | UNIQUE NOT NULL |
| `email` | VARCHAR(255) | UNIQUE NOT NULL |
| `password` | VARCHAR(255) | NOT NULL (bcrypt) |
| `role` | ENUM('Super Admin','Admin','Salesperson') | NOT NULL, DEFAULT 'Salesperson' |
| `status` | VARCHAR(20) | DEFAULT 'pending' |
| `must_change_password` | TINYINT(1) | DEFAULT 0 |
| `failed_attempts` | INT UNSIGNED | DEFAULT 0 |
| `last_failed_attempt` | DATETIME | NULLABLE |
| `notifications_enabled` | TINYINT(1) | DEFAULT 1 |
| `notification_time` | VARCHAR(5) | DEFAULT '08:00' |
| `daily_report_enabled` | TINYINT(1) | DEFAULT 0 |
| `daily_report_time` | VARCHAR(5) | DEFAULT '08:30' |
| `dashboard_time_range` | VARCHAR(20) | DEFAULT 'all' |
| `dashboard_category_id` | INT UNSIGNED | NULLABLE, FK → categories |
| `dashboard_supplier_id` | INT UNSIGNED | NULLABLE, FK → suppliers |
| `has_pending_changes` | TINYINT(1) | DEFAULT 0 |
| `pending_full_name` | VARCHAR(150) | NULLABLE |
| `pending_username` | VARCHAR(100) | NULLABLE |
| `pending_email` | VARCHAR(255) | NULLABLE |
| `pending_password` | VARCHAR(255) | NULLABLE |
| `verification_code` | VARCHAR(10) | NULLABLE |
| `verification_code_expires` | DATETIME | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `all_permissions`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `key` | VARCHAR(50) | UNIQUE NOT NULL |
| `description` | VARCHAR(255) | NOT NULL |
| `category` | ENUM('General','Inventory','Sales','Suppliers','Admin') | DEFAULT 'General' |
| `created_at` | DATETIME | DEFAULT NOW |

#### `permission_default_roles`
| Column | Type | Constraints |
|---|---|---|
| `permission_id` | INT UNSIGNED | PK (composite), FK → all_permissions |
| `role` | ENUM('Admin','Salesperson') | PK (composite) |

#### `role_permissions`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `role` | ENUM('Admin','Salesperson') | UNIQUE NOT NULL |

#### `role_permission_entries`
| Column | Type | Constraints |
|---|---|---|
| `role_permission_id` | INT UNSIGNED | PK (composite), FK → role_permissions |
| `permission_key` | VARCHAR(50) | PK (composite) |

---

### 3.3 Inventory / Product Tables

#### `products`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `item_code` | VARCHAR(50) | UNIQUE NOT NULL |
| `name` | VARCHAR(200) | NOT NULL |
| `category_id` | INT UNSIGNED | NOT NULL, FK → categories |
| `brand_id` | INT UNSIGNED | NOT NULL, FK → brands |
| `price` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 |
| `quantity` | INT | NOT NULL, DEFAULT 0 |
| `consigned_stock` | INT | DEFAULT 0 |
| `max_stock` | INT UNSIGNED | NOT NULL, DEFAULT 1, MIN 1 |
| `stock_status` | ENUM('Healthy','Low','Critical','Out of Stock') | DEFAULT 'Out of Stock' |
| `status` | ENUM('active','inactive') | DEFAULT 'active' |
| `unit` | VARCHAR(20) | DEFAULT 'pc' |
| `image` | VARCHAR(500) | NULLABLE |
| `default_cost` | DECIMAL(12,2) | DEFAULT 0 |
| `is_serialized` | TINYINT(1) | DEFAULT 0 |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

**Indexes:** `idx_products_category (category_id)`, `idx_products_brand (brand_id)`, `idx_products_status (stock_status)`

#### `product_supplier_costs` *(replaces `Product.supplierCosts` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products ON DELETE CASCADE |
| `supplier_id` | INT UNSIGNED | NOT NULL, FK → suppliers ON DELETE CASCADE |
| `cost` | DECIMAL(12,2) | NOT NULL |
| `note` | TEXT | NULLABLE |

**Unique:** `UNIQUE(product_id, supplier_id)`

#### `serialized_items` *(replaces `Product.serializedItems` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products ON DELETE CASCADE |
| `serial_number` | VARCHAR(100) | NOT NULL |
| `status` | ENUM('Available','Sold','Returned','Defective','Lost') | DEFAULT 'Available' |
| `purchase_order_id` | INT UNSIGNED | NULLABLE, FK → purchase_orders |
| `date_received` | DATETIME | DEFAULT NOW |

**Unique:** `UNIQUE(product_id, serial_number)`

---

### 3.4 Customer / Motorcycle Tables

#### `customers`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `name` | VARCHAR(150) | NOT NULL |
| `email` | VARCHAR(255) | NULLABLE, UNIQUE (sparse) |
| `phone` | VARCHAR(30) | NULLABLE |
| `address` | TEXT | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `motorcycles`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `owner_id` | INT UNSIGNED | NOT NULL, FK → customers ON DELETE CASCADE |
| `make` | VARCHAR(100) | NOT NULL |
| `model` | VARCHAR(100) | NOT NULL |
| `year` | SMALLINT UNSIGNED | NULLABLE |
| `color` | VARCHAR(50) | NULLABLE |
| `plate_number` | VARCHAR(20) | NULLABLE, UNIQUE |
| `vin` | VARCHAR(50) | NULLABLE, UNIQUE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

---

### 3.5 Supplier & Purchasing Tables

#### `suppliers`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `name` | VARCHAR(200) | UNIQUE NOT NULL |
| `email` | VARCHAR(255) | NULLABLE |
| `contact_person` | VARCHAR(150) | NULLABLE |
| `contact_number` | VARCHAR(30) | NULLABLE |
| `address` | TEXT | NULLABLE |
| `status` | ENUM('Pending','Approved','Rejected') | DEFAULT 'Pending' |
| `default_payment_terms` | ENUM('Cash','Consignment','Terms') | DEFAULT 'Cash' |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `counters` *(auto-increment sequences)*
| Column | Type | Constraints |
|---|---|---|
| `id` | VARCHAR(50) | PK |
| `seq` | INT UNSIGNED | DEFAULT 0 |

#### `purchase_orders`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `po_number` | VARCHAR(30) | UNIQUE NOT NULL |
| `supplier_id` | INT UNSIGNED | NOT NULL, FK → suppliers |
| `total_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 |
| `po_type` | ENUM('Purchase','Consignment') | DEFAULT 'Purchase' |
| `consignment_method` | ENUM('System','Manual') | DEFAULT 'System' |
| `terms_and_conditions` | TEXT | NULLABLE |
| `status` | ENUM('Pending','Awaiting Approval','Approved','Partially Received','Completed','Cancelled','Agreement Uploaded - Awaiting Delivery') | DEFAULT 'Pending' |
| `order_date` | DATETIME | DEFAULT NOW |
| `notes` | TEXT | NULLABLE |
| `supplier_notes` | TEXT | NULLABLE |
| `supplier_response_token` | VARCHAR(255) | NULLABLE, UNIQUE |
| `delivery_receipt_url` | VARCHAR(500) | NULLABLE |
| `signed_agreement_url` | VARCHAR(500) | NULLABLE |
| `countersigned_agreement_url` | VARCHAR(500) | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `purchase_order_items` *(replaces `PO.items` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `purchase_order_id` | INT UNSIGNED | NOT NULL, FK → purchase_orders ON DELETE CASCADE |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `quantity` | INT UNSIGNED | NOT NULL |
| `cost` | DECIMAL(12,2) | NOT NULL |
| `total` | DECIMAL(12,2) | NOT NULL |
| `supplier_updated_cost` | DECIMAL(12,2) | NULLABLE |
| `is_available` | TINYINT(1) | DEFAULT 1 |
| `quantity_received` | INT UNSIGNED | DEFAULT 0 |

#### `purchase_order_item_serials`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `po_item_id` | INT UNSIGNED | FK → purchase_order_items ON DELETE CASCADE |
| `serial_number` | VARCHAR(100) | NOT NULL |

#### `purchase_order_history` *(replaces `PO.history` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `purchase_order_id` | INT UNSIGNED | NOT NULL, FK → purchase_orders ON DELETE CASCADE |
| `status` | VARCHAR(100) | NOT NULL |
| `notes` | TEXT | NULLABLE |
| `updated_by` | VARCHAR(100) | DEFAULT 'System' |
| `timestamp` | DATETIME | DEFAULT NOW |

---

### 3.6 Sales & POS Tables

#### `sales`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `total_amount` | DECIMAL(12,2) | NOT NULL |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `customer_id` | INT UNSIGNED | NULLABLE, FK → customers |
| `motorcycle_id` | INT UNSIGNED | NULLABLE, FK → motorcycles |
| `customer_receipt_image` | TEXT | NULLABLE |
| `is_manual_entry` | TINYINT(1) | DEFAULT 0 |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `sale_items` *(replaces `Sale.items` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `sale_id` | INT UNSIGNED | NOT NULL, FK → sales ON DELETE CASCADE |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `quantity` | INT UNSIGNED | NOT NULL |
| `price_at_time` | DECIMAL(12,2) | NOT NULL |
| `cost_of_goods_sold` | DECIMAL(12,2) | NOT NULL, DEFAULT 0 |

#### `sale_services` *(replaces `Sale.services` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `sale_id` | INT UNSIGNED | NOT NULL, FK → sales ON DELETE CASCADE |
| `service_id` | INT UNSIGNED | NOT NULL, FK → services |
| `price_at_time` | DECIMAL(12,2) | NOT NULL |

---

### 3.7 Delivery Tables

#### `deliveries`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `supplier_id` | INT UNSIGNED | NULLABLE, FK → suppliers |
| `purchase_order_id` | INT UNSIGNED | NULLABLE, FK → purchase_orders |
| `delivery_date` | DATETIME | NOT NULL, DEFAULT NOW |
| `delivery_type` | ENUM('Purchase','Consignment') | DEFAULT 'Purchase' |
| `total_cost` | DECIMAL(12,2) | NULLABLE |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `delivery_items` *(replaces `Delivery.productsReceived` embedded array)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `delivery_id` | INT UNSIGNED | NOT NULL, FK → deliveries ON DELETE CASCADE |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `quantity` | INT UNSIGNED | NOT NULL |
| `cost_at_time` | DECIMAL(12,2) | NOT NULL |

---

### 3.8 Returns Tables

#### `returns` *(customer returns)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `original_sale_id` | INT UNSIGNED | NOT NULL, FK → sales |
| `reason` | TEXT | NOT NULL |
| `outcome` | ENUM('Restocked','Refunded','Replaced','Discarded') | DEFAULT 'Restocked' |
| `total_refund_amount` | DECIMAL(12,2) | NOT NULL |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `return_items` *(replaces `Return.itemsReturned`)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `return_id` | INT UNSIGNED | NOT NULL, FK → returns ON DELETE CASCADE |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `quantity` | INT UNSIGNED | NOT NULL |
| `price_at_time` | DECIMAL(12,2) | NOT NULL |

#### `return_services` *(replaces `Return.servicesReturned`)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `return_id` | INT UNSIGNED | NOT NULL, FK → returns ON DELETE CASCADE |
| `service_id` | INT UNSIGNED | NOT NULL, FK → services |
| `price_at_time` | DECIMAL(12,2) | NOT NULL |

#### `supplier_returns`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `supplier_id` | INT UNSIGNED | NOT NULL, FK → suppliers |
| `return_date` | DATETIME | NOT NULL |
| `notes` | TEXT | NULLABLE |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `original_purchase_id` | INT UNSIGNED | NULLABLE |
| `original_purchase_type` | ENUM('PurchaseOrder','Delivery') | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

#### `supplier_return_items` *(replaces `SupplierReturn.productsReturned`)*
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `supplier_return_id` | INT UNSIGNED | NOT NULL, FK → supplier_returns ON DELETE CASCADE |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `quantity` | INT UNSIGNED | NOT NULL |
| `reason` | ENUM('Defective','Wrong Item','Overstock','Other') | NOT NULL |
| `was_consigned` | TINYINT(1) | DEFAULT 0 |

---

### 3.9 Financial Tracking

#### `consignment_payables`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `sale_id` | INT UNSIGNED | NOT NULL, FK → sales |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products |
| `supplier_id` | INT UNSIGNED | NOT NULL, FK → suppliers |
| `quantity_sold` | INT UNSIGNED | NOT NULL |
| `cost_at_time_of_sale` | DECIMAL(12,2) | NOT NULL |
| `amount_owed` | DECIMAL(12,2) | NOT NULL |
| `status` | ENUM('Owed','Paid') | DEFAULT 'Owed' |
| `paid_date` | DATETIME | NULLABLE |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `created_at` | DATETIME | DEFAULT NOW |
| `updated_at` | DATETIME | ON UPDATE NOW |

---

### 3.10 Stock Ledger & System Tables

#### `movements`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `product_id` | INT UNSIGNED | NOT NULL, FK → products, INDEX |
| `type` | ENUM('SALE','DELIVERY','ADJUSTMENT','RETURN','DELIVERY (PO)') | NOT NULL |
| `quantity_change` | INT | NOT NULL (signed ±) |
| `stock_before` | INT | NOT NULL |
| `stock_after` | INT | NOT NULL |
| `reference_id` | VARCHAR(50) | NULLABLE |
| `notes` | TEXT | NULLABLE |
| `recorded_by` | INT UNSIGNED | NOT NULL, FK → users |
| `created_at` | DATETIME | DEFAULT NOW |

#### `audit_logs`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `user_id` | INT UNSIGNED | NOT NULL, FK → users |
| `action` | VARCHAR(50) | NOT NULL |
| `details` | TEXT | NOT NULL |
| `entity_type` | VARCHAR(50) | NULLABLE |
| `entity_id` | INT UNSIGNED | NULLABLE |
| `created_at` | DATETIME | DEFAULT NOW |

**Index:** `idx_audit_created (created_at DESC)`

#### `notifications`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `user_id` | INT UNSIGNED | NOT NULL, FK → users, INDEX |
| `message` | TEXT | NOT NULL |
| `type` | ENUM('LOW_STOCK','CRITICAL_STOCK','OUT_OF_STOCK','USER_ACTION','REQUEST_STATUS') | NOT NULL |
| `link` | VARCHAR(255) | NULLABLE |
| `image` | VARCHAR(500) | NULLABLE |
| `is_read` | TINYINT(1) | DEFAULT 0, INDEX |
| `created_at` | DATETIME | DEFAULT NOW |

#### `settings`
| Column | Type | Constraints |
|---|---|---|
| `id` | INT UNSIGNED AI | PK |
| `key` | VARCHAR(100) | UNIQUE NOT NULL |
| `value` | TEXT | NOT NULL |

---

### Table Count Summary: **27 tables** (normalized from 20 MongoDB collections)

---

## 4. Endpoint Matrix & API Contract

### Authentication & Users
| Method | Endpoint | Description | Auth | Permission |
|---|---|---|---|---|
| `POST` | `/api/users/login` | Login, issue JWT | ❌ | — |
| `POST` | `/api/users/demo-login` | Demo access | ❌ | — |
| `GET` | `/api/users/me` | Current user profile | ✅ | — |
| `POST` | `/api/users/logout` | Logout + audit | ✅ | — |
| `PUT` | `/api/users/force-change-password` | Forced password change | ✅ | — |
| `GET` | `/api/users` | List all users | ✅ | Super Admin |
| `POST` | `/api/users` | Create user | ✅ | Super Admin |
| `PUT` | `/api/users/:id` | Update user | ✅ | Super Admin |
| `DELETE` | `/api/users/:id` | Delete user | ✅ | Super Admin |

### Products & Inventory
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET` | `/api/products` | ✅ | canViewInventory |
| `GET` | `/api/products/:id` | ✅ | canViewInventory |
| `POST` | `/api/products` | ✅ | canManageInventory |
| `PUT` | `/api/products/:id` | ✅ | canManageInventory |
| `DELETE` | `/api/products/:id` | ✅ | canManageInventory |
| `GET` | `/api/products/low-stock` | ✅ | canViewInventory |
| `GET/POST` | `/api/categories` | ✅ | canManageInventory |
| `PUT/DELETE` | `/api/categories/:id` | ✅ | canManageInventory |
| `GET/POST` | `/api/brands` | ✅ | canManageInventory |
| `PUT/DELETE` | `/api/brands/:id` | ✅ | canManageInventory |

### Sales / POS
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `POST` | `/api/sales` | ✅ | canManageSales |
| `GET` | `/api/sales` | ✅ | canManageSales |
| `GET` | `/api/sales/:id` | ✅ | canManageSales |
| `GET` | `/api/sales/search` | ✅ | canManageSales |
| `PUT` | `/api/sales/:id/receipt` | ✅ | canManageSales |

### Services
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/services` | ✅ | canManageSales |
| `PUT/DELETE` | `/api/services/:id` | ✅ | canManageSales |

### Customers & Motorcycles
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/customers` | ✅ | canManageCustomers |
| `PUT/DELETE` | `/api/customers/:id` | ✅ | canManageCustomers |
| `GET/POST` | `/api/motorcycles` | ✅ | canManageCustomers |
| `PUT/DELETE` | `/api/motorcycles/:id` | ✅ | canManageCustomers |

### Suppliers & Purchase Orders
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/suppliers` | ✅ | canViewSuppliers / canManageSuppliers |
| `PUT/DELETE` | `/api/suppliers/:id` | ✅ | canManageSuppliers |
| `GET/POST` | `/api/purchase-orders` | ✅ | canManagePurchaseOrders |
| `GET` | `/api/purchase-orders/:id` | ✅ | canManagePurchaseOrders |
| `PUT` | `/api/purchase-orders/:id` | ✅ | canManagePurchaseOrders |
| `POST` | `/api/purchase-orders/:id/send` | ✅ | canManagePurchaseOrders |
| `POST` | `/api/purchase-orders/:id/receive` | ✅ | canManagePurchaseOrders |
| `GET` | `/supplier/po/:token` | ❌ | — (public supplier review) |

### Deliveries
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/deliveries` | ✅ | canManageDeliveries |

### Returns
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/returns` | ✅ | canManageReturns |
| `GET/POST` | `/api/supplier-returns` | ✅ | canManageReturns |

### Consignment
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET` | `/api/consignment/payables` | ✅ | canManagePurchaseOrders |
| `PUT` | `/api/consignment/payables/:id/pay` | ✅ | canManagePurchaseOrders |

### Stock Management
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET` | `/api/movements` | ✅ | canViewInventory |
| `POST` | `/api/adjustments` | ✅ | canManageInventory |

### Reports & Dashboard
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET` | `/api/reports/summary` | ✅ | canViewDashboard |
| `GET` | `/api/reports/sales` | ✅ | canViewReports |
| `GET` | `/api/reports/returns` | ✅ | canViewReports |
| `GET` | `/api/reports/low-stock` | ✅ | canViewReports |
| `GET` | `/api/reports/sales-trend` | ✅ | canViewDashboard |
| `GET` | `/api/reports/recent-activities` | ✅ | canViewDashboard |
| `GET` | `/api/reports/pending-pos` | ✅ | canViewDashboard |

### System Administration
| Method | Endpoint | Auth | Permission |
|---|---|---|---|
| `GET/POST` | `/api/notifications` | ✅ | — |
| `POST` | `/api/notifications/read` | ✅ | — |
| `GET` | `/api/audit-logs` | ✅ | Super Admin |
| `GET/PUT` | `/api/settings` | ✅ | Super Admin |
| `GET/PUT` | `/api/app-settings` | ✅ | Super Admin |
| `GET/PUT` | `/api/permissions` | ✅ | Super Admin |

**Total: ~85 endpoints** across 22 resource groups.

---

## 5. Frontend SPA Architecture

### Router Design (Hash-based)
```
#/login              → LoginPage (public)
#/supplier/po/:token → SupplierPOReviewPage (public)
#/dashboard          → DashboardPage
#/inventory          → InventoryPage
#/sales              → SalesPage (POS)
#/suppliers          → SuppliersPage
#/purchase-orders    → PurchaseOrdersPage
#/purchase-orders/new    → CreatePurchaseOrderPage
#/purchase-orders/:id    → PurchaseOrderDetailPage
#/deliveries         → DeliveriesPage
#/customers          → CustomersPage
#/returns            → ReturnsPage
#/supplier-returns   → SupplierReturnsPage
#/consignment-payouts → ConsignmentPayoutsPage
#/transactions       → TransactionsPage
#/reports            → ReportsPage
#/alerts             → AlertsPage
#/settings           → SettingsPage
#/user-management    → UserManagementPage
#/data-management    → DataManagementPage
#/audit-log          → AuditLogPage
#/permission-management → PermissionManagementPage
```

### State Management (Vanilla ES6 Store)
- Centralized `state.js` module using the Pub/Sub observer pattern
- Slices: `auth`, `notifications`, `lowStockItems`, `permissions`
- `subscribe(slice, callback)` for reactive component updates
- Token stored in `localStorage`, attached to all API calls via `fetch` wrapper

### Component Architecture
- **Layout Shell**: Navbar + Sidebar + Content area (rendered once, content swapped by router)
- **Data Table**: Custom sortable/filterable/paginated table component (replaces MUI DataGrid)
- **Modal System**: Generic `Modal.open(config)` API with confirm/cancel actions
- **Toast System**: Lightweight toast notification queue (replaces react-toastify)
- **Charts**: Chart.js integration (same library, direct DOM bindings)
- **Forms**: Declarative form builder with client-side validation
- **Receipt Printer**: HTML-based print layout using `window.print()` (replaces react-to-print)

---

## 6. Workflow Flowcharts

### Authentication Flow (PHP)
```
Login Request → AuthController::login()
  → Validate input
  → Query users table by username
  → Check status != 'locked'
  → Check failed_attempts < MAX
    → If exceeded: lock account, audit log LOGIN_LOCKED, return 403
  → Verify password (password_verify)
    → On fail: increment failed_attempts, audit log LOGIN_FAILED
    → On success: reset failed_attempts, generate JWT, audit log LOGIN
  → Return { token, user, permissions[], mustChangePassword }
```

### POS / Create Sale Flow (PHP)
```
POST /api/sales → SaleController::create()
  → Begin PDO Transaction
  → Validate items[] / services[] (at least one required)
  → For each item:
    → SELECT product FOR UPDATE (row-level lock)
    → Verify quantity >= requested
    → If serialized: validate serial numbers, mark as 'Sold'
    → Deduct quantity, handle consigned stock
    → Prepare COGS and consignment payable if applicable
    → Queue movement log entry
  → For each service:
    → Verify service exists and is active
  → INSERT INTO sales
  → INSERT INTO sale_items (batch)
  → INSERT INTO sale_services (batch)
  → INSERT INTO movements (batch)
  → INSERT INTO consignment_payables (if any)
  → COMMIT Transaction
  → Post-commit: checkStockLevelAndNotify() for each affected product
  → Audit log: PROCESS_SALE
  → Return populated sale with JOINed data
```

### Stock Level Monitoring
```
StockManager::checkAndNotify($product)
  → Calculate newStatus = getStockStatus(quantity, maxStock)
  → If status unchanged → return
  → UPDATE product SET stock_status = newStatus
  → If newStatus in (Low, Critical, Out of Stock):
    → INSERT INTO notifications for Super Admin + Admin users
    → (Optional: SSE push or polling endpoint)
  → Return
```

---

## 7. Phased Implementation Roadmap

### Phase 1: Foundation (Database + Core Backend)
1. Generate `vinjack_schema.sql` — complete DDL with all 27 tables
2. Generate `vinjack_seed.sql` — seed permissions, roles, demo Super Admin user
3. Create `api/config/Database.php` — PDO singleton with XML config reader
4. Create `api/config/Config.php` — XML configuration parser
5. Create `config/database.xml` + `config/app.xml`
6. Create `api/utils/Response.php`, `api/utils/JWTHelper.php`, `api/utils/Validator.php`
7. Create `api/index.php` — front controller with URL routing
8. Create `api/.htaccess` — Apache rewrite rules
9. Create `api/middleware/AuthMiddleware.php`, `RBACMiddleware.php`, `CorsMiddleware.php`

### Phase 2: Core DAOs & Controllers
10. `UserDAO.php` + `AuthController.php` + `UserController.php` — login, CRUD, password flows
11. `CategoryDAO.php` + `BrandDAO.php` + `CategoryController.php` + `BrandController.php`
12. `ProductDAO.php` + `ProductController.php` — full CRUD with supplier costs & serialized items
13. `ServiceDAO.php` + `ServiceController.php`
14. `CustomerDAO.php` + `MotorcycleDAO.php` + `CustomerController.php` + `MotorcycleController.php`
15. `SupplierDAO.php` + `SupplierController.php`

### Phase 3: Transactional Business Logic
16. `SaleDAO.php` + `SaleController.php` — POS with transaction, COGS, consignment
17. `services/StockManager.php` + `services/MovementLogger.php` + `services/AuditLogger.php`
18. `PurchaseOrderDAO.php` + `PurchaseOrderController.php` — create, send, approve, receive
19. `DeliveryDAO.php` + `DeliveryController.php`
20. `ReturnDAO.php` + `ReturnController.php` — customer returns with outcome handling
21. `SupplierReturnDAO.php` + `SupplierReturnController.php`
22. `ConsignmentPayableDAO.php` + `ConsignmentController.php`
23. `MovementDAO.php` + `MovementController.php` + `AdjustmentController.php`

### Phase 4: Reporting & System
24. `ReportController.php` + `DashboardController.php` — SQL aggregations (replaces MongoDB pipelines)
25. `NotificationDAO.php` + `NotificationController.php`
26. `AuditLogDAO.php` + `AuditLogController.php`
27. `SettingDAO.php` + `SettingsController.php`
28. `PermissionDAO.php` + `PermissionController.php`

### Phase 5: Frontend SPA Shell
29. `index.html` — SPA entry point with link references
30. CSS design system: `index.css`, `layout.css`, `components.css`, `pages.css`, `utilities.css`
31. `app.js` + `router.js` — hash-based SPA routing
32. `state.js` — centralized state store with pub/sub
33. `api/client.js` — fetch wrapper with auth interceptor
34. Core components: `navbar.js`, `sidebar.js`, `modal.js`, `toast.js`, `spinner.js`, `dataTable.js`

### Phase 6: Frontend Pages
35. `login.js` — login page with auth flow
36. `dashboard.js` — summary cards, charts, recent activity
37. `inventory.js` — product CRUD, stock gauge, movement history modal
38. `sales.js` — POS interface with item/service selection, receipt generation
39. `suppliers.js` — supplier list/CRUD
40. `purchaseOrders.js` + `purchaseOrderCreate.js` + `purchaseOrderDetail.js`
41. `deliveries.js` — delivery recording form
42. `customers.js` — customer + motorcycle management
43. `returns.js` + `supplierReturns.js`
44. `consignmentPayouts.js`
45. `transactions.js` — unified transaction log view
46. `reports.js` — sales report + returns report with chart.js
47. `alerts.js` — stock alert display
48. `settings.js` + `userManagement.js` + `auditLog.js` + `permissionManagement.js` + `dataManagement.js`
49. `supplierPOReview.js` — public supplier PO review page

### Phase 7: Integration & Polish
50. End-to-end testing of all CRUD flows
51. Chart.js integration for dashboard and reports
52. PDF receipt/report generation (jsPDF)
53. File upload handling (receipts, agreements)
54. Notification polling system
55. Responsive design pass
56. Cross-browser testing

---

## User Review Required

> [!IMPORTANT]
> **Socket.IO Replacement**: The reference uses Socket.IO for real-time stock alerts. In the PHP target, I plan to use **polling** (periodic `GET /api/notifications` calls every 30s) since PHP doesn't natively support persistent WebSocket connections. An alternative is **Server-Sent Events (SSE)** for one-way push. Which approach do you prefer?

> [!IMPORTANT]
> **Email Service**: The reference uses Nodemailer for low-stock email alerts and daily sales reports via cron. The PHP equivalent would use **PHPMailer** with a XAMPP-based cron/scheduled task. Should I include email functionality in the target system, or defer it for post-launch?

> [!IMPORTANT]  
> **Backup/Restore**: The reference uses MongoDB dump + Google Cloud Storage. For the MySQL target, this would be `mysqldump` + local file storage. Should this be included in the initial build?

> [!IMPORTANT]
> **Demo Login**: The reference has a `POST /api/users/demo-login` endpoint that bypasses authentication for testing. Should this be preserved in the target system?

## Open Questions

> [!WARNING]
> **Deployment Target**: Is this intended to run exclusively on **XAMPP (localhost)** for local use, or will it eventually be deployed to a web host? This affects decisions around `.htaccess` vs nginx config, and whether to include SSL/HTTPS configuration.

> [!WARNING]
> **Chart Library**: The reference uses `Chart.js` via React wrappers. For the vanilla SPA, I'll integrate Chart.js directly. Do you want to use the same library, or prefer a lighter alternative?
