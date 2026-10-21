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

    hasPermission(permissionKey) {
        if (!this.user) return false;
        if (this.user.role === 'Super Admin') return true;
        const permissions = this.user.permissions || [];
        return permissions.includes(permissionKey);
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
