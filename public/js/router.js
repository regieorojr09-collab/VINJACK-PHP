import { State } from './state.js';
import { LoginPage } from './views/login.js';
import { DashboardPage } from './views/dashboard.js?v=20261101d';
import { ReportsPage } from './views/reports.js?v=20261101d';
import { ProductsPage } from './views/products.js';
import { SuppliersPage } from './views/suppliers.js';
import { POSPage } from './views/pos.js';
import { PurchaseOrdersPage } from './views/purchase_orders.js';
import { MovementsPage } from './views/movements.js';
import { UsersPage } from './views/users.js?v=20261101d';
import { CustomersPage } from './views/customers.js';
import { AuditLogsPage } from './views/audit_logs.js?v=20261101d';

/**
 * Client-Side Hash Router
 * 
 * Auto-sync verified: Real-time watcher pipeline active.
 * Manages view transitions, authentication guards, RBAC route guards, and active navigation indicators.
 */
class Router {
    constructor() {
        this.routes = {
            '/login':           { view: LoginPage,          title: 'Sign In' },
            '/dashboard':       { view: DashboardPage,      title: 'Executive Dashboard' },
            '/reports':         { view: ReportsPage,        title: 'Reports & Financial Analytics' },
            '/analytics':       { view: ReportsPage,        title: 'Reports & Financial Analytics' },
            '/pos':             { view: POSPage,            title: 'Point of Sale (POS) Terminal' },
            '/sales':           { view: POSPage,            title: 'Point of Sale (POS) Terminal' },
            '/products':        { view: ProductsPage,       title: 'Inventory & Products' },
            '/inventory':       { view: ProductsPage,       title: 'Inventory & Products' },
            '/movements':       { view: MovementsPage,      title: 'Stock Movement Ledger' },
            '/ledger':          { view: MovementsPage,      title: 'Stock Movement Ledger' },
            '/suppliers':       { view: SuppliersPage,      title: 'Suppliers Directory' },
            '/purchase-orders': { view: PurchaseOrdersPage, title: 'Purchase Orders & Procurement' },
            '/purchase_orders': { view: PurchaseOrdersPage, title: 'Purchase Orders & Procurement' },
            '/customers':       { view: CustomersPage,       title: 'Customer Directory & Vehicles' },
            '/audit-log':       { view: AuditLogsPage,      title: 'System Audit Trail' },
            '/audit-logs':      { view: AuditLogsPage,      title: 'System Audit Trail' },
            '/audit_logs':      { view: AuditLogsPage,      title: 'System Audit Trail' },
            '/users':           { view: UsersPage,          title: 'User Management & Access Control' },
            '/user-management': { view: UsersPage,          title: 'User Management & Access Control' }
        };

        this.adminOnlyRoutes = new Set([
            '/users',
            '/user-management',
            '/audit-log',
            '/audit-logs',
            '/audit_logs',
            '/purchase-orders',
            '/purchase_orders'
        ]);

        this.appRoot = null;
        this.pageTitleEl = null;
        this.sidebarEl = null;
        this.topbarEl = null;
    }

    /**
     * Self-contained check for Admin / Super Admin role (safe even if an older state.js is cached).
     */
    hasAdminPrivileges() {
        if (!State || typeof State.isAuthenticated !== 'function' || !State.isAuthenticated()) {
            return false;
        }
        const role = State.user?.role || '';
        return role === 'Super Admin' || role === 'Admin';
    }

    init() {
        // Ensure DOM is fully parsed before querying #app-root or #sidebar elements
        if (typeof document !== 'undefined' && document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init(), { once: true });
            return;
        }

        this.appRoot = document.getElementById('app-root');
        this.pageTitleEl = document.getElementById('current-page-title');
        this.sidebarEl = document.getElementById('sidebar') || document.getElementById('app-sidebar');
        this.topbarEl = document.getElementById('app-topbar');

        // Handle URL Hash Changes
        window.addEventListener('hashchange', () => this.handleRoute());

        // React to Auth State Changes
        State.subscribe('auth:changed', (isAuthenticated) => {
            this.updateLayoutAuthVisibility(isAuthenticated);
            if (!isAuthenticated) {
                window.location.hash = '#/login';
            } else if (window.location.hash === '#/login' || window.location.hash === '') {
                window.location.hash = '#/dashboard';
            }
        });

        // Setup Alert Banner listener
        State.subscribe('alert:changed', (alertData) => {
            this.renderAlertBanner(alertData);
        });

        // Setup Profile Dropdown Toggle & Outside Click Closer
        const profileToggle = document.getElementById('profile-dropdown-toggle');
        const profileMenu = document.getElementById('profile-dropdown-menu');

        profileToggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            profileMenu?.classList?.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!profileMenu?.contains?.(e.target) && !profileToggle?.contains?.(e.target)) {
                profileMenu?.classList?.add('hidden');
            }
        });

        // Setup Logout handlers (Dropdown Logout & Fallback)
        const handleLogout = () => {
            profileMenu?.classList?.add('hidden');
            State.clearUser();
            State.setAlert('You have been signed out.', 'info');
        };

        document.getElementById('dropdown-logout-btn')?.addEventListener('click', handleLogout);
        document.getElementById('logout-btn')?.addEventListener('click', handleLogout);

        // Setup Sidebar Collapse Mechanics
        const sidebarToggle = document.getElementById('sidebar-toggle') || document.getElementById('sidebar-toggle-btn');
        const sidebar = document.getElementById('sidebar') || document.getElementById('app-sidebar') || this.sidebarEl;

        const updateCollapseButtonIcon = (isCollapsed) => {
            if (!sidebarToggle) return;
            sidebarToggle.innerHTML = isCollapsed ? '&rsaquo;' : '&lsaquo;';
            sidebarToggle.setAttribute('title', isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar');
        };

        sidebarToggle?.addEventListener('click', () => {
            sidebar?.classList?.toggle('collapsed');
            const isCollapsed = sidebar?.classList?.contains('collapsed');
            updateCollapseButtonIcon(isCollapsed);
            try {
                localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
            } catch (e) {}
        });

        // Restore collapsed state preference if saved
        try {
            if (localStorage.getItem('sidebar_collapsed') === 'true') {
                sidebar?.classList?.add('collapsed');
                updateCollapseButtonIcon(true);
            }
        } catch (e) {}

        // Initialize UI visibility and sidebar permissions after sidebar DOM is available
        this.updateLayoutAuthVisibility(State.isAuthenticated());

        // Initial Route Dispatch
        this.handleRoute();
    }

    handleRoute() {
        const hash = window.location.hash || '#/dashboard';
        const path = hash.replace(/^#/, '');

        // Enforce sidebar permission guards safely on every route evaluation
        this.applySidebarPermissions();

        // Auth Guard
        if (!State.isAuthenticated() && path !== '/login') {
            window.location.hash = '#/login';
            return;
        }

        if (State.isAuthenticated() && (path === '/login' || path === '/' || path === '')) {
            window.location.hash = '#/dashboard';
            return;
        }

        // Strict RBAC Guard for User Management, Audit Log, Purchase Orders, and Reports
        if (State.isAuthenticated()) {
            const isAdmin = this.hasAdminPrivileges();
            const isPurchaseOrderRoute = path === '/purchase-orders' || path === '/purchase_orders';
            const isReportRoute = path === '/reports' || path === '/analytics';

            const deniedAdminRoute = this.adminOnlyRoutes.has(path) && (
                !isAdmin || (isPurchaseOrderRoute && !State.hasPermission('canManagePurchaseOrders'))
            );
            const deniedReportRoute = isReportRoute && (!isAdmin || !State.hasPermission('canViewReports'));

            if (deniedAdminRoute || deniedReportRoute) {
                State.setAlert('403 Forbidden: Access Denied', 'danger');
                if (window.location.hash !== '#/dashboard') {
                    window.location.hash = '#/dashboard';
                } else {
                    this.renderView(DashboardPage, 'Executive Dashboard', '/dashboard');
                }
                return;
            }
        }

        const route = this.routes[path];
        if (route) {
            this.renderView(route.view, route.title, path);
        } else {
            this.render404(path);
        }
    }

    renderView(view, title, path) {
        if (!this.appRoot) {
            this.appRoot = document.getElementById('app-root');
        }
        if (!this.appRoot) return;

        // Update active navigation item in sidebar
        const canonicalMap = {
            '/sales': '/pos',
            '/inventory': '/products',
            '/ledger': '/products',
            '/analytics': '/reports',
            '/purchase_orders': '/purchase-orders',
            '/audit_logs': '/audit-log',
            '/audit-logs': '/audit-log',
            '/user-management': '/users'
        };
        const activeTarget = canonicalMap[path] || path;

        document.querySelectorAll('.nav-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href === `#${path}` || href === `#${activeTarget}`) {
                link.classList?.add('active');
            } else {
                link.classList?.remove('active');
            }
        });

        // Update Topbar Title
        if (this.pageTitleEl) {
            this.pageTitleEl.textContent = title;
        }

        // Render Component HTML
        this.appRoot.innerHTML = view.render();

        // Re-verify sidebar permissions after view mount
        this.applySidebarPermissions();

        // Lifecycle hook
        setTimeout(() => {
            if (typeof view.init === 'function') {
                view.init();
            }
        }, 0);
    }

    render404(path) {
        if (!this.appRoot) return;

        if (this.pageTitleEl) {
            this.pageTitleEl.textContent = '404 - Not Found';
        }

        this.appRoot.innerHTML = `
            <div class="card text-center py-5">
                <h2 class="text-2xl font-bold mb-2">404 - Page Not Found</h2>
                <p class="text-muted mb-4">The route <code>#${path}</code> does not exist.</p>
                <a href="#/dashboard" class="btn btn-primary inline-block">Return to Dashboard</a>
            </div>
        `;
    }

    updateLayoutAuthVisibility(isAuthenticated) {
        const user = State.user;

        if (isAuthenticated && user) {
            // Apply sidebar permission visibility before revealing the sidebar
            this.applySidebarPermissions();

            this.sidebarEl?.classList?.remove('hidden');
            this.topbarEl?.classList?.remove('hidden');

            const userNameEl = document.getElementById('user-display-name');
            const userRoleEl = document.getElementById('user-display-role');
            const dropdownNameEl = document.getElementById('dropdown-user-name');
            const dropdownUsernameEl = document.getElementById('dropdown-user-username');
            const dropdownRoleEl = document.getElementById('dropdown-user-role');

            if (userNameEl) userNameEl.textContent = user.full_name || user.username || '';
            if (userRoleEl) userRoleEl.textContent = user.role || '';
            if (dropdownNameEl) dropdownNameEl.textContent = user.full_name || user.username || '';
            if (dropdownUsernameEl) dropdownUsernameEl.textContent = '@' + (user.username || 'user');
            if (dropdownRoleEl) dropdownRoleEl.textContent = user.role || '';
        } else {
            this.sidebarEl?.classList?.add('hidden');
            this.topbarEl?.classList?.add('hidden');
            document.getElementById('profile-dropdown-menu')?.classList?.add('hidden');
            this.applySidebarPermissions();
        }
    }

    /**
     * Null-safe helper to toggle visibility on a single selector without throwing if absent.
     */
    setElementVisibility(selector, isVisible) {
        if (typeof document === 'undefined' || typeof document.querySelector !== 'function') {
            return;
        }
        const el = document.querySelector(selector);
        if (!el) return;

        if (isVisible) {
            el.classList?.remove('hidden');
            if (el.style) {
                if (typeof el.style.removeProperty === 'function') {
                    el.style.removeProperty('display');
                } else {
                    el.style.display = '';
                }
            }
        } else {
            el.classList?.add('hidden');
            if (el.style) {
                if (typeof el.style.setProperty === 'function') {
                    el.style.setProperty('display', 'none');
                } else {
                    el.style.display = 'none';
                }
            }
        }
    }

    /**
     * Safely hide #nav-user-mgmt, #nav-audit-log, #nav-purchase-orders, and #nav-reports
     * when the user lacks Admin / Super Admin privileges, only after DOM is ready.
     */
    applySidebarPermissions() {
        try {
            if (typeof document === 'undefined') return;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.applySidebarPermissions(), { once: true });
                return;
            }

            const isAdmin = this.hasAdminPrivileges();
            const canPurchaseOrders = isAdmin && State.hasPermission('canManagePurchaseOrders');
            const canReports = isAdmin && State.hasPermission('canViewReports');
            const canAuditLog = isAdmin;
            const canUserMgmt = isAdmin;

            this.setElementVisibility('#nav-user-mgmt', canUserMgmt);
            this.setElementVisibility('#nav-audit-log', canAuditLog);
            this.setElementVisibility('#nav-purchase-orders', canPurchaseOrders);
            this.setElementVisibility('#nav-reports', canReports);
            this.setElementVisibility('#nav-section-reports', canReports || canAuditLog);
            this.setElementVisibility('#nav-section-admin', canUserMgmt);
        } catch (err) {
            console.warn('[RBAC] Safe sidebar visibility check skipped:', err);
        }
    }

    renderAlertBanner(alertData) {
        const container = document.getElementById('alert-banner-container');
        if (!container) return;

        if (!alertData) {
            container.innerHTML = '';
            container.classList?.add('hidden');
            return;
        }

        container.className = `alert-banner alert-${alertData.type}`;
        container.innerHTML = `
            <div class="flex justify-between items-center px-4 py-2 text-sm">
                <span>${alertData.message}</span>
                <button id="dismiss-alert-btn" class="close-btn text-muted font-bold ml-3">&times;</button>
            </div>
        `;
        container.classList?.remove('hidden');

        document.getElementById('dismiss-alert-btn')?.addEventListener('click', () => {
            State.clearAlert();
        });
    }
}

export const router = new Router();
