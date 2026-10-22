# VinJack Motorworks - Academic Proposal Scope & Routing Alignment Report

**Date:** October 22, 2026  
**System:** VinJack Motorworks (Web-Based Sales & Inventory Management System)  
**Environment:** Apache 2.4 (Port 81) | PHP 8.2 | MariaDB/MySQL (Port 3307)  

---

## 1. Executive Summary

A system-wide frontend routing diagnostic was performed on the VinJack Motorworks web application to strictly align the user interface, sidebar navigation, and client router with the approved academic proposal scope. 

Features outside the approved scope (such as standalone return processing workflows and redundant delivery/transaction listings) were pruned. Routing misconfigurations and placeholder aliases (such as `#/customers` pointing to POS, and `#/audit-log` aliasing to movements) were permanently resolved by creating dedicated modern views and backend API controllers.

---

## 2. Strict Proposal Alignment: Feature Pruning

Per the approved project charter and requirements specification, the system scope is strictly confined to:
* **Executive Dashboard**
* **Point of Sale (POS) Terminal & Register**
* **Customer & Vehicle Directory**
* **Inventory Control & Catalog**
* **Procurement & Purchase Orders**
* **Supplier Directory & Agreements**
* **Consignment Payables & Settlements**
* **Sales & Financial Reporting**
* **System Audit Trail & Security Ledger**
* **User Management & Role-Based Access Control (RBAC)**

### Sidebar Navigation Links Removed

| Section | Removed Navigation Link | Previous `href` | Reason for Removal / Pruning |
| :--- | :--- | :--- | :--- |
| **Sales** | `Returns` (`#nav-returns`) | `#/returns` | Out of academic scope; formal returns processing workflows are not part of core requirements. |
| **Inventory & Supply** | `Deliveries` (`#nav-deliveries`) | `#/movements` | Redundant route; stock intake and receiving are handled directly within the Purchase Order receiving workflow. |
| **Inventory & Supply** | `Supplier Returns` (`#nav-supplier-returns`) | `#/returns` | Out of academic scope; supplier warranty return pipelines are excluded. |
| **Reports** | `Transactions` (`#nav-transactions`) | `#/pos` | Redundant route; complete sales volume and revenue analysis are centralized in the Dashboard and Reports views. |

### Router Cleanup (`public/js/router.js`)
* Removed the `/returns` route and unlinked the unused `ReturnsPage` import.
* Removed the `/deliveries` alias.
* Removed the `/transactions` alias.

---

## 3. Fixed Routing Aliases & Newly Implemented Views

### A. Customers View (`#/customers`)
* **Previous State:** The `#/customers` route was erroneously aliased to `POSPage`, loading the point-of-sale checkout terminal instead of a customer directory.
* **Remediation:**
  * Created a dedicated view at [`public/js/views/customers.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/customers.js) (`CustomersPage`).
  * Features a responsive client table displaying full name, contact info (phone/email), physical address, linked motorcycles (make, model, plate number badge), and lifetime POS purchase counts.
  * Includes customer profile registration modal and motorcycle linking modal.
  * Real-time client-side search filtering by name, phone, or email.
  * Fully wired to `GET /api/customers`, `POST /api/customers`, `PUT /api/customers/{id}`, and `POST /api/customers/{id}/motorcycles`.
  * Updated [`public/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/router.js) to map `#/customers` $\to$ `CustomersPage`.

### B. Audit Trail View (`#/audit-log`)
* **Previous State:** The `#/audit-log` route aliased directly to `MovementsPage` (stock movements ledger) because no dedicated audit log controller or frontend view existed.
* **Remediation:**
  * Created [`api/models/AuditLogDAO.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/models/AuditLogDAO.php) to query the immutable `audit_logs` table joined with user profile metadata.
  * Created [`api/controllers/AuditLogController.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/controllers/AuditLogController.php) enforcing RBAC verification and serving `GET /api/audit-logs` and `GET /api/audit_logs`.
  * Registered the resource in [`api/index.php`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/api/index.php).
  * Built [`public/js/views/audit_logs.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/audit_logs.js) (`AuditLogsPage`), featuring:
    * Metrics summary ribbon (Total Events, Authentication, Procurement, Inventory Adjustments).
    * Action-type color badges (`USER_LOGIN`, `PURCHASE_ORDER_CREATED`, `PRICE_UPDATED`, etc.).
    * Filters by action, entity type, and live keyword search.
    * Actor info displaying full name, `@username`, and role badge.
  * Updated sidebar `#nav-audit-log` in `index.html` and `public/index.html` to link to `href="#/audit-log"`.
  * Updated [`public/js/router.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/router.js) to map `#/audit-log`, `#/audit-logs`, and `#/audit_logs` $\to$ `AuditLogsPage`.

### C. User Management (`#/users`)
* **State Verification:** Verified that `#/users` and `#/user-management` map directly to [`public/js/views/users.js`](file:///d:/Projects/VINJACK-PHP/VINJACK-PHP/public/js/views/users.js) (`UsersPage`).
* **Capabilities:** Complete user directory, Super Admin account creation, role badges, active/locked/inactive status toggles, and security metrics ribbon.
* Sidebar link `#nav-user-mgmt` properly routes to `href="#/users"`.

---

## 4. Current Approved Active Navigation Map

| Section | Label | Active Route | Dedicated View Component | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Overview** | Dashboard | `#/dashboard` | `DashboardPage` | Active |
| **Sales** | Sales (POS) | `#/pos` | `POSPage` | Active |
| **Sales** | Customers | `#/customers` | `CustomersPage` | **Fixed & Built** |
| **Inventory & Supply** | Inventory | `#/products` / `#/inventory` | `ProductsPage` | Active |
| **Inventory & Supply** | Purchase Orders | `#/purchase-orders` | `PurchaseOrdersPage` | Active |
| **Inventory & Supply** | Suppliers | `#/suppliers` | `SuppliersPage` | Active |
| **Inventory & Supply** | Consignment Payouts | `#/consignment` | `ConsignmentPage` | Active |
| **Reports** | Reports | `#/reports` | `ReportsPage` | Active |
| **Reports** | Audit Log | `#/audit-log` | `AuditLogsPage` | **Fixed & Built** |
| **Admin** | User Management | `#/users` | `UsersPage` | Active |

---

## 5. Live Endpoint & Route Diagnostics

| Target Resource | Tested Endpoint | HTTP Method | Response Status | Data Returned |
| :--- | :--- | :--- | :--- | :--- |
| **Authentication** | `/api/auth/login` | `POST` | `200 OK` | Valid signed JWT issued |
| **Customers** | `/api/customers` | `GET` | `200 OK` | 4 customer profiles with motorcycles |
| **Audit Logs** | `/api/audit-logs` | `GET` | `200 OK` | 6 timestamped system audit events |
| **Users** | `/api/users` | `GET` | `200 OK` | 3 registered user accounts |
| **Products Catalog** | `/api/products` | `GET` | `200 OK` | 10 active products |

All endpoints tested directly against Apache on port 81 and MariaDB on port 3307. Zero syntax or routing errors remain.
