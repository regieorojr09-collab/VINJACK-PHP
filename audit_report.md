# Comprehensive Codebase Audit & Architectural Overview
## Project: VINJACK-PHP — Sales & Inventory Management System with XML Data Interchange
**Client:** VinJack Motorworks (Marikina City, Philippines)  
**Stack:** PHP 8.2+ (Pure OOP, PDO), Vanilla JavaScript (ES6+), XML (DOMDocument / DOMParser), MySQL 8.0+ / MariaDB 10.4+, Vanilla CSS  
**Date of Audit:** October 2026  

---

## 1. Full Directory & File Inventory

The repository is structured into clear functional domains across root, backend API, database scripts, active frontend (`public/`), documentation, and legacy prototype assets (`assets/`). Below is the complete inventory of all files and their specific purpose.

### Root Directory
* [`.dockerignore`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.dockerignore) — Specifies exclusion patterns (`.git`, `*.zip`, `*.log`, `Dockerfile`, `.vscode`, etc.) for container image builds.
* [`.gitignore`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.gitignore) — Specifies untracked files for Git (logs, temporary files, OS cache). *(Note: `*.zip` and `upload.zip.zip` are currently omitted here).*
* [`.htaccess`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.htaccess) — Root Apache configuration: disables directory browsing (`Options -Indexes`), denies access to internal directories (`config`, `database`, `docs`) and sensitive extensions (`.xml`, `.sql`, `.env`), configures security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`), and rewrites `/api/*` endpoints to [`api/index.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/index.php).
* [`Dockerfile`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/Dockerfile) — Containerization definition using official `php:8.2-apache`, installing `pdo`, `pdo_mysql`, `mysqli`, and `libxml2-dev`, enabling `mod_rewrite` and `mod_headers`, and allowing `.htaccess` overrides.
* [`README.md`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/README.md) — Comprehensive technical overview documenting client background, production and local URLs, seeded credentials, architectural patterns, XML interchange flows, and installation instructions.
* [`implementation_plan.md`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/implementation_plan.md) — Architectural blueprint detailing the migration strategy from legacy MERN to relational PHP/MySQL with 3NF normalization, DAO patterns, and XML interchange specifications.
* [`index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/index.html) — Main application Single Page Application (SPA) shell containing sidebar navigation, royal blue header bar, dynamic flash alert container, main view outlet (`#app-root`), and ES6 module loader targeting [`public/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/router.js) and [`public/css/styles.css`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/css/styles.css).
* [`upload.zip.zip`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/upload.zip.zip) — 311.8 KB leftover archive committed directly into the Git repository root; recommended for cleanup.

### Developer Tooling & Automation (`.vscode/` & `scripts/`)
* [`.vscode/settings.json`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.vscode/settings.json) — Editor settings configuring tab width (4 spaces), newline termination, and trim trailing whitespace.
* [`.vscode/tasks.json`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.vscode/tasks.json) — Automated tasks configuration defining the "Start Git Auto-Sync" task.
* [`scripts/auto-sync.ps1`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/scripts/auto-sync.ps1) — PowerShell background watcher daemon utilizing `System.IO.FileSystemWatcher` with a 10-second debounce window to automatically commit and push file modifications to GitHub `origin/main`.

### Root Configuration (`config/`)
* [`config/app.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/app.xml) — XML application configuration declaring shop metadata, default currency (`PHP`), tax rates (`0.00`), and notification flags.
* [`config/database.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/database.xml) — XML database configuration specifying environment, connection parameters (`localhost:3306`, `vinjack_db`, `root`), and PDO driver attributes.

### Database Layer (`database/`)
* [`database/vinjack_schema.sql`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/database/vinjack_schema.sql) — Complete 615-line MySQL 8.0+/MariaDB DDL defining 31 relational tables (InnoDB, 3NF, foreign keys with restrict/cascade constraints) along with initial seed data.
* [`database/vinjack_seed.sql`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/database/vinjack_seed.sql) — Standalone database seeding script providing default permissions, role mappings, demo Super Admin account (`admin` / `password123`), and core system settings.

### Academic & Project Documentation (`docs/`)
* [`docs/SME_Client_Based_Online_Web_Application_Proposal_Template.docx.pdf`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/docs/SME_Client_Based_Online_Web_Application_Proposal_Template.docx.pdf) — Academic capstone proposal specification template from STI College defining requirements for PHP, JavaScript, XML, and client-based deployment.
* [`docs/codebase_analysis.md`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/docs/codebase_analysis.md) — Comprehensive technical breakdown of the legacy reference MERN prototype ("VINJACK OLD REF").
* [`docs/implementation_plan.md`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/docs/implementation_plan.md) — 959-line detailed migration blueprint mapping NoSQL collections to normalized relational tables, DAOs, controllers, and XML interfaces.
* [`docs/task.md`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/docs/task.md) — Migration task tracker outlining Phase 1 through Phase 7 tasks and completion checkboxes.

---

### Backend API Architecture (`api/`)

#### Front Controller, Core Engine & Utilities
* [`api/.htaccess`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/.htaccess) — Passes `Authorization` header to PHP, blocks direct access to internal subfolders (`config`, `core`, `middleware`, `models`, `utils`), sets CORS headers, and routes requests to `index.php`.
* [`api/index.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/index.php) — Front Controller and API gateway; manages global exception handling, sets JSON/XML response headers, binds CORS, maps `/api/health` and `/api/auth/*`, and dynamically registers resource controllers into [`Core\Router`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Router.php).
* [`api/core/Router.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Router.php) — Custom lightweight OOP router handling REST verbs (`GET`, `POST`, `PUT`, `DELETE`), dynamic URI parameter extraction (`{id}`, `{subresource}`), and middleware callback queues.
* [`api/core/Database.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Database.php) — Database Singleton with lazy connection initialization, fallback XML loader ([`config/database.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/database.xml)), 12-factor cloud environment variable overrides (`DATABASE_URL`, `MYSQLHOST`, `DB_HOST`), and cloud SSL support.
* [`api/core/JWT.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/JWT.php) — Pure PHP HS256 JSON Web Token generator and validator with XML secret fallback ([`config/app.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/app.xml)), base64url encoding, and token expiration enforcement.
* [`api/utils/Response.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/utils/Response.php) — Standardized response emitter providing `Response::json()` and `Response::xml()`.
* [`api/utils/Validator.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/utils/Validator.php) — Utility for mandatory input key validation and string sanitization (`htmlspecialchars`).
* [`api/utils/JWTHelper.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/utils/JWTHelper.php) — Thin compatibility wrapper around [`Core\JWT`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/JWT.php).

#### Configuration & Security Middleware
* [`api/config/Config.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/config/Config.php) — Configuration singleton parsing `database.xml` and `app.xml` via `simplexml_load_file()`.
* [`api/config/Database.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/config/Database.php) — Namespace alias pointing to [`Core\Database`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Database.php).
* [`api/config/app.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/config/app.xml) — Local duplicate of application configuration.
* [`api/config/database.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/config/database.xml) — Local duplicate of database configuration with cloud environment documentation.
* [`api/middleware/AuthMiddleware.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/middleware/AuthMiddleware.php) — Validates incoming JWT Bearer tokens from `Authorization` header and attaches decoded claims.
* [`api/middleware/CorsMiddleware.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/middleware/CorsMiddleware.php) — Injects CORS headers and handles preflight `OPTIONS` requests.
* [`api/middleware/RBACMiddleware.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/middleware/RBACMiddleware.php) — Enforces permission checks against token claims; automatically bypasses checks for `Super Admin`.

#### Data Access Objects (DAOs — `api/models/`)
* [`api/models/UserDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/UserDAO.php) — User lookups by username/id, failed login throttling, account lockouts, and role permission resolution.
* [`api/models/ProductDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/ProductDAO.php) — Product catalog CRUD, multi-criteria filtering, stock status calculation (`Healthy`, `Low`, `Critical`, `Out of Stock`), and supplier-cost pivot sync.
* [`api/models/SaleDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/SaleDAO.php) — ACID-compliant POS checkout transaction (`PDO::beginTransaction`), pessimistic row locking (`FOR UPDATE`), historical COGS capture, consignment payable generation, immutable movement logging, and native DOMDocument XML receipt generation.
* [`api/models/PurchaseOrderDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/PurchaseOrderDAO.php) — Multi-item PO creation, status history trail, stock receiving engine (with consignment stock tracking), and native DOMDocument B2B XML export.
* [`api/models/ConsignmentDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/ConsignmentDAO.php) — Consignment payables ledger, vendor liability metrics, and single/batch settlement payouts.
* [`api/models/MovementDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/MovementDAO.php) — Immutable stock movement ledger queries and physical count adjustment engine.
* [`api/models/ReturnDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/ReturnDAO.php) — Customer return workflows (restock/refund/replace with inventory adjustment and ledger logging) and supplier RMA claims.
* [`api/models/ReportDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/ReportDAO.php) — Executive dashboard KPIs, daily sales profitability with COGS and refunds, inventory asset valuation (cost basis vs. retail, owned vs. consigned), and top 5 bestsellers.
* [`api/models/SupplierDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/SupplierDAO.php) — Supplier directory CRUD and approval status workflow (`Approved`, `Pending`, `Rejected`).
* [`api/models/CustomerDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/CustomerDAO.php) — Customer directory CRUD, motorcycle association, and purchase history.
* [`api/models/MotorcycleDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/MotorcycleDAO.php) — Customer vehicle registry CRUD (`plate_number`, `make`, `model`, `vin`).
* [`api/models/CategoryDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/CategoryDAO.php) — Product category CRUD with item count aggregations.
* [`api/models/BrandDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/BrandDAO.php) — Product brand CRUD with item count aggregations.

#### Controllers (`api/controllers/`)
* [`api/controllers/AuthController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/AuthController.php) — Handles `POST /auth/login`, `POST /auth/demo-login`, and `GET /auth/me`.
* [`api/controllers/UserController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/UserController.php) — Basic controller for `GET /users` (Super Admin user list), `GET /users/me`, and `POST /users/logout`.
* [`api/controllers/ProductController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/ProductController.php) — Product catalog CRUD and inventory query endpoints.
* [`api/controllers/SaleController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/SaleController.php) — POS sales checkout (`POST /sales`) and XML digital receipt generation (`GET /sales/{id}/receipt`).
* [`api/controllers/PurchaseOrderController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/PurchaseOrderController.php) — Procurement lifecycle management and XML export (`GET /purchase-orders/{id}/export-xml`).
* [`api/controllers/ConsignmentController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/ConsignmentController.php) — Consignment payables retrieval, summaries, and vendor settlements.
* [`api/controllers/MovementController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/MovementController.php) — Stock movement ledger queries and physical count adjustments (`POST /movements`).
* [`api/controllers/ReturnController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/ReturnController.php) — Customer returns and supplier RMA dispatches.
* [`api/controllers/ReportController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/ReportController.php) — Exposes `/reports/dashboard`, `/reports/top-selling`, `/reports/valuation`, and `/reports/sales`.
* [`api/controllers/SupplierController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/SupplierController.php) — Supplier directory CRUD and approval toggling.
* [`api/controllers/CustomerController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/CustomerController.php) — Customer profiles and motorcycle associations.
* [`api/controllers/MotorcycleController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/MotorcycleController.php) — Customer vehicle registry CRUD.
* [`api/controllers/CategoryController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/CategoryController.php) — Category CRUD.
* [`api/controllers/BrandController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/BrandController.php) — Brand CRUD.

---

### Active Frontend Layer (`public/`)
* [`public/index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/index.html) — Alternative direct web root entry point mirroring root `index.html`.
* [`public/css/styles.css`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/css/styles.css) — Hand-crafted 1030-line CSS design system: light enterprise automotive theme (`#1976d2` royal blue), metric cards, responsive tables, badge pills, modal windows, thermal receipt print styles, and A4 report print styles.
* [`public/js/client.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/client.js) — Universal `fetch` wrapper auto-injecting JWT headers, managing 401/403 intercepts, and auto-detecting XML content-types (`application/xml`, `text/xml`) vs JSON.
* [`public/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/router.js) — Client-side hash router managing 18 routes, navigation indicators, page title updates, and authentication guards.
* [`public/js/state.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/state.js) — Centralized reactive store (Pub/Sub pattern) persisting `token` and `user` in `localStorage`, managing flash alerts, and evaluating permissions (`hasPermission`).
* [`public/js/views/login.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/login.js) — Login interface with quick demo-login buttons for `Super Admin`, `Admin`, and `Salesperson`.
* [`public/js/views/dashboard.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/dashboard.js) — Executive dashboard: 6 colored metric cards, pure SVG revenue trend chart, Top 5 selling products and labor services, and date filters (`Today`, `Week`, `Month`, `All Time`).
* [`public/js/views/pos.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/pos.js) — Full POS terminal: catalog search, category filtering, cart management, customer linking, tender calculation, ACID checkout submission, and browser native `DOMParser()` XML receipt rendering.
* [`public/js/views/products.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/products.js) — Product catalog: stock threshold ribbons (`Healthy`, `Low`, `Critical`), multi-filter search, pagination, and Add/Edit product modal.
* [`public/js/views/purchase_orders.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/purchase_orders.js) — Procurement manager: PO issuance modal, status history inspection, XML export download via `Blob`, and goods receiving modal.
* [`public/js/views/consignment.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/consignment.js) — Vendor liabilities ledger: outstanding debt tracking, settled payout metrics, and batch payout settlement.
* [`public/js/views/movements.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/movements.js) — Immutable stock movement audit ledger and physical count adjustment modal.
* [`public/js/views/returns.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/returns.js) — Customer return restock/refund processing and vendor RMA shipment tracking.
* [`public/js/views/reports.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/reports.js) — Financial reporting: Sales Profitability vs. Inventory Valuation tabs, client-side RFC-compliant CSV export with UTF-8 BOM, and official A4 report printing.
* [`public/js/views/suppliers.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/suppliers.js) — Supplier directory: contact management, status approval toggle, and registration modal.

---

### Legacy Prototype Assets (`assets/`)
*(Note: These files represent an earlier prototype. They are completely unreferenced by [`index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/index.html) and [`public/index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/index.html), which load from `public/`)*
* [`assets/css/style.css`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/css/style.css) — Earlier minimal CSS stylesheet (4.7 KB).
* [`assets/js/app.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/app.js) — Earlier SPA layout bootstrap targeting outdated DOM IDs (`#sidebar`, `#user-info`, `#main-content`).
* [`assets/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/router.js) — Earlier basic router with only 5 routes (`/login`, `/inventory`, `/suppliers`, `/customers`, `/pos`).
* [`assets/js/state.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/state.js) — Earlier minimal store implementation.
* [`assets/js/api/client.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/api/client.js) — Earlier basic fetch wrapper.
* [`assets/js/pages/login.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/pages/login.js) — Earlier login view.
* [`assets/js/pages/inventory.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/pages/inventory.js) — Earlier inventory view.
* [`assets/js/pages/sales.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/pages/sales.js) — Earlier sales view.
* [`assets/js/pages/suppliers.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/pages/suppliers.js) — Earlier suppliers view.
* [`assets/js/pages/customers.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/js/pages/customers.js) — Earlier customers view.

---

## 2. Database & Data Layer (`database/` & `config/`)

### Configured Database Engine
* **Engine**: **MySQL 8.0+ / MariaDB 10.4+** with InnoDB storage engine for all tables.
* **Character Set & Collation**: `utf8mb4` with `utf8mb4_unicode_ci`.
* **ACID Transactions**: Strict foreign keys with `ON DELETE RESTRICT` or `ON DELETE CASCADE`. Multi-table writes (sales, receiving, adjustments) are wrapped in `PDO::beginTransaction()`, `PDO::commit()`, and `PDO::rollBack()`.

### Existing Schema Tables & Inventory Analysis
There are **31 relational tables** declared in [`database/vinjack_schema.sql`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/database/vinjack_schema.sql):

| Category | Table Name | Key Columns | Purpose |
|---|---|---|---|
| **Classification** | `categories` | `id`, `name`, `description` | Item taxonomy |
| | `brands` | `id`, `name` | Manufacturer brands |
| | `services` | `id`, `name`, `charge`, `status` | Workshop labor catalog |
| **Entities** | `suppliers` | `id`, `name`, `status`, `default_payment_terms` | Vendor master records |
| | `customers` | `id`, `name`, `email`, `phone`, `address` | Customer directory |
| | `motorcycles` | `id`, `owner_id`, `plate_number`, `make`, `model`, `vin` | Vehicle registry |
| **Authentication & RBAC** | `users` | `id`, `username`, `email`, `password`, `role`, `status`, `failed_attempts` | System accounts |
| | `all_permissions` | `id`, `key`, `description`, `category` | Master permission catalog (11 keys) |
| | `role_permissions` | `id`, `role` | Role definitions (`Super Admin`, `Admin`, `Salesperson`) |
| | `role_permission_entries` | `role_permission_id`, `permission_key` | Role-to-permission mapping |
| **Catalog & Inventory** | `products` | `id`, `item_code`, `name`, `price`, `default_cost`, `quantity`, `consigned_stock`, `max_stock`, `stock_status`, `status` | Product inventory |
| | `product_supplier_costs` | `id`, `product_id`, `supplier_id`, `cost` | Supplier cost matrix pivot |
| | `serialized_items` | `id`, `product_id`, `serial_number`, `status` | High-value part tracking |
| **Procurement** | `purchase_orders` | `id`, `po_number`, `supplier_id`, `total_amount`, `po_type`, `consignment_method`, `status` | PO header records |
| | `purchase_order_items` | `id`, `purchase_order_id`, `product_id`, `quantity`, `cost`, `quantity_received` | PO item lines |
| | `purchase_order_history` | `id`, `purchase_order_id`, `status`, `updated_by`, `timestamp` | PO audit progression |
| | `deliveries` | `id`, `supplier_id`, `purchase_order_id`, `delivery_type`, `total_cost`, `recorded_by` | Goods delivery header |
| | `delivery_items` | `id`, `delivery_id`, `product_id`, `quantity`, `cost_at_time` | Delivery line items |
| **POS Sales** | `sales` | `id`, `total_amount`, `recorded_by`, `customer_id`, `motorcycle_id`, `created_at` | POS sales transactions |
| | `sale_items` | `id`, `sale_id`, `product_id`, `quantity`, `price_at_time`, `cost_of_goods_sold` | Product sale line items |
| | `sale_services` | `id`, `sale_id`, `service_id`, `price_at_time` | Labor service line items |
| **Returns** | `returns` | `id`, `original_sale_id`, `outcome`, `total_refund_amount`, `recorded_by` | Customer return headers |
| | `return_items` | `id`, `return_id`, `product_id`, `quantity`, `price_at_time` | Returned items |
| | `return_services` | `id`, `return_id`, `service_id`, `price_at_time` | Returned services |
| | `supplier_returns` | `id`, `supplier_id`, `return_date`, `recorded_by` | Supplier RMA headers |
| | `supplier_return_items` | `id`, `supplier_return_id`, `product_id`, `quantity`, `reason`, `was_consigned` | Supplier RMA lines |
| **Consignment** | `consignment_payables` | `id`, `sale_id`, `product_id`, `supplier_id`, `quantity_sold`, `cost_at_time_of_sale`, `amount_owed`, `status`, `paid_date` | Vendor consignment liabilities |
| **Audit & Operations** | `movements` | `id`, `product_id`, `type`, `quantity_change`, `stock_before`, `stock_after`, `reference_id`, `notes`, `recorded_by` | Immutable stock ledger |
| | `audit_logs` | `id`, `user_id`, `action`, `details`, `entity_type`, `entity_id`, `created_at` | System action audit log |
| | `notifications` | `id`, `user_id`, `message`, `type`, `is_read` | Alert messages |
| | `settings` | `id`, `setting_key`, `setting_value` | Key-value store |

### Core Feature Schema Verification

1. **Role-Based Access Control (`Super Admin`, `Admin`, `Salesperson`)**:
   * **Present & Fully Modeled**: `users.role` uses `ENUM('Super Admin', 'Admin', 'Salesperson')`.
   * Granular permissions are modeled via `all_permissions`, `role_permissions`, and `role_permission_entries`.
   * In seed data: `Super Admin` bypasses all restrictions, `Admin` receives all 11 permissions, and `Salesperson` receives 5 restricted permissions (`canViewDashboard`, `canViewInventory`, `canManageSales`, `canViewSuppliers`, `canManageCustomers`).
2. **Soft-Deletes (`deleted_at`)**:
   * **NOT Present**: No tables feature a `deleted_at` timestamp.
   * Entity deactivation is handled through status flags (e.g., `products.status = 'inactive'`, `services.status = 'inactive'`, `users.status = 'inactive'`).
   * In `ProductDAO.php`, `deleteProduct()` performs a hard SQL `DELETE FROM products WHERE id = :id` after checking foreign key usage in `sale_items`.
3. **Cost of Goods Sold (COGS) Tracking**:
   * **Present & Accurately Modeled**:
     * Baseline unit cost is stored in `products.default_cost` and vendor-specific costs in `product_supplier_costs.cost`.
     * Crucially, **historical COGS** is recorded at the moment of sale on each line item in `sale_items.cost_of_goods_sold`. This ensures profitability calculations remain accurate even if product acquisition costs change in the future.
4. **Consignment vs. Direct Purchase Orders**:
   * **Present & Separated**:
     * `purchase_orders.po_type` is an `ENUM('Purchase', 'Consignment')`.
     * `purchase_orders.consignment_method` is an `ENUM('System', 'Manual')`.
     * `deliveries.delivery_type` distinguishes delivery origin.
     * `products.consigned_stock` tracks non-owned inventory on hand.
     * `consignment_payables` isolates amounts owed to suppliers until settled.
5. **Audit Logs & Stock Ledger**:
   * `movements` is **fully present and actively populated**: Tracks product inventory deltas, before/after levels, event type (`SALE`, `DELIVERY`, `ADJUSTMENT`, `RETURN`, `DELIVERY (PO)`), user ID, and timestamp.
   * `audit_logs` is **present in SQL schema, but inactive in PHP**: The table exists, but no controller or DAO writes general system security events to it.

---

## 3. XML Implementation Status (CRITICAL)

The project includes an **active, working XML implementation** using native PHP `DOMDocument`, `SimpleXML`, and the browser's `DOMParser`:

```
                       XML Data Interchange Architecture
                       
  [config/*.xml]
        │
   (SimpleXML) 
        ▼
 [PHP Boot / Config]
        │
        ├───► [SaleDAO::getReceiptXml()] ──────► XML String (DOMDocument)
        │                                             │ (HTTP text/xml)
        │                                             ▼
        │                                     [Browser: pos.js]
        │                                     (DOMParser -> Thermal Modal)
        │
        └───► [PurchaseOrderDAO::getExportXml()] ► XML String (DOMDocument)
                                                      │ (HTTP application/xml)
                                                      ▼
                                              [Browser: purchase_orders.js]
                                              (Blob -> PO-XXXX.xml Download)
```

### Detailed XML Breakdown

| Area | Implementation Mechanism | Location | Completion Status | Notes |
|---|---|---|---|---|
| **System Configuration** | PHP `simplexml_load_file()` | [`api/core/Database.php:64`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Database.php#L64)<br>[`api/config/Config.php:35`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/config/Config.php#L35)<br>[`api/core/JWT.php:130`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/JWT.php#L130) | **Working** | Parses [`config/database.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/database.xml) and [`config/app.xml`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/config/app.xml) on startup for database credentials, JWT secret, and shop settings. |
| **POS Digital Receipts (Backend)** | PHP `\DOMDocument('1.0', 'UTF-8')` | [`api/models/SaleDAO.php:351-465`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/SaleDAO.php#L351-L465)<br>[`api/controllers/SaleController.php:109-118`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/SaleController.php#L109-L118) | **Working** | Generates formatted XML document with `<receipt>`, `<header>`, `<metadata>` (cashier, customer, vehicle plate), `<items>`, `<services>`, and `<summary>`. Returns with `Response::xml()`. |
| **POS Digital Receipts (Frontend)** | JS native `DOMParser()` | [`public/js/views/pos.js:738-842`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/pos.js#L738-L842) | **Working** | [`ApiClient.get()`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/client.js) receives raw XML text. `DOMParser().parseFromString(xmlString, "application/xml")` parses the tree; DOM selectors query `<item>`, `<service>`, and `<summary>` nodes to populate thermal receipt HTML. |
| **Purchase Order B2B Export (Backend)** | PHP `\DOMDocument('1.0', 'UTF-8')` | [`api/models/PurchaseOrderDAO.php:429-521`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/PurchaseOrderDAO.php#L429-L521)<br>[`api/controllers/PurchaseOrderController.php:174-183`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/PurchaseOrderController.php#L174-L183) | **Working** | Assembles structured XML document with `<purchase_order>`, `<order_details>`, `<buyer>` (VinJack details), `<supplier>`, and `<line_items>` (quantities, unit costs, line totals). |
| **Purchase Order B2B Export (Frontend)** | JS `Blob` + Download trigger | [`public/js/views/purchase_orders.js:1003-1023`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/purchase_orders.js#L1003-L1023) | **Working** | Triggered by clicking "Export Official XML" in PO Details. Fetches XML via `apiClient.get()`, wraps in `new Blob([xmlContent], { type: 'application/xml' })`, and initiates browser download of `{po_number}.xml`. |
| **XML Product Import / Backup** | — | — | **Missing** | Outlined in initial plan as a potential administrative backup/restore mechanism, but not implemented. Standard CRUD uses JSON. |

---

## 4. Backend API & Business Logic (`api/`)

### Architecture & Routing Pattern
The backend is built in pure PHP 8.2+ without external frameworks. Requests route through [`api/index.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/index.php), which delegates to [`Core\Router`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/core/Router.php). Resources are registered with uniform dispatch delegates forwarding to `handleRequest($method, $id, $uriParts)`.

### Endpoint Inventory & Completion Status

| Endpoint Group | Method & Route | Auth & RBAC | Status | Implementation Details |
|---|---|---|---|---|
| **Health** | `GET /api/health` | Public | **Working** | Checks live MySQL connectivity via `Core\Database::checkConnection()`. Returns 200 or 503. |
| **Authentication** | `POST /api/auth/login` | Public | **Working** | Validates username/password, verifies bcrypt hash via `password_verify()`, enforces 5-attempt lockout, generates 24-hr HS256 JWT containing role and permission array. |
| | `POST /api/auth/demo-login` | Public | **Working** | Quick login endpoint for demo users (`admin`, `mechanic`, `clerk`). |
| | `GET /api/auth/me` | JWT Bearer | **Working** | Returns authenticated profile and active permission keys. |
| **Products** | `GET /api/products` | `canViewInventory` | **Working** | Multi-column filter (`search`, `category_id`, `brand_id`, `stock_status`, `status`) and pagination. |
| | `GET /api/products/{id}` | `canViewInventory` | **Working** | Detailed product record with supplier costs matrix. |
| | `POST /api/products` | `canManageInventory` | **Working** | Creates product, calculates stock status, updates supplier costs pivot. |
| | `PUT /api/products/{id}` | `canManageInventory` | **Working** | Updates product fields, recalculates stock status, updates supplier costs. |
| | `DELETE /api/products/{id}` | `canManageInventory` | **Working** | Checks for existing `sale_items` rows to preserve referential integrity; performs hard delete. |
| **POS Sales** | `POST /api/sales` | `canManageSales` | **Working** | Wrapped in ACID transaction (`FOR UPDATE` row locking): checks stock availability, computes line totals, captures historical COGS in `sale_items`, deducts stock, recomputes status, logs to `movements`, and creates `consignment_payables` if applicable. |
| | `GET /api/sales/{id}` | `canManageSales` | **Working** | Fetches sale details, line items, and labor services. |
| | `GET /api/sales/{id}/receipt` | `canManageSales` | **Working** | Generates and outputs DOMDocument XML digital receipt (`Content-Type: application/xml`). |
| **Purchase Orders** | `GET /api/purchase-orders` | `canViewSuppliers` | **Working** | Paginated list with status and keyword search filtering. |
| | `GET /api/purchase-orders/{id}` | `canViewSuppliers` | **Working** | Fetches PO details, items, and status history trail. |
| | `POST /api/purchase-orders` | `canManagePurchaseOrders` | **Working** | Generates unique PO number (`PO-YYYY-XXXX`), line items, and initial history record. |
| | `PUT /api/purchase-orders/{id}/status` | `canManagePurchaseOrders` | **Working** | Updates status (`Approved`, `Cancelled`, etc.) and appends to `purchase_order_history`. |
| | `POST /api/purchase-orders/{id}/receive`| `canManagePurchaseOrders` | **Working** | Receives ordered quantities (supports partial receiving), updates `products.quantity`, increments `consigned_stock` if consignment, logs `DELIVERY (PO)` to `movements`, and updates PO status. |
| | `GET /api/purchase-orders/{id}/export-xml`| `canViewSuppliers` | **Working** | Returns native DOMDocument XML document (`Content-Type: application/xml`). |
| **Suppliers** | `GET /api/suppliers` | `canViewSuppliers` | **Working** | Lists suppliers with search and status filtering. |
| | `POST/PUT/DELETE /api/suppliers` | `canManageSuppliers` | **Working** | Full CRUD and status approval toggling (`Approved`, `Pending`, `Rejected`). |
| **Consignment** | `GET /api/consignment/summary` | `canViewSuppliers` | **Working** | Aggregates outstanding liabilities, total settled, and supplier breakdown. |
| | `GET /api/consignment` | `canViewSuppliers` | **Working** | Lists consignment payables with filter by supplier and status (`Owed`/`Paid`). |
| | `POST /api/consignment/settle` | `canManageSuppliers` | **Working** | Settles selected payable IDs (`UPDATE consignment_payables SET status='Paid'`). |
| | `POST /api/consignment/settle-supplier`| `canManageSuppliers`| **Working** | Settles all payables for a specific supplier in a single operation. |
| **Returns** | `GET /api/returns` | `canManageReturns` | **Working** | Lists customer returns with line item breakdowns. |
| | `GET /api/returns/supplier` | `canManageReturns` | **Working** | Lists supplier RMA claims. |
| | `POST /api/returns` | `canManageReturns` | **Working** | Processes customer return (`Restocked`, `Refunded`, `Replaced`): handles stock restock, updates stock status, and logs `RETURN` to `movements`. |
| | `POST /api/returns/supplier` | `canManageReturns` | **Working** | Dispatches supplier RMA for defective/overstock merchandise. |
| **Stock Movements** | `GET /api/movements` | `canViewInventory` | **Working** | Queries immutable stock movement ledger with date, product, and type filters. |
| | `GET /api/movements/metrics` | `canViewInventory` | **Working** | Aggregate movement counts, units received, and units dispensed. |
| | `POST /api/movements` | `canManageInventory` | **Working** | Physical count adjustment engine: calculates delta, updates product quantity, and creates `ADJUSTMENT` movement record. |
| **Reporting** | `GET /api/reports/dashboard` | `canViewDashboard` | **Working** | Revenue, COGS, refunds, net profit, margin %, transaction tickets, and items sold. |
| | `GET /api/reports/top-selling` | `canViewDashboard` | **Working** | Top 5 products by volume and top 5 workshop labor services. |
| | `GET /api/reports/sales` | `canViewReports` | **Working** | Daily sales, COGS, customer refunds, net profit, and item unit aggregations. |
| | `GET /api/reports/valuation` | `canViewReports` | **Working** | Total SKUs, total units, retail value, cost basis, owned vs. consigned value, and breakdowns by Category and Brand. |
| **Customers & Bikes**| `CRUD /api/customers` | `canManageCustomers` | **Working** | Full CRUD for customer profiles and vehicle linkings. |
| | `CRUD /api/motorcycles` | `canManageCustomers` | **Working** | Vehicle registry CRUD (`plate_number`, `make`, `model`, `vin`). |
| **Categories & Brands**| `CRUD /api/categories` & `brands` | `canView/ManageInventory`| **Working** | Full CRUD with item count aggregations. |
| **Users & Security** | `GET /api/users` | `Super Admin` | **Partially Implemented** | Only lists users. No create, edit, lock, or role-permission assignment endpoints. |
| **Audit Log** | `/api/audit-log` | — | **Missing** | No `AuditLogDAO.php` or `AuditLogController.php` exists. (Only stock movements are queried). |
| **Services CRUD** | `/api/services` | — | **Missing** | No `ServiceController.php` exists. Services are only read as sub-queries in Sales/Reports. |
| **Notifications** | `/api/notifications` | — | **Missing** | Table exists, but no controller exposes notifications. |

---

## 5. Frontend & UI (`public/`, `assets/`, `index.html`)

### CSS Framework & Styling Approach
* **Zero External Frameworks**: **No Bootstrap, Tailwind, or Bulma**.
* **Design System**: Hand-crafted in [`public/css/styles.css`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/css/styles.css) (25 KB, 1030 lines).
* **Aesthetics**: Professional enterprise automotive theme matching the live production system (`vinjacksystem.online`).
  - Curated CSS variables (`--primary: #1976d2`, `--bg-page: #f4f6f9`, `--bg-card: #ffffff`, `--success: #2e7d32`, `--warning: #f57c00`, `--danger: #d32f2f`).
  - Google Fonts: `'Inter'` for UI typography and `'JetBrains Mono'` for currency, SKU codes, and monetary figures.
  - Zero external charting dependencies: Dynamic pure SVG vector charts (`<svg>`, `<polyline>`, `<polygon>`) for revenue trends.
  - Dual Print Styles (`@media print`): Dedicated layouts for 80mm thermal receipt slips and official A4 financial reports.

### Module Wiring & API Integration Status

```
                       Frontend View Integration Matrix
                       
  VIEW MODULE                 WIRED API ENDPOINTS                  COMPLETION
  ─────────────────────────────────────────────────────────────────────────────
  login.js          ───► POST /auth/login, /auth/demo-login        [ Fully Wired ]
  dashboard.js      ───► GET /reports/dashboard, /top-selling,     [ Fully Wired ]
                         GET /reports/sales
  pos.js            ───► GET /products, /categories, /customers,   [ Fully Wired ]*
                         POST /sales, GET /sales/{id}/receipt (XML)
  products.js       ───► GET/POST/PUT/DELETE /products,            [ Fully Wired ]
                         GET /categories, /brands
  purchase_orders.js───► GET/POST /purchase-orders,                [ Fully Wired ]
                         PUT /purchase-orders/{id}/status,
                         POST /purchase-orders/{id}/receive,
                         GET /purchase-orders/{id}/export-xml (XML)
  consignment.js    ───► GET /consignment, /consignment/summary,   [ Fully Wired ]
                         POST /consignment/settle, /settle-supplier
  movements.js      ───► GET /movements, /movements/metrics,       [ Fully Wired ]
                         POST /movements (Adjustments)
  returns.js        ───► GET/POST /returns,                        [ Fully Wired ]
                         GET/POST /returns/supplier
  reports.js        ───► GET /reports/sales, /reports/valuation    [ Fully Wired ]
  suppliers.js      ───► GET/POST/PUT/DELETE /suppliers            [ Fully Wired ]
  ─────────────────────────────────────────────────────────────────────────────
  * pos.js uses static fallback array for labor services catalog.
```

### Static / Placeholder Modules & Route Aliases
In [`public/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/router.js) and [`index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/index.html), several routes are mapped to placeholder aliases rather than dedicated standalone views:
1. `#/customers`: Routes to `POSPage` (customer modal selection is functional in POS, but there is no standalone customer CRUD management view in `public/js/views/`).
2. `#/deliveries`: Routes to `MovementsPage` (stock receiving is performed inside the Purchase Orders view via modal).
3. `#/transactions`: Routes to `POSPage` (no standalone historical sales transaction browser).
4. `#/audit-log`: Routes to `MovementsPage` (inventory ledger is displayed; general user audit log is omitted).
5. `#/suppliers`: Sidebar link for "User Management" (`#nav-user-mgmt`) has `href="#/suppliers"`, redirecting to suppliers due to lack of a user management UI.
6. Notification Bell (`#notif-btn`): The topbar bell icon displays a notification badge dot, but has no click listener or popover drawer.

---

## 6. Local Testing Environment Readiness

### Testing Options & Environment Evaluation

#### Option A: Native Apache + MySQL (XAMPP / Laragon / WampServer) — **RECOMMENDED**
* **Why**: The project includes Apache [`.htaccess`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.htaccess) rules with `RewriteBase /VINJACK-PHP/` and `RewriteBase /VINJACK-PHP/api/`. XAMPP matches the local development specification in `README.md`.
* **Exact Terminal Commands to Spin Up**:
  ```powershell
  # 1. Ensure MySQL is running, then create database and import schema
  mysql -u root -e "CREATE DATABASE IF NOT EXISTS vinjack_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -u root vinjack_db < "d:\Projects\VINJACK-PHP\VINJACK-PHP\database\vinjack_schema.sql"

  # 2. Verify connection via API health check in your browser or curl:
  # http://localhost/VINJACK-PHP/api/health
  # Expected Response: {"status":"healthy","database":{"connected":true}}

  # 3. Open Application in Browser:
  # http://localhost/VINJACK-PHP/
  # Log in with: admin / password123
  ```

#### Option B: Docker Container (`Dockerfile`)
* **Status**: A single [`Dockerfile`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/Dockerfile) (`php:8.2-apache`) is present, but **there is no `docker-compose.yml`** to provision the companion MySQL database container.
* **Exact Terminal Commands to Spin Up with Docker**:
  ```bash
  # 1. Create a Docker bridge network
  docker network create vinjack-net

  # 2. Run a MySQL 8.0 container on that network
  docker run -d --name vinjack-mysql --network vinjack-net \
    -e MYSQL_ROOT_PASSWORD=rootpass \
    -e MYSQL_DATABASE=vinjack_db \
    -p 3306:3306 \
    mysql:8.0

  # 3. Import schema into MySQL container
  docker exec -i vinjack-mysql mysql -u root -prootpass vinjack_db < ./database/vinjack_schema.sql

  # 4. Build and run the VINJACK-PHP container
  docker build -t vinjack-php .
  docker run -d --name vinjack-app --network vinjack-net \
    -p 8080:80 \
    -e DB_HOST=vinjack-mysql \
    -e DB_PORT=3306 \
    -e DB_NAME=vinjack_db \
    -e DB_USER=root \
    -e DB_PASSWORD=rootpass \
    vinjack-php

  # 5. Access at: http://localhost:8080/
  ```

#### Option C: PHP Built-in Server (`php -S`)
* **Evaluation**: The PHP built-in server **does not parse Apache `.htaccess` rewrite rules**. However, because [`public/js/client.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/client.js) uses `api/index.php?request=` as its base query, basic API calls can still resolve if served from the workspace root.
* **Command**:
  ```powershell
  php -S localhost:8000
  # Browse to: http://localhost:8000/
  ```

### Clutter & Hygiene Audit
1. **`upload.zip.zip`**:
   * Size: 311,885 bytes (~312 KB).
   * Status: Tracked in Git root (`git ls-files upload.zip.zip`).
   * Action Needed: Untrack via `git rm --cached upload.zip.zip`, delete file, and append `*.zip` to [`.gitignore`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.gitignore).
2. **Obsolete Prototype Folder (`assets/`)**:
   * Contains 10 legacy files (`assets/css/style.css`, `assets/js/app.js`, `assets/js/router.js`, `assets/js/pages/*`).
   * Completely unreferenced by [`index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/index.html) and [`public/index.html`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/index.html).
   * Action Needed: Safe to archive or clean up to avoid developer confusion.
3. **Duplicate XML Files**:
   * `app.xml` and `database.xml` exist in both `config/` (root) and `api/config/`. Deduplication is recommended.

---

## 7. Gap Analysis vs. Proposal Requirements

Cross-referencing current code against core requirements defined in the STI proposal specification ([`docs/SME_Client_Based_Online_Web_Application_Proposal_Template.docx.pdf`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/docs/SME_Client_Based_Online_Web_Application_Proposal_Template.docx.pdf)):

```
                        Gap Analysis Summary
                        
  REQUIREMENT                                STATUS
  ──────────────────────────────────────────────────────────────────
  (a) Role-Based Access Control (RBAC)       Partially Implemented
  (b) Inventory Control & Low-Stock Alerts   Partially Implemented
  (c) POS with COGS & XML Receipts           Working
  (d) Supplier Procurement & Consignment     Working
  (e) Sales Reporting & Net Gross Profit     Working
  (f) Immutable Audit Trail                  Partially Implemented
  ──────────────────────────────────────────────────────────────────
```

### Detailed Breakdown

#### 1. (a) Role-Based Access Control (RBAC) — **Partially Implemented**
* **Working**:
  * Database schema includes roles (`Super Admin`, `Admin`, `Salesperson`) and granular permission tables.
  * JWT token encodes user role and assigned permissions.
  * [`RBACMiddleware::checkPermission()`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/middleware/RBACMiddleware.php) guards endpoints and blocks unauthorized actions with 403 Forbidden.
  * Frontend conditionally hides or shows management buttons (`+ New Purchase Order`, `Adjust Stock Count`, `Batch Settle`) using `State.hasPermission()`.
* **Missing / Incomplete**:
  * No User Management UI. The sidebar link `User Management` (`#nav-user-mgmt`) has `href="#/suppliers"`.
  * Backend [`UserController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/UserController.php) only has `getAllUsers()`. It lacks endpoints to create users, update passwords, toggle status, or assign permissions.

#### 2. (b) Inventory Control with Low-Stock Alerts — **Partially Implemented**
* **Working**:
  * Full product catalog CRUD with categories, brands, and multiple supplier costs.
  * Automated dynamic recomputation of stock status (`Healthy`, `Low`, `Critical`, `Out of Stock`) upon sales, receiving, and manual adjustments.
  * Inventory view displays stock status threshold cards (`Healthy (>15)`, `Low (6-15)`, `Critical/Out of Stock (≤5)`) and status filter.
* **Missing / Incomplete**:
  * No proactive alert push or notification endpoint.
  * Topbar notification bell (`#notif-btn`) is a static visual placeholder; clicking it does not display alerts.
  * The `notifications` table exists in MySQL, but no backend event inserts records when stock drops below threshold.

#### 3. (c) POS with COGS & XML Receipts — **Working**
* **Working**:
  * Complete POS interface with live search, category pills, cart, customer selection, and cash tender calculation.
  * ACID-compliant transaction with pessimistic row locking (`FOR UPDATE`) preventing negative inventory or race conditions.
  * Captures historical unit COGS on each `sale_items` record at time of checkout.
  * Backend generates compliant digital receipt XML via native PHP `DOMDocument`.
  * Frontend parses XML payload using native browser `DOMParser()` and renders thermal receipt preview modal with print support.
* **Minor Gap**:
  * Services tab in POS uses a static JavaScript fallback array because there is no `/services` CRUD API on the backend.
  * Transaction history route (`#/transactions`) redirects to `POSPage` rather than showing a dedicated receipts log.

#### 4. (d) Supplier Procurement & Consignment Tracking — **Working**
* **Working**:
  * Full supplier CRUD with payment terms (`Cash`, `Consignment`, `Terms`) and approval status toggling.
  * Purchase Orders workflow: PO issuance, status updates, line items, and partial stock receiving.
  * B2B XML Data Interchange: Generates official hierarchical PO XML via PHP `DOMDocument`; downloaded in browser via `Blob`.
  * Consignment tracking: PO receiving increments `products.consigned_stock`. POS checkout automatically creates `consignment_payables`. Consignment view calculates total liabilities and supports single and batch vendor settlements.
* **Minor Gap**:
  * Supplier agreement image/PDF uploads (`delivery_receipt_url`, `signed_agreement_url`) are present as schema columns, but file upload handling is not implemented.

#### 5. (e) Sales Reporting with Net Gross Profit — **Working**
* **Working**:
  * Executive Dashboard: 6 KPI scorecards (Total Revenue, Total Profit, Total Sales, Items Sold, Stock Units, SKUs), pure SVG revenue trend chart, Top 5 products and labor services, and date filters (`Today`, `Week`, `Month`, `All Time`).
  * Reports module: Dual-tab reporting for Sales Profitability vs. Inventory Valuation.
  * Profit calculation accurately subtracts returns and historical COGS: `Net Sales = Gross Sales - Refunds`, `Gross Profit = Net Sales - COGS`.
  * Inventory valuation accurately calculates retail value, cost basis, owned vs. consigned value, and category/brand breakdowns.
  * RFC-compliant CSV export with UTF-8 BOM (`\uFEFF`) and `@media print` A4 printable executive report formatting.

#### 6. (f) Immutable Audit Trail — **Partially Implemented**
* **Working**:
  * Stock Movement Ledger (`movements`) is **fully implemented and operational**:
    * Every sale automatically records a `SALE` entry with negative quantity delta, stock before, and stock after.
    * Every PO receiving records a `DELIVERY (PO)` entry.
    * Every customer return restock records a `RETURN` entry.
    * Physical count adjustments compute delta and log an `ADJUSTMENT` entry with audit notes and user attribution.
    * The `movements.js` view displays this history with date and type filtering.
* **Missing / Incomplete**:
  * General System Audit Log (`audit_logs` table) is not integrated into PHP.
  * No `AuditLogDAO.php` or `AuditLogController.php` exists. Administrative actions (logins, logouts, supplier modifications, user changes) are not logged to the database.

---

## 8. Prioritized Remediation Roadmap

To bring the project to 100% completion against the proposal template:

1. **Clean Clutter & Git Staging**:
   * Untrack and remove [`upload.zip.zip`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/upload.zip.zip) and add `*.zip` to [`.gitignore`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/.gitignore).
   * Delete or archive unreferenced prototype directory [`assets/`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/assets/).
2. **Implement User Management UI & Endpoints**:
   * Add `POST /api/users` (create user), `PUT /api/users/{id}` (edit profile/role), and `PUT /api/users/{id}/status` in [`UserController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/UserController.php).
   * Create `public/js/views/users.js` and link `#nav-user-mgmt` to `#/users`.
3. **Wire System Audit Trail**:
   * Implement `AuditLogDAO.php` and `AuditLogController.php` to capture logins, logouts, supplier edits, and security changes into `audit_logs`.
4. **Expose Labor Services CRUD**:
   * Implement `ServiceDAO.php` and `ServiceController.php` to eliminate the static fallback services array in [`public/js/views/pos.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/pos.js).
5. **Add Docker Compose File**:
   * Create `docker-compose.yml` provisioning both `vinjack-app` (PHP 8.2 Apache) and `vinjack-db` (MySQL 8.0) with automated schema import for turnkey local and CI evaluation.
