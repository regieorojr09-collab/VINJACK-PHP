import { State } from './state.js';
import { LoginPage } from './views/login.js';
import { DashboardPage } from './views/dashboard.js';
import { ReportsPage } from './views/reports.js';
import { ProductsPage } from './views/products.js';
import { SuppliersPage } from './views/suppliers.js';
import { POSPage } from './views/pos.js';
import { PurchaseOrdersPage } from './views/purchase_orders.js';
import { MovementsPage } from './views/movements.js';
import { ReturnsPage } from './views/returns.js';
import { ConsignmentPage } from './views/consignment.js';

/**
 * Client-Side Hash Router
 * 
 * Manages view transitions, authentication guards, and active navigation indicators.
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
            '/returns':         { view: ReturnsPage,        title: 'Returns & Warranty Claims' },
            '/consignment':     { view: ConsignmentPage,    title: 'Consignment Payables & Settlements' },
            '/suppliers':       { view: SuppliersPage,      title: 'Suppliers Directory' },
            '/purchase-orders': { view: PurchaseOrdersPage, title: 'Purchase Orders & Procurement' },
            '/purchase_orders': { view: PurchaseOrdersPage, title: 'Purchase Orders & Procurement' }
        };

        this.appRoot = null;
        this.pageTitleEl = null;
        this.sidebarEl = null;
        this.topbarEl = null;
    }

    init() {
        this.appRoot = document.getElementById('app-root');
        this.pageTitleEl = document.getElementById('current-page-title');
        this.sidebarEl = document.getElementById('app-sidebar');
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

        // Setup Logout button
        document.getElementById('logout-btn')?.addEventListener('click', () => {
            State.clearUser();
            State.setAlert('You have been signed out.', 'info');
        });

        // Initialize UI visibility based on current session
        this.updateLayoutAuthVisibility(State.isAuthenticated());

        // Initial Route Dispatch
        this.handleRoute();
    }

    handleRoute() {
        const hash = window.location.hash || '#/dashboard';
        const path = hash.replace(/^#/, '');

        // Auth Guard
        if (!State.isAuthenticated() && path !== '/login') {
            window.location.hash = '#/login';
            return;
        }

        if (State.isAuthenticated() && (path === '/login' || path === '/' || path === '')) {
            window.location.hash = '#/dashboard';
            return;
        }

        const route = this.routes[path];
        if (route) {
            this.renderView(route.view, route.title, path);
        } else {
            this.render404(path);
        }
    }

    renderView(view, title, path) {
        if (!this.appRoot) return;

        // Update active navigation item in sidebar
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === `#${path}`) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Update Topbar Title
        if (this.pageTitleEl) {
            this.pageTitleEl.textContent = title;
        }

        // Render Component HTML
        this.appRoot.innerHTML = view.render();

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
            this.sidebarEl?.classList.remove('hidden');
            this.topbarEl?.classList.remove('hidden');

            const userNameEl = document.getElementById('user-display-name');
            const userRoleEl = document.getElementById('user-display-role');
            if (userNameEl) userNameEl.textContent = user.full_name || user.username;
            if (userRoleEl) userRoleEl.textContent = user.role;
        } else {
            this.sidebarEl?.classList.add('hidden');
            this.topbarEl?.classList.add('hidden');
        }
    }

    renderAlertBanner(alertData) {
        const container = document.getElementById('alert-banner-container');
        if (!container) return;

        if (!alertData) {
            container.innerHTML = '';
            container.classList.add('hidden');
            return;
        }

        container.className = `alert-banner alert-${alertData.type}`;
        container.innerHTML = `
            <div class="flex justify-between items-center px-4 py-2 text-sm">
                <span>${alertData.message}</span>
                <button id="dismiss-alert-btn" class="close-btn text-muted font-bold ml-3">&times;</button>
            </div>
        `;
        container.classList.remove('hidden');

        document.getElementById('dismiss-alert-btn')?.addEventListener('click', () => {
            State.clearAlert();
        });
    }
}

export const router = new Router();
