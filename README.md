# Web-Based Sales and Inventory Management System with XML Data Interchange
### Client: VinJack Motorworks (Marikina City, Philippines)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B%20InnoDB-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B%20Vanilla-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![XML](https://img.shields.io/badge/Interchange-XML%20%26%20DOMParser-FF6600)](https://www.w3.org/XML/)
[![License](https://img.shields.io/badge/License-Proprietary-red)](#)

---

## 🌐 Live Application & Access Details

| Environment | URL | Description |
|---|---|---|
| **Production / Live System** | [https://vinjacksystem.online](https://vinjacksystem.online) | Hosted production instance |
| **Local Development** | [http://localhost/VINJACK-PHP/](http://localhost/VINJACK-PHP/) | Local Apache / XAMPP server |

### 🔑 Seeded Test Credentials

| Role | Username | Password | Access Scope |
|---|---|---|---|
| **Super Admin** | `admin` | `password123` | Unrestricted bypass; full system access, user management, and DB operations |
| **Admin (Shop Lead)** | `mechanic` | `password123` | Dashboard, Inventory, Purchase Orders, Reports, and Movements |
| **Salesperson (Counter)** | `clerk` | `password123` | Dashboard summary, POS Terminal, Customer records, and Catalog browsing |

---

## 🏛️ System Architecture

* **Backend Engine**: Pure Object-Oriented PHP 8.2+ with strict typing (`declare(strict_types=1);`).
* **Architecture Pattern**: Front Controller pattern via Apache `.htaccess`, centralized router (`api/index.php`), isolated Data Access Objects (`api/models/*DAO.php`), and RESTful JSON/XML controllers.
* **Database Design**: Fully normalized 3NF MySQL 8.0+ / MariaDB relational schema utilizing InnoDB with strict foreign key constraints, cascading rules, and ACID transactional integrity (`PDO::beginTransaction()`, `PDO::commit()`, `PDO::rollBack()`).
* **Frontend Standard**: Vanilla Single Page Application (SPA) driven by client-side hash routing (`#/dashboard`, `#/pos`, `#/products`, `#/reports`), reactive centralized store (`state.js`), native `fetch()` wrapper (`client.js`), and pure CSS/SVG data visualizations (**Zero external charting libraries and Zero UI frameworks**).

---

## 🔄 XML Data Interchange Specifications

In compliance with academic and industry data interchange requirements, the system executes strategic XML workflows via native PHP `DOMDocument`/`SimpleXML` and browser `DOMParser`:

1. **System Configuration (`config/*.xml`)**:
   - `config/database.xml`: XML-based database connection definitions parsed at boot time.
   - `config/app.xml`: Business configurations (company metadata, tax rates, currency, notification flags).
2. **External Purchase Order Interchange**:
   - PO exports are generated as structured, hierarchical XML documents ready for supply-chain B2B exchange with vendors.
3. **Digital Sales Receipts**:
   - POS checkout dynamically compiles receipt data into XML documents.
   - The browser parses the XML payload using native `DOMParser()` and transforms it into thermal receipts and printable invoices.

---

## 🚀 Key Functional Modules

* **📊 Executive Dashboard (`#/dashboard`)**:
  - Six color-coded metric scorecards: Total Revenue, Total Profit, Total Sales, Items Sold, Stock Units, and Product SKUs.
  - Interactive Revenue Trend card with pure SVG vector graphics and empty-state placeholders.
  - Top 5 Bestselling Products and Top 5 Workshop Labor Services leaderboards.
  - Date-range toggle filters (Today, Week, Month, All Time).
* **💳 Point of Sale Terminal (`#/pos`)**:
  - Live product search, category pills, customer selector, dynamic cart calculations, discounts, and tender calculation.
  - ACID inventory deduction, historical COGS capture, and automatic consignment payable creation.
* **📦 Inventory Catalog (`#/products`)**:
  - Multi-tier product taxonomy (Categories, Brands, Suppliers).
  - Safety threshold indicators (Healthy, Low, Critical, Out of Stock).
* **📋 Procurement & Purchase Orders (`#/purchase-orders`)**:
  - Multi-item supplier purchase orders, XML export, status progression, and stock receiving ledger.
* **🔄 Immutable Stock Ledger (`#/movements`)**:
  - Comprehensive physical count reconciliation with automated audit trail logging.
* **↩️ Returns & Warranty Claims (`#/returns`)**:
  - Customer return handling (Restock, Refund, or Replace) with automatic financial adjustments.
  - Supplier RMA tracking with vendor dispatch logging.
* **🤝 Consignment Payables (`#/consignment`)**:
  - Supplier consignment liabilities tracking, sales volume reconciliation, and payout settlements.
* **📈 Reports & Financial Analytics (`#/reports`)**:
  - Dual-tab reporting: Sales & Profitability Breakdown vs. Asset Inventory Valuation.
  - Client-side RFC-compliant CSV export with UTF-8 BOM (`Blob`).
  - Dual-mode print media formatting (`@media print`) for thermal POS slips and official A4 reports.

---

## 🛠️ Local Installation & Setup

### Prerequisites
* [XAMPP](https://www.apachefriends.org/) (Apache 2.4+, PHP 8.2+, MySQL 8.0+/MariaDB 10.4+)
* Git for Windows

### Setup Steps
1. Clone the repository into your XAMPP web root:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/regieorojr09-collab/VINJACK-PHP.git
   ```
2. Start **Apache** and **MySQL** in XAMPP Control Panel.
3. Import the database schema and seeds via phpMyAdmin or MySQL CLI:
   ```bash
   mysql -u root -p < C:\xampp\htdocs\VINJACK-PHP\database\vinjack_schema.sql
   ```
4. Access the application in your browser:
   ```text
   http://localhost/VINJACK-PHP/
   ```

---

## ⚡ Git Auto-Sync Daemon

The project includes an automated file-watcher daemon that detects saves in the workspace, debounces by 10 seconds, and commits and pushes to GitHub automatically:

* **Run via PowerShell**:
  ```powershell
  powershell -ExecutionPolicy Bypass -File .\scripts\auto-sync.ps1
  ```
* **Run via VS Code / Antigravity**:
  Press `Ctrl+Shift+P` → **Tasks: Run Task** → Select **Start Git Auto-Sync**.
