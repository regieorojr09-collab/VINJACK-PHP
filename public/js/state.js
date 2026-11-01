/**
 * Reactive Centralized State Store (Pub/Sub Pattern)
 * 
 * Manages user session, JWT token persistence, active routing, flash alerts,
 * and cached catalog lookup data.
 */
class StateStore {
    constructor() {
        this.listeners = new Map();

        // Safely restore authenticated session from localStorage
        let parsedUser = null;
        let savedToken = null;
        try {
            savedToken = localStorage.getItem('vinjack_jwt');
            const savedUser = localStorage.getItem('vinjack_user');
            parsedUser = savedUser ? JSON.parse(savedUser) : null;
        } catch (err) {
            console.warn('[State] Cleared corrupted session storage:', err);
            try {
                localStorage.removeItem('vinjack_jwt');
                localStorage.removeItem('vinjack_user');
            } catch (_) {}
        }

        this.token = savedToken || null;
        this.user = parsedUser;
        this.activeRoute = (typeof window !== 'undefined' && window.location?.hash) ? window.location.hash : '#/products';
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

        try {
            if (token) {
                localStorage.setItem('vinjack_jwt', token);
            }
            if (user) {
                localStorage.setItem('vinjack_user', JSON.stringify(user));
            }
        } catch (err) {
            console.warn('[State] Unable to persist session:', err);
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
        try {
            localStorage.removeItem('vinjack_jwt');
            localStorage.removeItem('vinjack_user');
        } catch (err) {
            console.warn('[State] Unable to clear storage:', err);
        }

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
     * Check whether the authenticated user is strictly a Super Admin.
     */
    isSuperAdmin() {
        return !!(this.isAuthenticated() && this.user && this.user.role === 'Super Admin');
    }

    /**
     * Check whether the authenticated user holds Admin or Super Admin privileges.
     */
    isAdminUser() {
        if (!this.isAuthenticated() || !this.user) return false;
        return this.user.role === 'Super Admin' || this.user.role === 'Admin';
    }

    hasPermission(permissionKey) {
        if (!this.user) return false;
        if (this.user.role === 'Super Admin') return true;
        const permissions = Array.isArray(this.user.permissions) ? this.user.permissions : [];
        return permissions.includes(permissionKey);
    }

    /**
     * Safely toggle visibility on RBAC-restricted sidebar elements only when present in DOM.
     */
    syncSidebarPermissions() {
        try {
            if (typeof document === 'undefined' || typeof document.querySelector !== 'function') {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.syncSidebarPermissions(), { once: true });
                return;
            }

            const toggleSafe = (selector, visible) => {
                const el = document.querySelector(selector);
                if (!el) return;
                if (visible) {
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
            };

            const isSuperAdmin = this.isSuperAdmin();

            toggleSafe('#nav-customers', isSuperAdmin);
            toggleSafe('#nav-reports', isSuperAdmin);
            toggleSafe('#nav-user-mgmt', isSuperAdmin);
            toggleSafe('#nav-audit-log', isSuperAdmin);
            toggleSafe('#nav-purchase-orders', isSuperAdmin);
            toggleSafe('#nav-suppliers', isSuperAdmin);
            toggleSafe('#nav-section-reports', isSuperAdmin);
            toggleSafe('#nav-section-admin', isSuperAdmin);
        } catch (err) {
            console.warn('[State] Safe sidebar sync skipped:', err);
        }
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
