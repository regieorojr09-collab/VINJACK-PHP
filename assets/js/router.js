import { State } from './state.js';
import { LoginPage } from './pages/login.js';
import { InventoryPage } from './pages/inventory.js';
import { SuppliersPage } from './pages/suppliers.js';
import { CustomersPage } from './pages/customers.js';
import { SalesPage } from './pages/sales.js';

const routes = {
    '/login': LoginPage,
    '/inventory': InventoryPage,
    '/suppliers': SuppliersPage,
    '/customers': CustomersPage,
    '/pos': SalesPage
};

export const Router = {
    init() {
        window.addEventListener('hashchange', () => this.handleRoute());
        this.handleRoute();
    },

    handleRoute() {
        const hash = window.location.hash || '#/';
        const path = hash.replace('#', '');
        
        // Redirect to login if not authenticated
        if (!State.isAuthenticated() && path !== '/login') {
            window.location.hash = '#/login';
            return;
        }

        // Redirect to POS if authenticated and at root
        if (State.isAuthenticated() && (path === '/' || path === '' || path === '/login')) {
            window.location.hash = '#/pos';
            return;
        }

        const component = routes[path];
        if (component) {
            this.updateNav(path);
            this.render(component);
        } else {
            this.render({
                render: () => `
                    <div class="card text-center">
                        <h2>404 - Not Found</h2>
                        <p class="mt-4">The page you are looking for does not exist.</p>
                        <a href="#/" class="btn btn-primary mt-4">Go Home</a>
                    </div>
                `,
                init: () => {}
            });
        }
    },

    updateNav(path) {
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.dataset.route === path) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    },

    render(component) {
        const main = document.getElementById('main-content');
        main.innerHTML = component.render();
        
        // Use setTimeout to ensure DOM is updated before init is called
        setTimeout(() => {
            if (component.init) component.init();
        }, 0);
    }
};
