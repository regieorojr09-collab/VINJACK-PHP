# Vinjack Motorworks — Migration Task Tracker

## Phase 1: Foundation (Database + Core Backend)
- [x] 1. Generate `vinjack_schema.sql` — complete DDL with all 27 tables
- [x] 2. Generate `vinjack_seed.sql` — seed permissions, roles, demo Super Admin
- [x] 3. Create `api/config/Database.php` — PDO singleton with XML config
- [x] 4. Create `api/config/Config.php` — XML configuration parser
- [x] 5. Create `config/database.xml` + `config/app.xml`
- [x] 6. Create `api/utils/Response.php`, `JWTHelper.php`, `Validator.php`
- [x] 7. Create `api/index.php` — front controller with URL routing
- [x] 8. Create `api/.htaccess` — Apache rewrite rules
- [x] 9. Create `api/middleware/AuthMiddleware.php`, `RBACMiddleware.php`, `CorsMiddleware.php`

## Phase 2: Core DAOs & Controllers
- [x] 10. `UserDAO.php` + `AuthController.php` + `UserController.php`
- [x] 11. `CategoryDAO.php` + `BrandDAO.php` + controllers
- [x] 12. `ProductDAO.php` + `ProductController.php`
- [ ] 13. `ServiceDAO.php` + `ServiceController.php`
- [x] 14. `CustomerDAO.php` + `MotorcycleDAO.php` + controllers
- [x] 15. `SupplierDAO.php` + `SupplierController.php`

## Phase 3: Transactional Business Logic
- [x] 16. `SaleDAO.php` + `SaleController.php` (POS with transaction, COGS, consignment)
- [ ] 17. `StockManager.php` + `MovementLogger.php` + `AuditLogger.php`
- [x] 18. `PurchaseOrderDAO.php` + `PurchaseOrderController.php`
- [ ] 19. `DeliveryDAO.php` + `DeliveryController.php`
- [x] 20. `ReturnDAO.php` + `ReturnController.php`
- [x] 21. `SupplierReturnDAO.php` + `SupplierReturnController.php` (consolidated in ReturnDAO)
- [x] 22. `ConsignmentDAO.php` + `ConsignmentController.php`
- [x] 23. `MovementDAO.php` + `MovementController.php` (with count adjustment engine)

## Phase 4: Reporting & System
- [x] 24. `ReportDAO.php` + `ReportController.php` (Dashboard, Top Selling, Valuation, Sales reports)
- [ ] 25. `NotificationDAO.php` + `NotificationController.php`
- [ ] 26. `AuditLogDAO.php` + `AuditLogController.php`
- [ ] 27. `SettingDAO.php` + `SettingsController.php`
- [ ] 28. `PermissionDAO.php` + `PermissionController.php`

## Phase 5: Frontend SPA Shell
- [x] 29. `index.html` & `public/index.html` — SPA entry point with responsive sidebar & topbar
- [x] 30. CSS design system (`public/css/styles.css` — automotive dark theme, cards, tables, badges, skeletons, health progress bar)
- [x] 31. `public/js/router.js` — hash-based SPA routing with auth guards & dashboard default
- [x] 32. `public/js/state.js` — centralized reactive store with pub/sub
- [x] 33. `public/js/client.js` — fetch wrapper with auth header interceptor & response handling
- [x] 34. Core UI utilities (alert banner, modals, formatted badges, segmented tracks)

## Phase 6: Frontend Pages
- [x] 35. `login.js` — login page with credential authentication
- [x] 36. `dashboard.js` — Executive dashboard (KPI cards, pure CSS stock health meter, top products/services, activity ledger)
- [x] 37. `products.js` — product catalog & inventory management
- [x] 38. `pos.js` — POS terminal & XML Digital Receipt generation
- [x] 39. `suppliers.js` — supplier directory & CRUD
- [x] 40. `purchase_orders.js` — PO registry, XML export & stock receiving
- [ ] 41. `deliveries.js` — delivery recording
- [ ] 42. `customers.js` — customer + motorcycle management
- [x] 43. `returns.js` — Customer returns, restock & supplier RMA dispatches
- [x] 44. `consignment.js` — Vendor consignment liabilities & payout settlements
- [x] 44a. `movements.js` — Stock ledger & physical count adjustments
- [ ] 45. `transactions.js`
- [x] 46. `reports.js` — Dual-tab sales profitability & inventory valuation with client CSV export & A4 print
- [ ] 47. `alerts.js`
- [ ] 48. `settings.js` + `userManagement.js` + `auditLog.js` + `permissionManagement.js` + `dataManagement.js`
- [ ] 49. `supplierPOReview.js`

## Phase 7: Integration & Polish
- [x] 50. End-to-end testing of all business flows & ReportDAO
- [x] 51. Pure CSS/SVG data visualization (No external charting libraries)
- [x] 52. Dual Print formatting (`@media print` for POS receipts & executive reports)
- [x] 53. Client-side RFC-compliant CSV export with UTF-8 BOM
- [x] 54. Responsive design & dark automotive styling
- [x] 55. PHP 8.2 syntax lint validation across all backend classes
