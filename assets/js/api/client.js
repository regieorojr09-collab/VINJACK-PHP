import { State } from '../state.js';

const API_BASE = 'api/index.php?request=';

export const ApiClient = {
    async request(endpoint, options = {}) {
        const url = `${API_BASE}${endpoint}`;
        
        const headers = {
            'Content-Type': 'application/json',
            ...(options.headers || {})
        };

        if (State.token) {
            headers['Authorization'] = `Bearer ${State.token}`;
        }

        const config = {
            ...options,
            headers
        };

        if (config.body && typeof config.body === 'object') {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(url, config);
            
            // Check for XML response (specifically for receipt)
            const contentType = response.headers.get("content-type");
            
            if (contentType && contentType.includes("application/xml")) {
                const text = await response.text();
                if (!response.ok) throw new Error(text || 'XML Request failed');
                return text;
            }
            
            // Default to JSON
            const data = await response.json();

            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    if (endpoint !== 'auth/login' && endpoint !== 'auth/demo-login') {
                        State.clearUser();
                        window.location.hash = '#/login';
                    }
                }
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    get(endpoint, options = {}) {
        return this.request(endpoint, { ...options, method: 'GET' });
    },

    post(endpoint, body, options = {}) {
        return this.request(endpoint, { ...options, method: 'POST', body });
    },

    put(endpoint, body, options = {}) {
        return this.request(endpoint, { ...options, method: 'PUT', body });
    },

    delete(endpoint, options = {}) {
        return this.request(endpoint, { ...options, method: 'DELETE' });
    }
};
