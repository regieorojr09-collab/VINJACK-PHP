import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Consignment Payables & Supplier Liabilities View
 * 
 * Manages tracking of sold consigned inventory, calculation of amounts owed to suppliers,
 * and individual/batch settlement payouts.
 */
export const ConsignmentPage = {
    state: {
        payables: [],
        summary: null,
        suppliers: [],
        filters: {
            status: '',
            supplier_id: '',
            search: ''
        },
        selectedPayableIds: new Set()
    },

    render() {
        const canManage = State.hasPermission('canManageSuppliers');

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Consignment Payables & Settlements</h1>
                        <p class="text-muted text-sm">Track liabilities accrued from sales of consigned stock and record vendor settlement payouts.</p>
                    </div>
                    ${canManage ? `
                        <button id="batch-settle-btn" class="btn btn-primary" disabled>
                            <span>💳</span> Mark Selected as Paid (0)
                        </button>
                    ` : ''}
                </div>

                <!-- KPI Metric Cards -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card border-warning">
                        <span class="metric-label">Outstanding Liability</span>
                        <div class="metric-value font-mono text-warning" id="metric-total-owed">₱0.00</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Total Settled Payouts</span>
                        <div class="metric-value font-mono text-success" id="metric-total-paid">₱0.00</div>
                    </div>
                    <div class="metric-card">
                        <span class="metric-label">Pending Payout Records</span>
                        <div class="metric-value font-mono" id="metric-pending-records">0</div>
                    </div>
                    <div class="metric-card" style="border-left-color: var(--info);">
                        <span class="metric-label">Consignment Vendors</span>
                        <div class="metric-value font-mono" style="color: var(--info);" id="metric-total-vendors">0</div>
                    </div>
                </div>

                <!-- Vendor Breakdown Cards -->
                <div class="mb-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-muted mb-2">Vendor Liabilities Breakdown</h2>
                    <div class="grid grid-cols-3 gap-3" id="vendor-cards-container">
                        <div class="card p-3 text-center text-muted col-span-3">Loading vendor summaries...</div>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-4 gap-3 items-center">
                        <div class="col-span-2">
                            <input type="text" id="filter-cp-search" class="form-control" placeholder="🔍 Search vendor name, product title, or SKU..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-cp-status" class="form-control">
                                <option value="">All Statuses (Owed & Paid)</option>
                                <option value="Owed" selected>Unsettled (Owed to Vendor)</option>
                                <option value="Paid">Settled (Paid)</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <select id="filter-cp-supplier" class="form-control">
                                <option value="">All Consignment Suppliers</option>
                            </select>
                            <button id="refresh-cp-btn" class="btn btn-secondary btn-sm" title="Refresh">🔄</button>
                        </div>
                    </div>
                </div>

                <!-- Consignment Payables Ledger Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="consignment-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" id="select-all-payables-check" title="Select All Owed">
                                    </th>
                                    <th>Date / Sale Reference</th>
                                    <th>Supplier / Vendor</th>
                                    <th>Consigned Product / SKU</th>
                                    <th class="text-center">Units Sold</th>
                                    <th class="text-right">Vendor Unit Cost</th>
                                    <th class="text-right">Amount Owed</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="consignment-tbody">
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">Loading consignment payables...</td>
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
        await Promise.all([
            this.loadSuppliers(),
            this.loadSummary(),
            this.loadPayables()
        ]);
    },

    bindEvents() {
        // Status filter
        document.getElementById('filter-cp-status')?.addEventListener('change', (e) => {
            this.state.filters.status = e.target.value;
            this.state.selectedPayableIds.clear();
            this.updateBatchButton();
            this.loadPayables();
        });

        // Supplier filter
        document.getElementById('filter-cp-supplier')?.addEventListener('change', (e) => {
            this.state.filters.supplier_id = e.target.value;
            this.state.selectedPayableIds.clear();
            this.updateBatchButton();
            this.loadPayables();
        });

        // Search filter
        let searchTimer;
        document.getElementById('filter-cp-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.loadPayables();
            }, 300);
        });

        document.getElementById('refresh-cp-btn')?.addEventListener('click', () => {
            this.loadSummary();
            this.loadPayables();
        });

        // Select All Checkbox
        document.getElementById('select-all-payables-check')?.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            document.querySelectorAll('.payable-row-check:not(:disabled)').forEach(cb => {
                cb.checked = isChecked;
                const id = parseInt(cb.dataset.id, 10);
                if (isChecked) {
                    this.state.selectedPayableIds.add(id);
                } else {
                    this.state.selectedPayableIds.delete(id);
                }
            });
            this.updateBatchButton();
        });

        // Row checkbox delegation
        document.getElementById('consignment-tbody')?.addEventListener('change', (e) => {
            if (e.target.classList.contains('payable-row-check')) {
                const id = parseInt(e.target.dataset.id, 10);
                if (e.target.checked) {
                    this.state.selectedPayableIds.add(id);
                } else {
                    this.state.selectedPayableIds.delete(id);
                }
                this.updateBatchButton();
            }
        });

        // Single Settle Action
        document.getElementById('consignment-tbody')?.addEventListener('click', (e) => {
            const settleBtn = e.target.closest('.settle-single-btn');
            if (settleBtn) {
                const payableId = parseInt(settleBtn.dataset.id, 10);
                const amt = settleBtn.dataset.amount;
                if (confirm(`Confirm settlement payout of ₱${amt} for this consignment item?`)) {
                    this.handleSettleSingle(payableId);
                }
            }
        });

        // Batch Settle Button
        document.getElementById('batch-settle-btn')?.addEventListener('click', () => {
            const count = this.state.selectedPayableIds.size;
            if (count === 0) return;

            if (confirm(`Confirm batch settlement payout for ${count} selected consignment payable record(s)?`)) {
                this.handleSettleBatch();
            }
        });

        // Vendor Breakdown Settle All
        document.getElementById('vendor-cards-container')?.addEventListener('click', (e) => {
            const settleAllBtn = e.target.closest('.settle-vendor-all-btn');
            if (settleAllBtn) {
                const supId = parseInt(settleAllBtn.dataset.supplierId, 10);
                const supName = settleAllBtn.dataset.supplierName;
                const owed = settleAllBtn.dataset.owed;
                if (confirm(`Confirm full settlement of ₱${owed} to vendor '${supName}'?`)) {
                    this.handleSettleVendorAll(supId, supName);
                }
            }
        });
    },

    updateBatchButton() {
        const btn = document.getElementById('batch-settle-btn');
        const count = this.state.selectedPayableIds.size;
        if (btn) {
            btn.disabled = count === 0;
            btn.innerHTML = `<span>💳</span> Mark Selected as Paid (${count})`;
        }
    },

    async loadSummary() {
        try {
            const res = await apiClient.get('consignment/summary');
            if (res.success && res.data) {
                this.state.summary = res.data;
                const { kpis, vendors } = res.data;

                // Update KPIs
                const elOwed = document.getElementById('metric-total-owed');
                const elPaid = document.getElementById('metric-total-paid');
                const elRecords = document.getElementById('metric-pending-records');
                const elVendors = document.getElementById('metric-total-vendors');

                if (elOwed) elOwed.textContent = `₱${parseFloat(kpis.total_owed || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                if (elPaid) elPaid.textContent = `₱${parseFloat(kpis.total_paid || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                if (elRecords) elRecords.textContent = kpis.pending_records_count || 0;
                if (elVendors) elVendors.textContent = kpis.total_vendors_count || 0;

                // Render Vendor Breakdown Cards
                const container = document.getElementById('vendor-cards-container');
                if (container) {
                    if (!vendors || vendors.length === 0) {
                        container.innerHTML = `<div class="card p-4 text-center text-muted col-span-3">No active consignment vendor liabilities.</div>`;
                    } else {
                        container.innerHTML = vendors.map(v => {
                            const owedFmt = parseFloat(v.total_owed).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                            const paidFmt = parseFloat(v.total_paid).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                            const hasOwed = parseFloat(v.total_owed) > 0;

                            return `
                                <div class="card p-3 flex flex-col justify-between">
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="flex justify-between items-start">
                                            <h3 class="font-bold text-sm text-primary">${v.supplier_name}</h3>
                                            <span class="badge ${hasOwed ? 'badge-warning' : 'badge-success'} text-xs">
                                                ${hasOwed ? `${v.pending_items_count} items owed` : 'Settled'}
                                            </span>
                                        </div>
                                        <div class="text-xs text-muted">${v.contact_person || 'No rep'} | ${v.contact_number || 'No phone'}</div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                                        <div>
                                            <span class="text-dim block">Outstanding Owed</span>
                                            <span class="font-mono font-bold text-base ${hasOwed ? 'text-warning' : 'text-muted'}">₱${owedFmt}</span>
                                        </div>
                                        <div>
                                            <span class="text-dim block">Total Settled</span>
                                            <span class="font-mono font-bold text-base text-success">₱${paidFmt}</span>
                                        </div>
                                    </div>
                                    ${hasOwed ? `
                                        <button class="btn btn-secondary btn-sm btn-block settle-vendor-all-btn text-warning" 
                                                data-supplier-id="${v.supplier_id}" 
                                                data-supplier-name="${v.supplier_name}" 
                                                data-owed="${owedFmt}">
                                            Settle All (₱${owedFmt})
                                        </button>
                                    ` : `
                                        <button class="btn btn-secondary btn-sm btn-block text-muted" disabled>
                                            ✓ Up to Date
                                        </button>
                                    `}
                                </div>
                            `;
                        }).join('');
                    }
                }
            }
        } catch (err) {
            console.error('Failed to load consignment summary', err);
        }
    },

    async loadSuppliers() {
        try {
            const res = await apiClient.get('suppliers');
            if (res.success) {
                this.state.suppliers = res.data || [];
                const select = document.getElementById('filter-cp-supplier');
                if (select) {
                    const opts = this.state.suppliers.map(s => `
                        <option value="${s.id}">${s.name}</option>
                    `).join('');
                    select.innerHTML = '<option value="">All Consignment Suppliers</option>' + opts;
                }
            }
        } catch (err) {
            console.error('Failed to load suppliers', err);
        }
    },

    async loadPayables() {
        try {
            const params = {};
            if (this.state.filters.status) params.status = this.state.filters.status;
            if (this.state.filters.supplier_id) params.supplier_id = this.state.filters.supplier_id;
            if (this.state.filters.search) params.search = this.state.filters.search;

            const res = await apiClient.get('consignment', params);
            if (res.success) {
                this.state.payables = res.data || [];
                this.renderPayablesTable();
            }
        } catch (err) {
            console.error('Failed to load consignment payables', err);
            document.getElementById('consignment-tbody').innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-danger">Failed to load payables: ${err.message}</td>
                </tr>
            `;
        }
    },

    renderPayablesTable() {
        const tbody = document.getElementById('consignment-tbody');
        if (!tbody) return;

        if (this.state.payables.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">No consignment payable records match the filter criteria.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.payables.map(cp => {
            const isOwed = cp.status === 'Owed';
            const costFmt = parseFloat(cp.cost_at_time_of_sale).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const owedFmt = parseFloat(cp.amount_owed).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const dateStr = cp.created_at ? new Date(cp.created_at).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' }) : '—';
            const isChecked = this.state.selectedPayableIds.has(cp.id);

            return `
                <tr>
                    <td class="text-center">
                        <input type="checkbox" 
                               class="payable-row-check" 
                               data-id="${cp.id}" 
                               ${isChecked ? 'checked' : ''} 
                               ${!isOwed ? 'disabled' : ''}>
                    </td>
                    <td>
                        <div class="font-mono font-bold text-xs">#SALE-${cp.sale_id}</div>
                        <div class="text-xs text-muted font-mono">${dateStr}</div>
                    </td>
                    <td>
                        <div class="font-semibold text-sm">${cp.supplier_name}</div>
                        <div class="text-xs text-muted">${cp.supplier_contact_person || 'Consignment'}</div>
                    </td>
                    <td>
                        <div class="font-semibold text-sm">${cp.product_name}</div>
                        <div class="text-xs text-muted font-mono">${cp.item_code}</div>
                    </td>
                    <td class="text-center font-mono font-bold">${cp.quantity_sold} ${cp.unit || 'pc'}</td>
                    <td class="text-right font-mono text-xs">₱${costFmt}</td>
                    <td class="text-right font-mono font-bold ${isOwed ? 'text-warning' : 'text-success'}">₱${owedFmt}</td>
                    <td class="text-center">
                        ${isOwed 
                            ? `<span class="badge badge-warning text-xs">Owed</span>` 
                            : `<span class="badge badge-success text-xs" title="Paid on ${cp.paid_date || ''}">Paid</span>`}
                    </td>
                    <td class="text-right">
                        ${isOwed ? `
                            <button class="btn btn-secondary btn-sm settle-single-btn text-success" 
                                    data-id="${cp.id}" 
                                    data-amount="${owedFmt}">
                                ✓ Mark Paid
                            </button>
                        ` : `
                            <span class="text-muted text-xs">Settled</span>
                        `}
                    </td>
                </tr>
            `;
        }).join('');
    },

    async handleSettleSingle(payableId) {
        try {
            const res = await apiClient.post('consignment/settle', { payable_id: payableId });
            if (res.success) {
                State.setAlert('Consignment payable marked as Paid.', 'success');
                this.state.selectedPayableIds.delete(payableId);
                this.updateBatchButton();
                await Promise.all([
                    this.loadSummary(),
                    this.loadPayables()
                ]);
            }
        } catch (err) {
            State.setAlert('Settlement failed: ' + err.message, 'danger');
        }
    },

    async handleSettleBatch() {
        const ids = Array.from(this.state.selectedPayableIds);
        try {
            const res = await apiClient.post('consignment/settle', { payable_ids: ids });
            if (res.success) {
                State.setAlert(res.message || 'Selected payables settled successfully.', 'success');
                this.state.selectedPayableIds.clear();
                this.updateBatchButton();
                await Promise.all([
                    this.loadSummary(),
                    this.loadPayables()
                ]);
            }
        } catch (err) {
            State.setAlert('Batch settlement failed: ' + err.message, 'danger');
        }
    },

    async handleSettleVendorAll(supplierId, supplierName) {
        try {
            const res = await apiClient.post('consignment/settle-supplier', { supplier_id: supplierId });
            if (res.success) {
                State.setAlert(`All outstanding liabilities to '${supplierName}' settled.`, 'success');
                this.state.selectedPayableIds.clear();
                this.updateBatchButton();
                await Promise.all([
                    this.loadSummary(),
                    this.loadPayables()
                ]);
            }
        } catch (err) {
            State.setAlert('Vendor settlement failed: ' + err.message, 'danger');
        }
    }
};
