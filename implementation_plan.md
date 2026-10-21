# VinJack Motorworks - Architectural Blueprint & Implementation Plan

## 1. Goal Description

The goal is to design a comprehensive architectural blueprint and implementation plan for the "Web-Based Sales and Inventory Management System with XML Data Interchange for VinJack Motorworks." We are migrating from a legacy MERN stack prototype to a traditional relational web stack using PHP 8.2+, Vanilla JavaScript (ES6+), XML, and MySQL on a local Apache/XAMPP server. No external UI or backend frameworks (like React or Node.js) will be used.

## 2. Database Architecture

We will implement a fully normalized (3NF) MySQL relational schema. The legacy NoSQL database used embedded arrays, which we will convert into proper relational tables with strict foreign key constraints to ensure ACID compliance.

### Normalization Strategy (Legacy NoSQL to MySQL)
- **Sales and Items**: The embedded `items` and `services` in the `Sale` document will be extracted into `sale_items` and `sale_services` junction tables, referencing `sales`, `products`, and `services` tables.
- **Purchase Orders (PO)**: The `PO.items` and `PO.history` arrays will become `purchase_order_items` and `purchase_order_history` tables.
- **Supplier Costs**: The `supplierCosts` array in `Product` will become a `product_supplier_costs` pivot table linking `products` and `suppliers`.
- **Transactions & ACID**: All multi-table write operations (e.g., creating a sale, deducting inventory, creating a consignment payable) will be wrapped in MySQL `START TRANSACTION`, `COMMIT`, and `ROLLBACK` blocks using PHP's PDO.

### Core Schema Overview (Sample of key tables)
- `users`: Authentication and roles (Super Admin, Admin, Salesperson).
- `products`, `categories`, `brands`: Inventory management.
- `sales`, `sale_items`, `sale_services`: POS transaction tracking.
- `purchase_orders`, `purchase_order_items`, `purchase_order_history`: Supply chain management.
- `suppliers`, `customers`, `motorcycles`: Entity management.
- `movements`: Immutable stock ledger.

## 3. Backend PHP Architecture

The backend will be a robust, object-oriented PHP application tailored for Apache, serving as a RESTful (and XML-enabled) API.

### Separation of Concerns
- **Front Controller & API Routing**: An `index.php` at the API root will intercept all requests (routed via Apache `.htaccess`). A lightweight custom router will dispatch requests to appropriate controllers.
- **Controllers (RESTful)**: E.g., `SaleController.php`, `ProductController.php`. They will handle HTTP request parsing, call DAOs for data, and format responses (JSON or XML).
- **Data Access Objects (DAO)**: E.g., `ProductDAO.php`. All database queries will be encapsulated here using PDO (PHP Data Objects) with prepared statements to prevent SQL injection.
- **Service Layer**: Complex business logic like `StockManager.php` (for stock level alerts) or `MovementLogger.php` will sit between controllers and DAOs.

### Security & RBAC
- **Session Management**: JWT (JSON Web Tokens) will be used for stateless authentication, matching the legacy system's security profile.
- **RBAC Middleware**: A custom middleware layer will verify the JWT and enforce role-based access control based on granular permissions stored in the database.

## 4. XML Integration Strategy

XML will be used strategically for configuration and structured data interchange, fulfilling the capstone rubric requirements.

### Technical Approach
- **PHP Libraries**: 
  - `DOMDocument` will be used for generating complex XML structures (e.g., hierarchical purchase order exports) because of its robust formatting and validation capabilities.
  - `SimpleXML` will be used for parsing incoming XML payloads or reading configuration files due to its ease of use and speed.
- **Use Cases for XML Data Interchange**:
  1. **System Configuration**: Application and database settings will be stored in `config/app.xml` and `config/database.xml` and parsed on startup.
  2. **Purchase Order Exports**: POs will be exported as XML files for suppliers. The system will generate an XML document containing the PO details, supplier info, and item lists.
  3. **Digital Receipt Rendering**: When a sale is completed, the backend will generate an XML representation of the receipt. The frontend will consume this XML to render a printable HTML receipt.
  4. **Data Import/Export**: Administrative functions for backing up or migrating product catalogs will utilize XML payloads.

## 5. Frontend Architecture

The frontend will be a structured Single Page Application (SPA) built using purely Vanilla ES6+ JavaScript, HTML5, and Vanilla CSS.

### Structured Design
- **Client-Side Routing**: A custom hash-based router (`#/dashboard`, `#/sales`) will intercept URL changes, clear the main DOM container, and dynamically load the respective page modules without full page reloads.
- **State Management**: A centralized, lightweight Pub/Sub store (`state.js`) will manage global state like authentication tokens, user roles, and UI notifications.
- **API Interaction**: 
  - The native `fetch()` API will be used for all backend communication, wrapped in a `client.js` utility to automatically attach JWT headers and handle generic error catching.
- **XML Processing via DOMParser**: 
  - When the frontend requests an XML payload (e.g., fetching a receipt format or PO export), it will use the native browser `DOMParser()` to parse the XML string into an XMLDocument.
  - Standard DOM traversal methods (`getElementsByTagName`, `querySelector`) will extract the data for rendering into the UI.
- **DOM Manipulation**: Component functions will return HTML template literals. Event delegation will be heavily utilized on a root container to manage click and form submit events efficiently without memory leaks.

## 6. Phased Implementation Roadmap

- **Phase 1: Foundation & Database Setup**
  - Design and deploy the fully normalized MySQL schema (`vinjack_schema.sql`).
  - Configure Apache `.htaccess`, `database.xml`, and the PHP PDO singleton.
  - Build the backend router, JWT authentication, and RBAC middleware.
- **Phase 2: Core Inventory & Entity Management**
  - Implement DAOs and Controllers for Users, Products, Categories, Brands, Suppliers, and Customers.
  - Build the vanilla frontend SPA shell, routing, and corresponding management pages.
- **Phase 3: Transactional Workflows (POS & Purchasing)**
  - Implement Sales (POS) with ACID transactions, COGS calculation, and XML receipt generation.
  - Implement Purchase Orders with XML data interchange exports.
  - Build frontend Sales and PO interfaces, parsing XML payloads with `DOMParser`.
- **Phase 4: Stock Ledger, Returns & Consignment**
  - Implement the immutable stock ledger (Movements), Customer/Supplier Returns, and Consignment logic.
- **Phase 5: Reporting & Polish**
  - Replace legacy MongoDB aggregations with optimized SQL views for dashboard reports.
  - Finalize vanilla CSS styling, CSS animations, and UI polish.
  - End-to-end testing and bug fixing.

---

## User Review Required

> [!IMPORTANT]
> **XML vs JSON Payloads**: Standard RESTful operations (e.g., CRUD for products) are best suited for JSON. My plan utilizes JSON for standard CRUD, and reserves XML strictly for the documented data interchange requirements (config, PO exports, receipts). Do you approve this hybrid approach, or should *all* API responses be strictly XML?

> [!WARNING]
> **Real-Time Notifications**: The legacy system used Node/Socket.IO for real-time stock alerts. Since we are moving to a pure PHP/Apache stack, I recommend using **polling** (fetching alerts every 30 seconds) or **Server-Sent Events (SSE)**. Which approach do you prefer for real-time updates?

## Proposed Changes

N/A - Waiting for approval on the architectural blueprint.

## Verification Plan

### Manual Verification
- Review the proposed architecture above.
- Approve the structural choices for PHP, Vanilla JS, XML usage, and the MySQL schema plan.
- Provide feedback on the open questions.
