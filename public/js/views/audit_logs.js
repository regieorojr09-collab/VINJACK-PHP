import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * System Audit Trail View
 * 
 * Displays an immutable, timestamped ledger of administrative actions,
 * user authentication events, procurement authorizations, and stock alterations.
 */
export const AuditLogsPage = {
    state: {
        logs: [],
        filters: {
            search: '',
            action: '',
            entityType: ''
        },
        loading: false
    },

    render() {
        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">System Audit Trail</h1>
                        <p class="text-muted text-sm">Immutable chronicle of system logins, inventory movements, procurement approvals, and pricing changes.</p>
                    </div>
                    <div class="flex gap-2">
                        <button id="refresh-audit-btn" class="btn btn-secondary btn-sm">
                            🔄 Refresh Logs
                        </button>
                    </div>
                </div>

                <!-- Metrics Ribbon -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card">
                        <span class="metric-label">Total Logged Events</span>
                        <div class="metric-value font-mono" id="metric-total-events">0</div>
                    </div>
                    <div class="metric-card border-blue">
                        <span class="metric-label">Authentication Events</span>
                        <div class="metric-value font-mono text-primary" id="metric-auth-events">0</div>
                    </div>
                    <div class="metric-card border-purple">
                        <span class="metric-label">Procurement & POs</span>
                        <div class="metric-value font-mono text-purple" id="metric-po-events">0</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Inventory & Adjustments</span>
                        <div class="metric-value font-mono text-success" id="metric-inv-events">0</div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-3 gap-3 items-center">
                        <div>
                            <input type="text" id="filter-audit-search" class="form-control" placeholder="🔍 Search action, details, user, or entity..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-audit-action" class="form-control">
                                <option value="">All Actions</option>
                                <option value="USER_LOGIN">User Logins</option>
                                <option value="PURCHASE_ORDER_CREATED">PO Created</option>
                                <option value="PURCHASE_ORDER_APPROVED">PO Approved</option>
                                <option value="STOCK_DELIVERY_RECEIVED">Stock Delivery</option>
                                <option value="INVENTORY_ADJUSTMENT">Inventory Adjustment</option>
                                <option value="PRICE_UPDATED">Price Updated</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <select id="filter-audit-entity" class="form-control">
                                <option value="">All Entity Types</option>
                                <option value="Auth">Auth & Sessions</option>
                                <option value="PurchaseOrder">Purchase Orders</option>
                                <option value="Delivery">Deliveries</option>
                                <option value="Inventory">Inventory Items</option>
                                <option value="Product">Product Catalog</option>
                            </select>
                            <button id="clear-audit-filters-btn" class="btn btn-secondary" title="Clear Filters">Clear</button>
                        </div>
                    </div>
                </div>

                <!-- Audit Log Ledger Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="audit-logs-table">
                            <thead>
                                <tr>
                                    <th style="width: 175px;">Timestamp</th>
                                    <th style="width: 190px;">Actor / User</th>
                                    <th style="width: 200px;">Action</th>
                                    <th>Event Details & Context</th>
                                    <th style="width: 160px;">Target Entity</th>
                                </tr>
                            </thead>
                            <tbody id="audit-logs-tbody">
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">Loading audit log entries...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await this.loadAuditLogs();
    },

    bindEvents() {
        // Search input with debounce
        let searchTimer;
        document.getElementById('filter-audit-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.loadAuditLogs();
            }, 250);
        });

        // Action filter
        document.getElementById('filter-audit-action')?.addEventListener('change', (e) => {
            this.state.filters.action = e.target.value;
            this.loadAuditLogs();
        });

        // Entity filter
        document.getElementById('filter-audit-entity')?.addEventListener('change', (e) => {
            this.state.filters.entityType = e.target.value;
            this.loadAuditLogs();
        });

        // Clear filters
        document.getElementById('clear-audit-filters-btn')?.addEventListener('click', () => {
            this.state.filters = { search: '', action: '', entityType: '' };
            const sInput = document.getElementById('filter-audit-search');
            const aInput = document.getElementById('filter-audit-action');
            const eInput = document.getElementById('filter-audit-entity');
            if (sInput) sInput.value = '';
            if (aInput) aInput.value = '';
            if (eInput) eInput.value = '';
            this.loadAuditLogs();
        });

        // Refresh button
        document.getElementById('refresh-audit-btn')?.addEventListener('click', () => {
            this.loadAuditLogs();
        });
    },

    async loadAuditLogs() {
        this.state.loading = true;
        const tbody = document.getElementById('audit-logs-tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-5 text-muted">Refreshing audit trail...</td></tr>`;
        }

        try {
            const params = {};
            if (this.state.filters.search) params.search = this.state.filters.search;
            if (this.state.filters.action) params.action = this.state.filters.action;
            if (this.state.filters.entityType) params.entity_type = this.state.filters.entityType;

            const res = await apiClient.get('audit-logs', params);
            if (res.success && Array.isArray(res.data)) {
                this.state.logs = res.data;
            } else {
                this.state.logs = [];
            }
        } catch (err) {
            console.error('Failed to load audit logs:', err);
            State.setAlert('Unable to load audit logs: ' + (err.message || 'Unknown error'), 'danger', 4000);
            this.state.logs = [];
        } finally {
            this.state.loading = false;
            this.renderTable();
            this.updateMetrics();
        }
    },

    renderTable() {
        const tbody = document.getElementById('audit-logs-tbody');
        if (!tbody) return;

        if (this.state.logs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        No audit events found matching the specified criteria.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.logs.map(log => {
            const dateStr = this.formatDate(log.created_at);
            const actionBadge = this.getActionBadge(log.action);
            const roleBadge = this.getRoleBadge(log.user_role);

            const entityText = log.entity_type 
                ? `<span class="font-mono text-xs font-semibold px-2 py-1 rounded bg-surface border">${log.entity_type}${log.entity_id ? ' #' + log.entity_id : ''}</span>`
                : '<span class="text-muted text-xs">—</span>';

            return `
                <tr>
                    <td class="font-mono text-xs whitespace-nowrap text-muted">${dateStr}</td>
                    <td>
                        <div class="font-semibold text-sm">${this.escapeHtml(log.user_name || 'System')}</div>
                        <div class="text-xs text-muted flex items-center gap-1">
                            <span>@${this.escapeHtml(log.username || 'system')}</span>
                            ${roleBadge}
                        </div>
                    </td>
                    <td>${actionBadge}</td>
                    <td class="text-sm">${this.escapeHtml(log.details || '')}</td>
                    <td>${entityText}</td>
                </tr>
            `;
        }).join('');
    },

    updateMetrics() {
        const total = this.state.logs.length;
        const auth = this.state.logs.filter(l => l.action.includes('LOGIN') || l.entity_type === 'Auth').length;
        const po = this.state.logs.filter(l => l.action.includes('PURCHASE') || l.entity_type === 'PurchaseOrder').length;
        const inv = this.state.logs.filter(l => l.action.includes('INVENTORY') || l.action.includes('STOCK') || l.action.includes('DELIVERY') || l.action.includes('PRICE')).length;

        const totalEl = document.getElementById('metric-total-events');
        const authEl = document.getElementById('metric-auth-events');
        const poEl = document.getElementById('metric-po-events');
        const invEl = document.getElementById('metric-inv-events');

        if (totalEl) totalEl.textContent = total;
        if (authEl) authEl.textContent = auth;
        if (poEl) poEl.textContent = po;
        if (invEl) invEl.textContent = inv;
    },

    getActionBadge(action) {
        if (!action) return `<span class="badge badge-secondary">UNKNOWN</span>`;
        const a = String(action).toUpperCase();

        if (a.includes('LOGIN') || a.includes('AUTH')) {
            return `<span class="badge badge-info font-mono text-xs">${a}</span>`;
        }
        if (a.includes('APPROVED') || a.includes('DELIVERY') || a.includes('RECEIVED')) {
            return `<span class="badge badge-success font-mono text-xs">${a}</span>`;
        }
        if (a.includes('CREATED') || a.includes('ADD')) {
            return `<span class="badge badge-primary font-mono text-xs">${a}</span>`;
        }
        if (a.includes('ADJUST') || a.includes('UPDATE') || a.includes('PRICE')) {
            return `<span class="badge badge-warning font-mono text-xs">${a}</span>`;
        }
        if (a.includes('DELETE') || a.includes('REJECT') || a.includes('LOCK')) {
            return `<span class="badge badge-danger font-mono text-xs">${a}</span>`;
        }
        return `<span class="badge badge-secondary font-mono text-xs">${a}</span>`;
    },

    getRoleBadge(role) {
        if (role === 'Super Admin') {
            return `<span class="badge badge-purple" style="font-size: 0.65rem;">Super Admin</span>`;
        }
        if (role === 'Admin') {
            return `<span class="badge badge-primary" style="font-size: 0.65rem;">Admin</span>`;
        }
        if (role === 'Salesperson') {
            return `<span class="badge badge-secondary" style="font-size: 0.65rem;">Salesperson</span>`;
        }
        return '';
    },

    formatDate(dateString) {
        if (!dateString) return '—';
        try {
            const d = new Date(dateString);
            if (isNaN(d.getTime())) return dateString;
            return d.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        } catch (e) {
            return dateString;
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};
