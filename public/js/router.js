import { State } from './state.js';
import { LoginPage } from './views/login.js?v=20261101k';
import { DashboardPage } from './views/dashboard.js?v=20261101k';
import { ReportsPage } from './views/reports.js?v=20261101k';
import { ProductsPage } from './views/products.js?v=20261101k';
import { SuppliersPage } from './views/suppliers.js?v=20261101k';
import { POSPage } from './views/pos.js?v=20261101k';
import { PurchaseOrdersPage } from './views/purchase_orders.js?v=20261101k';
import { MovementsPage } from './views/movements.js?v=20261101k';
import { UsersPage } from './views/users.js?v=20261101k';
import { CustomersPage } from './views/customers.js?v=20261101k';
import { AuditLogsPage } from './views/audit_logs.js?v=20261101k';

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

        // Strict role-separated navigation allowlists
        this.roleAllowedRoutes = {
            Salesperson: new Set([
                '/pos',
                '/sales',
                '/products',
                '/inventory',
                '/customers'
            ]),
            Admin: new Set([
                '/dashboard',
                '/pos',
                '/sales',
                '/customers',
                '/products',
                '/inventory',
                '/reports',
                '/analytics',
                '/purchase-orders',
                '/purchase_orders',
                '/suppliers'
            ])
        };

        this.appRoot = null;
        this.pageTitleEl = null;
        this.sidebarEl = null;
        this.topbarEl = null;
        this.pendingViewInitTimer = null;
    }

    /**
     * Resolve default landing hash route per role:
     * - Super Admin -> #/dashboard
     * - Admin (Mechanic) -> #/dashboard
     * - Salesperson (Clerk) -> #/pos
     */
    getDefaultRoute() {
        if (!State || typeof State.isAuthenticated !== 'function' || !State.isAuthenticated()) {
            return '#/login';
        }
        const role = State.user?.role || '';
        return (role === 'Super Admin' || role === 'Admin') ? '#/dashboard' : '#/pos';
    }

    /**
     * Self-contained check strictly for Super Admin role.
     */
    hasSuperAdminPrivileges() {
        if (!State || typeof State.isAuthenticated !== 'function' || !State.isAuthenticated()) {
            return false;
        }
        return (State.user?.role || '') === 'Super Admin';
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
            } else if (window.location.hash === '#/login' || window.location.hash === '' || window.location.hash === '#/') {
                window.location.hash = this.getDefaultRoute();
            } else {
                this.handleRoute();
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
        const defaultHash = this.getDefaultRoute();
        const hash = window.location.hash || defaultHash;
        const path = hash.replace(/^#/, '');

        // Enforce sidebar permission guards safely on every route evaluation
        this.applySidebarPermissions();

        // Auth Guard
        if (!State.isAuthenticated() && path !== '/login') {
            window.location.hash = '#/login';
            return;
        }

        if (State.isAuthenticated() && (path === '/login' || path === '/' || path === '')) {
            if (window.location.hash !== defaultHash) {
                window.location.hash = defaultHash;
            } else {
                const fallbackPath = defaultHash.replace(/^#/, '');
                const fallbackRoute = this.routes[fallbackPath];
                if (fallbackRoute) {
                    this.renderView(fallbackRoute.view, fallbackRoute.title, fallbackPath);
                }
            }
            return;
        }

        // Strict Role-Separated RBAC Route Guard
        if (State.isAuthenticated()) {
            const role = State.user?.role || '';
            if (role !== 'Super Admin' && this.routes[path]) {
                const allowedSet = this.roleAllowedRoutes[role];
                if (!allowedSet || !allowedSet.has(path)) {
                    State.setAlert('403 Forbidden: Access Denied', 'danger');
                    if (window.location.hash !== defaultHash) {
                        window.location.hash = defaultHash;
                    } else {
                        const fallbackPath = defaultHash.replace(/^#/, '');
                        const fallbackRoute = this.routes[fallbackPath];
                        if (fallbackRoute) {
                            this.renderView(fallbackRoute.view, fallbackRoute.title, fallbackPath);
                        }
                    }
                    return;
                }
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

        // Lifecycle hook (cancel any superseded view init timer on rapid redirects)
        if (this.pendingViewInitTimer) {
            clearTimeout(this.pendingViewInitTimer);
        }
        this.pendingViewInitTimer = setTimeout(() => {
            this.pendingViewInitTimer = null;
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

        const homeHash = this.getDefaultRoute();
        this.appRoot.innerHTML = `
            <div class="card text-center py-5">
                <h2 class="text-2xl font-bold mb-2">404 - Page Not Found</h2>
                <p class="text-muted mb-4">The route <code>#${path}</code> does not exist.</p>
                <a href="${homeHash}" class="btn btn-primary inline-block">Return Home</a>
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

            const isAuth = Boolean(State && typeof State.isAuthenticated === 'function' && State.isAuthenticated());
            const role = isAuth ? (State.user?.role || '') : '';
            const isSuperAdmin = role === 'Super Admin';
            const isAdmin = role === 'Admin';
            const isSalesperson = role === 'Salesperson';

            this.setElementVisibility('#nav-dashboard', isSuperAdmin || isAdmin);
            this.setElementVisibility('#nav-pos', isAuth && (isSuperAdmin || isAdmin || isSalesperson));
            this.setElementVisibility('#nav-customers', isAuth && (isSuperAdmin || isAdmin || isSalesperson));
            this.setElementVisibility('#nav-products', isAuth && (isSuperAdmin || isAdmin || isSalesperson));
            this.setElementVisibility('#nav-purchase-orders', isSuperAdmin || isAdmin);
            this.setElementVisibility('#nav-suppliers', isSuperAdmin || isAdmin);
            this.setElementVisibility('#nav-reports', isSuperAdmin || isAdmin);
            this.setElementVisibility('#nav-audit-log', isSuperAdmin);
            this.setElementVisibility('#nav-user-mgmt', isSuperAdmin);
            this.setElementVisibility('#nav-section-reports', isSuperAdmin || isAdmin);
            this.setElementVisibility('#nav-section-admin', isSuperAdmin);
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
