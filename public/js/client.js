import { State } from './state.js';

/**
 * Universal Native Fetch API Wrapper
 * 
 * Auto-injects JWT Authorization headers, intercepts 401/403 responses,
 * and handles both JSON and XML data interchange payloads.
 */
class ApiClient {
    constructor() {
        this.base = this.resolveBaseUrl();
    }

    resolveBaseUrl() {
        const path = window.location.pathname;
        if (path.includes('/public')) {
            return '../api/index.php?request=';
        }
        return 'api/index.php?request=';
    }

    async request(endpoint, options = {}) {
        // Normalize endpoint
        const cleanEndpoint = endpoint.replace(/^\/+/, '');
        const url = `${this.base}${cleanEndpoint}`;

        const headers = {
            'Accept': 'application/json, application/xml, text/plain, */*',
            ...(options.headers || {})
        };

        // Attach Bearer token if session exists
        const token = State.token;
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        // Set JSON Content-Type if body is an object and not FormData
        let body = options.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }

        const config = {
            ...options,
            headers,
            body
        };

        try {
            const response = await fetch(url, config);

            // Handle Unauthorized (401)
            if (response.status === 401) {
                State.clearUser();
                State.setAlert('Session expired or unauthorized. Please sign in.', 'warning');
                window.location.hash = '#/login';
                throw new Error('Unauthorized');
            }

            // Handle Forbidden (403)
            if (response.status === 403) {
                const errData = await response.json().catch(() => ({}));
                const msg = errData.message || 'Forbidden: Insufficient permissions.';
                State.setAlert(msg, 'danger');
                throw new Error(msg);
            }

            const contentType = response.headers.get('content-type') || '';

            // Handle XML Responses (e.g. receipts, PO XML exports)
            if (contentType.includes('application/xml') || contentType.includes('text/xml')) {
                const xmlText = await response.text();
                if (!response.ok) {
                    throw new Error(xmlText || `HTTP ${response.status} XML Request Failed`);
                }
                return xmlText;
            }

            // Default to JSON
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data.message || `Request failed with status ${response.status}`;
                throw new Error(message);
            }

            return data;
        } catch (error) {
            if (error.message !== 'Unauthorized') {
                console.error(`[ApiClient Error: ${cleanEndpoint}]`, error);
            }
            throw error;
        }
    }

    get(endpoint, params = {}) {
        let qs = '';
        if (params && Object.keys(params).length > 0) {
            const searchParams = new URLSearchParams();
            for (const [key, val] of Object.entries(params)) {
                if (val !== null && val !== undefined && val !== '') {
                    searchParams.append(key, val);
                }
            }
            const str = searchParams.toString();
            if (str) qs = (endpoint.includes('?') ? '&' : '&') + str;
        }
        return this.request(`${endpoint}${qs}`, { method: 'GET' });
    }

    post(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body
        });
    }

    put(endpoint, body = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body
        });
    }

    delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
}

export const apiClient = new ApiClient();
export { apiClient as ApiClient };
