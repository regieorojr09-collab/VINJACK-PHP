/**
 * Reactive Centralized State Store (Pub/Sub Pattern)
 * 
 * Manages user session, JWT token persistence, active routing, flash alerts,
 * and cached catalog lookup data.
 */
class StateStore {
    constructor() {
        this.listeners = new Map();

        // Restore authenticated session from localStorage
        const savedToken = localStorage.getItem('vinjack_jwt');
        const savedUser = localStorage.getItem('vinjack_user');

        this.token = savedToken || null;
        this.user = savedUser ? JSON.parse(savedUser) : null;
        this.activeRoute = window.location.hash || '#/products';
        this.flashAlert = null;
        this.categories = [];
        this.brands = [];
    }

    /**
     * Subscribe to state event changes.
     *
     * @param {string} event Event name (e.g. 'auth:changed', 'alert:changed')
     * @param {Function} callback Handler function
     * @return {Function} Unsubscribe handle
     */
    subscribe(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        this.listeners.get(event).add(callback);

        return () => {
            this.listeners.get(event).delete(callback);
        };
    }

    /**
     * Publish an event to all subscribers.
     */
    publish(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(cb => {
                try {
                    cb(data);
                } catch (err) {
                    console.error(`[State Error on ${event}]`, err);
                }
            });
        }
    }

    /**
     * Save active user session and persist JWT.
     */
    setUser(user, token) {
        this.user = user;
        this.token = token;

        if (token) {
            localStorage.setItem('vinjack_jwt', token);
        }
        if (user) {
            localStorage.setItem('vinjack_user', JSON.stringify(user));
        }

        this.syncSidebarPermissions();
        this.publish('auth:changed', true);
    }

    /**
     * Clear user session and purge storage.
     */
    clearUser() {
        this.user = null;
        this.token = null;
        localStorage.removeItem('vinjack_jwt');
        localStorage.removeItem('vinjack_user');

        this.syncSidebarPermissions();
        this.publish('auth:changed', false);
    }

    isAuthenticated() {
        return !!(this.token && this.user);
    }

    hasRole(requiredRole) {
        if (!this.user) return false;
        if (this.user.role === 'Super Admin') return true;
        return this.user.role === requiredRole;
    }

    /**
     * Check whether the authenticated user holds Admin or Super Admin privileges
     * (strictly false for Salesperson / Clerk accounts).
     */
    isAdminUser() {
        if (!this.isAuthenticated() || !this.user) return false;
        return this.user.role === 'Super Admin' || this.user.role === 'Admin';
    }

    hasPermission(permissionKey) {
        if (!this.user) return false;
        if (this.user.role === 'Super Admin') return true;
        const permissions = this.user.permissions || [];
        return permissions.includes(permissionKey);
    }

    /**
     * Conditionally hide or show RBAC-restricted sidebar navigation modules
     * (#nav-user-mgmt, #nav-audit-log, #nav-purchase-orders, #nav-reports)
     * whenever the user logs in, logs out, or refreshes the page.
     */
    syncSidebarPermissions() {
        if (typeof document === 'undefined') return;

        const toggleEl = (id, visible) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (!visible) {
                el.classList.add('hidden');
                el.style.display = 'none';
            } else {
                el.classList.remove('hidden');
                el.style.display = '';
            }
        };

        const isAdmin = this.isAdminUser();
        const canPurchaseOrders = isAdmin && this.hasPermission('canManagePurchaseOrders');
        const canReports = isAdmin && this.hasPermission('canViewReports');
        const canAuditLog = isAdmin;
        const canUserMgmt = isAdmin;

        toggleEl('nav-purchase-orders', canPurchaseOrders);
        toggleEl('nav-reports', canReports);
        toggleEl('nav-audit-log', canAuditLog);
        toggleEl('nav-user-mgmt', canUserMgmt);
        toggleEl('nav-section-reports', canReports || canAuditLog);
        toggleEl('nav-section-admin', canUserMgmt);
    }

    /**
     * Set a temporary flash alert banner across the UI.
     *
     * @param {string} message 
     * @param {'success'|'danger'|'warning'|'info'} type 
     * @param {number} durationMs 
     */
    setAlert(message, type = 'info', durationMs = 5000) {
        this.flashAlert = { message, type };
        this.publish('alert:changed', this.flashAlert);

        if (this.alertTimer) {
            clearTimeout(this.alertTimer);
        }

        if (durationMs > 0) {
            this.alertTimer = setTimeout(() => {
                this.clearAlert();
            }, durationMs);
        }
    }

    clearAlert() {
        this.flashAlert = null;
        this.publish('alert:changed', null);
    }

    setCategories(categories) {
        this.categories = categories;
        this.publish('categories:changed', categories);
    }

    setBrands(brands) {
        this.brands = brands;
        this.publish('brands:changed', brands);
    }
}

export const State = new StateStore();
