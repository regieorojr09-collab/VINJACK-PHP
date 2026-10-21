export const State = {
    events: {},
    
    user: JSON.parse(localStorage.getItem('vinjack_user')) || null,
    token: localStorage.getItem('vinjack_token') || null,

    subscribe(event, callback) {
        if (!this.events[event]) {
            this.events[event] = [];
        }
        this.events[event].push(callback);
    },

    publish(event, data) {
        if (this.events[event]) {
            this.events[event].forEach(callback => callback(data));
        }
    },

    setUser(user, token) {
        this.user = user;
        this.token = token;
        localStorage.setItem('vinjack_user', JSON.stringify(user));
        localStorage.setItem('vinjack_token', token);
        this.publish('auth:changed', this.isAuthenticated());
    },

    clearUser() {
        this.user = null;
        this.token = null;
        localStorage.removeItem('vinjack_user');
        localStorage.removeItem('vinjack_token');
        this.publish('auth:changed', false);
    },

    isAuthenticated() {
        return !!this.token;
    },
    
    hasPermission(permission) {
        if (!this.user) return false;
        if (this.user.role === 'Super Admin') return true;
        return this.user.permissions && this.user.permissions.includes(permission);
    }
};
