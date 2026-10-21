import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Stock Movement Ledger View
 * 
 * Displays immutable audit history for all inventory events and allows
 * authorized stock count adjustments.
 */
export const MovementsPage = {
    state: {
        movements: [],
        products: [],
        metrics: null,
        filters: {
            type: '',
            search: '',
            start_date: '',
            end_date: ''
        },
        selectedProduct: null
    },

    render() {
        const canManage = State.hasPermission('canManageInventory');

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Stock Movement Ledger</h1>
                        <p class="text-muted text-sm">Immutable audit trail of all inventory transactions, PO deliveries, returns, and physical adjustments.</p>
                    </div>
                    ${canManage ? `
                        <button id="open-adjust-modal-btn" class="btn btn-primary">
                            <span>⚖️</span> Adjust Stock Count
                        </button>
                    ` : ''}
                </div>

                <!-- Metrics Overview Cards -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card">
                        <span class="metric-label">Total Ledger Entries</span>
                        <div class="metric-value font-mono" id="metric-total-movements">0</div>
                    </div>
                    <div class="metric-card border-warning">
                        <span class="metric-label">Physical Adjustments</span>
                        <div class="metric-value text-warning font-mono" id="metric-total-adjustments">0</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Total Units Received</span>
                        <div class="metric-value text-success font-mono" id="metric-units-received">+0</div>
                    </div>
                    <div class="metric-card border-danger">
                        <span class="metric-label">Total Units Dispensed</span>
                        <div class="metric-value text-danger font-mono" id="metric-units-dispensed">-0</div>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-4 gap-3 items-center">
                        <div>
                            <input type="text" id="filter-mov-search" class="form-control" placeholder="🔍 Search product, SKU, reference #, or note..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-mov-type" class="form-control">
                                <option value="">All Movement Types</option>
                                <option value="SALE">SALE (POS Outflow)</option>
                                <option value="DELIVERY (PO)">DELIVERY (PO Receipt)</option>
                                <option value="ADJUSTMENT">ADJUSTMENT (Count Reconciliation)</option>
                                <option value="RETURN">RETURN (Customer / Supplier)</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <input type="date" id="filter-mov-start" class="form-control" title="Start Date">
                            <input type="date" id="filter-mov-end" class="form-control" title="End Date">
                        </div>
                        <div class="flex justify-end gap-2">
                            <button id="reset-mov-filters-btn" class="btn btn-secondary btn-sm">Reset</button>
                            <button id="refresh-mov-btn" class="btn btn-primary btn-sm">Apply & Refresh</button>
                        </div>
                    </div>
                </div>

                <!-- Ledger Table Card -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="movements-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Reference #</th>
                                    <th>Product / SKU</th>
                                    <th class="text-center">Event Type</th>
                                    <th class="text-center">Stock Delta</th>
                                    <th class="text-center">Before ➔ After</th>
                                    <th>Audit Note / Reason</th>
                                    <th class="text-right">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody id="movements-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">Loading stock ledger movements...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Stock Adjustment Modal -->
            <div id="adjust-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div>
                            <h2 class="text-lg font-bold">Physical Stock Adjustment</h2>
                            <p class="text-xs text-muted">Reconcile physical inventory counts and log audit reasons</p>
                        </div>
                        <button id="close-adjust-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="adjust-form" class="mt-3">
                        <div id="adjust-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="form-group mb-3">
                            <label for="adj-product-select" class="text-xs font-semibold text-muted block mb-1">Target Product / Item *</label>
                            <select id="adj-product-select" class="form-control" required>
                                <option value="">Select a catalog product...</option>
                            </select>
                        </div>

                        <!-- Current vs New Stock Comparison -->
                        <div class="grid grid-cols-2 gap-3 p-3 bg-surface rounded mb-3">
                            <div>
                                <span class="text-xs uppercase text-muted font-bold block mb-1">Recorded Current Stock</span>
                                <span class="text-2xl font-bold font-mono" id="adj-current-stock">—</span>
                            </div>
                            <div>
                                <span class="text-xs uppercase text-muted font-bold block mb-1">Calculated Delta</span>
                                <span class="text-2xl font-bold font-mono text-muted" id="adj-delta-display">—</span>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="adj-new-qty" class="text-xs font-semibold text-muted block mb-1">Actual Physical Count Verified *</label>
                            <input type="number" step="1" min="0" id="adj-new-qty" class="form-control font-mono font-bold text-lg" placeholder="Enter physical stock count" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="adj-notes" class="text-xs font-semibold text-muted block mb-1">Audit Reconciliation Reason *</label>
                            <textarea id="adj-notes" class="form-control" rows="2" placeholder="e.g. Damaged during handling, monthly stock count variance, expired stock write-off" required></textarea>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-adjust-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-adjust-btn" class="btn btn-primary">
                                ⚖️ Commit Adjustment to Ledger
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await Promise.all([
            this.loadMetrics(),
            this.loadMovements(),
            this.loadProducts()
        ]);
    },

    bindEvents() {
        // Filter changes
        document.getElementById('filter-mov-type')?.addEventListener('change', (e) => {
            this.state.filters.type = e.target.value;
            this.loadMovements();
        });

        let searchTimer;
        document.getElementById('filter-mov-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.loadMovements();
            }, 300);
        });

        document.getElementById('refresh-mov-btn')?.addEventListener('click', () => {
            this.state.filters.start_date = document.getElementById('filter-mov-start')?.value || '';
            this.state.filters.end_date = document.getElementById('filter-mov-end')?.value || '';
            this.loadMovements();
            this.loadMetrics();
        });

        document.getElementById('reset-mov-filters-btn')?.addEventListener('click', () => {
            this.state.filters = { type: '', search: '', start_date: '', end_date: '' };
            const sInput = document.getElementById('filter-mov-search');
            const tSelect = document.getElementById('filter-mov-type');
            const startD = document.getElementById('filter-mov-start');
            const endD = document.getElementById('filter-mov-end');
            if (sInput) sInput.value = '';
            if (tSelect) tSelect.value = '';
            if (startD) startD.value = '';
            if (endD) endD.value = '';
            this.loadMovements();
        });

        // Adjustment Modal
        document.getElementById('open-adjust-modal-btn')?.addEventListener('click', () => {
            this.openAdjustModal();
        });

        document.getElementById('close-adjust-modal-btn')?.addEventListener('click', () => {
            document.getElementById('adjust-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-adjust-btn')?.addEventListener('click', () => {
            document.getElementById('adjust-modal')?.classList.add('hidden');
        });

        // Product selection in adjustment modal
        document.getElementById('adj-product-select')?.addEventListener('change', (e) => {
            const prodId = parseInt(e.target.value, 10);
            const prod = this.state.products.find(p => p.id === prodId);
            this.state.selectedProduct = prod || null;

            const currentEl = document.getElementById('adj-current-stock');
            const newQtyInput = document.getElementById('adj-new-qty');

            if (prod) {
                currentEl.textContent = `${prod.quantity} ${prod.unit || 'pc'}`;
                newQtyInput.value = prod.quantity;
            } else {
                currentEl.textContent = '—';
                newQtyInput.value = '';
            }
            this.recalculateDelta();
        });

        // New qty input triggers delta recompute
        document.getElementById('adj-new-qty')?.addEventListener('input', () => {
            this.recalculateDelta();
        });

        // Submit Adjustment Form
        document.getElementById('adjust-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleCommitAdjustment();
        });
    },

    recalculateDelta() {
        const prod = this.state.selectedProduct;
        const deltaEl = document.getElementById('adj-delta-display');
        const newQtyVal = document.getElementById('adj-new-qty').value;

        if (!prod || newQtyVal === '') {
            deltaEl.textContent = '—';
            deltaEl.className = 'text-2xl font-bold font-mono text-muted';
            return;
        }

        const currentQty = parseInt(prod.quantity, 10);
        const newQty = parseInt(newQtyVal, 10);
        const delta = newQty - currentQty;

        if (delta > 0) {
            deltaEl.textContent = `+${delta} ${prod.unit || 'pc'}`;
            deltaEl.className = 'text-2xl font-bold font-mono text-success';
        } else if (delta < 0) {
            deltaEl.textContent = `${delta} ${prod.unit || 'pc'}`;
            deltaEl.className = 'text-2xl font-bold font-mono text-danger';
        } else {
            deltaEl.textContent = `0 (No change)`;
            deltaEl.className = 'text-2xl font-bold font-mono text-muted';
        }
    },

    async loadMetrics() {
        try {
            const res = await apiClient.get('movements/metrics');
            if (res.success && res.data) {
                this.state.metrics = res.data;
                const m = res.data;
                const elTot = document.getElementById('metric-total-movements');
                const elAdj = document.getElementById('metric-total-adjustments');
                const elRec = document.getElementById('metric-units-received');
                const elDis = document.getElementById('metric-units-dispensed');

                if (elTot) elTot.textContent = m.total_movements || 0;
                if (elAdj) elAdj.textContent = m.total_adjustments || 0;
                if (elRec) elRec.textContent = `+${parseInt(m.units_received || 0, 10).toLocaleString()}`;
                if (elDis) elDis.textContent = `-${parseInt(m.units_dispensed || 0, 10).toLocaleString()}`;
            }
        } catch (err) {
            console.error('Failed to load movement metrics', err);
        }
    },

    async loadMovements() {
        try {
            const params = {};
            if (this.state.filters.type) params.type = this.state.filters.type;
            if (this.state.filters.search) params.search = this.state.filters.search;
            if (this.state.filters.start_date) params.start_date = this.state.filters.start_date;
            if (this.state.filters.end_date) params.end_date = this.state.filters.end_date;

            const res = await apiClient.get('movements', params);
            if (res.success) {
                this.state.movements = res.data || [];
                this.renderMovementsTable();
            }
        } catch (err) {
            console.error('Failed to load movements', err);
            document.getElementById('movements-tbody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">Failed to load ledger: ${err.message}</td>
                </tr>
            `;
        }
    },

    async loadProducts() {
        try {
            const res = await apiClient.get('products', { limit: 100 });
            if (res.success) {
                this.state.products = res.data || [];
                const select = document.getElementById('adj-product-select');
                if (select) {
                    const opts = this.state.products.map(p => `
                        <option value="${p.id}">${p.item_code} - ${p.name} (Stock: ${p.quantity} ${p.unit})</option>
                    `).join('');
                    select.innerHTML = '<option value="">Select a catalog product...</option>' + opts;
                }
            }
        } catch (err) {
            console.error('Failed to load products', err);
        }
    },

    renderMovementsTable() {
        const tbody = document.getElementById('movements-tbody');
        if (!tbody) return;

        if (this.state.movements.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">No stock movement records found matching the filter criteria.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.movements.map(m => {
            const dateStr = m.created_at ? new Date(m.created_at).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' }) : '—';
            const delta = parseInt(m.quantity_change, 10);
            const deltaBadge = delta > 0 
                ? `<span class="badge badge-success font-mono font-bold">+${delta} ${m.unit || 'pc'}</span>`
                : (delta < 0 
                    ? `<span class="badge badge-danger font-mono font-bold">${delta} ${m.unit || 'pc'}</span>`
                    : `<span class="badge badge-secondary font-mono">0</span>`);

            const typeBadge = this.getTypeBadge(m.type);

            return `
                <tr>
                    <td class="text-xs text-muted font-mono whitespace-nowrap">${dateStr}</td>
                    <td class="font-mono font-bold text-xs text-primary">${m.reference_id || 'N/A'}</td>
                    <td>
                        <div class="font-semibold text-sm">${m.product_name}</div>
                        <div class="text-xs text-muted font-mono">${m.item_code}</div>
                    </td>
                    <td class="text-center">${typeBadge}</td>
                    <td class="text-center">${deltaBadge}</td>
                    <td class="text-center font-mono text-xs">
                        <span class="text-muted">${m.stock_before}</span>
                        <span class="text-dim">➔</span>
                        <span class="font-bold">${m.stock_after}</span>
                    </td>
                    <td class="text-xs text-muted max-w-xs truncate" title="${m.notes || ''}">
                        ${m.notes || '—'}
                    </td>
                    <td class="text-right text-xs">
                        <div class="font-medium">${m.recorded_by_name || 'System'}</div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getTypeBadge(type) {
        switch (type) {
            case 'SALE':
                return '<span class="badge badge-danger text-xs">SALE</span>';
            case 'DELIVERY (PO)':
            case 'DELIVERY':
                return '<span class="badge badge-success text-xs">DELIVERY</span>';
            case 'ADJUSTMENT':
                return '<span class="badge badge-warning text-xs">ADJUSTMENT</span>';
            case 'RETURN':
                return '<span class="badge badge-info text-xs">RETURN</span>';
            default:
                return `<span class="badge badge-secondary text-xs">${type}</span>`;
        }
    },

    openAdjustModal() {
        document.getElementById('adjust-error').style.display = 'none';
        document.getElementById('adjust-form').reset();
        document.getElementById('adj-current-stock').textContent = '—';
        document.getElementById('adj-delta-display').textContent = '—';
        this.state.selectedProduct = null;
        document.getElementById('adjust-modal')?.classList.remove('hidden');
    },

    async handleCommitAdjustment() {
        const errEl = document.getElementById('adjust-error');
        const submitBtn = document.getElementById('save-adjust-btn');
        errEl.style.display = 'none';

        const productId = parseInt(document.getElementById('adj-product-select').value, 10);
        const newQty = parseInt(document.getElementById('adj-new-qty').value, 10);
        const notes = document.getElementById('adj-notes').value.trim();

        if (!productId) {
            errEl.textContent = 'Please select a valid catalog product.';
            errEl.style.display = 'block';
            return;
        }

        if (isNaN(newQty) || newQty < 0) {
            errEl.textContent = 'Please enter a valid physical count quantity (zero or positive).';
            errEl.style.display = 'block';
            return;
        }

        if (notes === '') {
            errEl.textContent = 'An audit reason/note is required to justify inventory adjustment.';
            errEl.style.display = 'block';
            return;
        }

        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Committing to Ledger...';

            const payload = {
                product_id: productId,
                new_quantity: newQty,
                notes: notes
            };

            const response = await apiClient.post('movements', payload);

            if (response.success) {
                document.getElementById('adjust-modal')?.classList.add('hidden');
                State.setAlert('Stock count adjustment committed and logged into ledger.', 'success');
                await Promise.all([
                    this.loadMetrics(),
                    this.loadMovements(),
                    this.loadProducts()
                ]);
            }
        } catch (err) {
            errEl.textContent = err.message || 'Failed to commit adjustment.';
            errEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '⚖️ Commit Adjustment to Ledger';
        }
    }
};
