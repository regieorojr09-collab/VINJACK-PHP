import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Returns & Warranty Claims View
 * 
 * Manages customer returns (with automated restock or replacement),
 * and supplier RMA returns for defective/overstock merchandise.
 */
export const ReturnsPage = {
    state: {
        activeTab: 'customer', // 'customer' or 'supplier'
        customerReturns: [],
        supplierReturns: [],
        suppliers: [],
        products: [],
        lookupSale: null,
        supplierReturnLines: [
            { product_id: '', quantity: 1, reason: 'Defective', was_consigned: false }
        ]
    },

    render() {
        const canManage = State.hasPermission('canManageReturns');

        return `
            <div class="view-container">
                <!-- Page Header with Tabs -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Returns & Warranty Claims</h1>
                        <p class="text-muted text-sm">Process customer returns, issue restock entries, and dispatch vendor RMA shipments.</p>
                    </div>
                    ${canManage ? `
                        <div class="flex gap-2">
                            <button id="open-cust-return-btn" class="btn btn-primary">
                                <span>↩️</span> Customer Return
                            </button>
                            <button id="open-sup-return-btn" class="btn btn-secondary">
                                <span>📤</span> Supplier Return
                            </button>
                        </div>
                    ` : ''}
                </div>

                <!-- Tab Navigation Pills -->
                <div class="card p-2 mb-4">
                    <div class="flex gap-2">
                        <button class="btn btn-sm ${this.state.activeTab === 'customer' ? 'btn-primary' : 'btn-secondary'}" id="tab-cust-returns-btn">
                            👤 Customer Returns & Restock
                        </button>
                        <button class="btn btn-sm ${this.state.activeTab === 'supplier' ? 'btn-primary' : 'btn-secondary'}" id="tab-sup-returns-btn">
                            🏢 Supplier Returns (RMA)
                        </button>
                    </div>
                </div>

                <!-- Dynamic Content Outlet -->
                <div id="returns-content-outlet">
                    <!-- Populated based on activeTab -->
                </div>
            </div>

            <!-- Customer Return Modal -->
            <div id="cust-return-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div>
                            <h2 class="text-lg font-bold">Process Customer Return</h2>
                            <p class="text-xs text-muted">Verify original sale ticket, select returned items, and assign disposition</p>
                        </div>
                        <button id="close-cust-ret-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <div class="mt-3">
                        <div id="cust-ret-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <!-- Ticket Lookup Bar -->
                        <div class="form-group mb-3">
                            <label class="text-xs font-semibold text-muted block mb-1">Lookup Original Sale Ticket # *</label>
                            <div class="flex gap-2">
                                <input type="number" id="lookup-sale-id" class="form-control font-mono" placeholder="Enter Sale ID (e.g. 1)" autofocus>
                                <button type="button" id="btn-lookup-sale" class="btn btn-secondary">
                                    🔍 Find Ticket
                                </button>
                            </div>
                        </div>

                        <!-- Ticket Info Container -->
                        <div id="lookup-ticket-info" class="p-3 bg-surface rounded mb-3 hidden">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-sm" id="ticket-customer-name">Customer: Walk-in</div>
                                    <div class="text-xs text-muted" id="ticket-vehicle-plate">Vehicle: No plate</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-bold text-primary" id="ticket-total-amt">₱0.00</div>
                                    <div class="text-xs text-muted" id="ticket-date">Date: —</div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchased Items Selection Table -->
                        <div id="lookup-items-section" class="mb-3 hidden">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-muted mb-2">Purchased Items to Return</h3>
                            <div class="table-responsive">
                                <table class="data-table text-xs">
                                    <thead>
                                        <tr>
                                            <th>Item / SKU</th>
                                            <th class="text-center">Sold Qty</th>
                                            <th class="text-center">Already Rec'd</th>
                                            <th class="text-center">Eligible</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-center" style="width: 120px;">Return Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ticket-items-tbody">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Return Parameters -->
                        <div id="return-details-section" class="grid grid-cols-2 gap-3 mb-3 hidden">
                            <div>
                                <label for="ret-outcome-select" class="text-xs font-semibold text-muted block mb-1">Return Disposition Outcome *</label>
                                <select id="ret-outcome-select" class="form-control font-medium" required>
                                    <option value="Restocked">Restocked (Add back to sellable inventory)</option>
                                    <option value="Refunded">Refunded Only (Do not restock)</option>
                                    <option value="Replaced">Replaced (Exchange unit under warranty)</option>
                                    <option value="Discarded">Discarded (Defective/Damaged item written off)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-muted block mb-1">Calculated Refund Total</label>
                                <div class="font-mono text-xl font-bold text-success p-2 bg-surface rounded border" id="ret-calculated-refund">
                                    ₱0.00
                                </div>
                            </div>
                            <div class="col-span-2">
                                <label for="ret-reason-text" class="text-xs font-semibold text-muted block mb-1">Return Reason / Diagnosis *</label>
                                <textarea id="ret-reason-text" class="form-control" rows="2" placeholder="e.g. Wrong sizing, defective packaging, customer exchanged for compatible model" required></textarea>
                            </div>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-cust-ret-btn" class="btn btn-secondary">Cancel</button>
                            <button type="button" id="submit-cust-ret-btn" class="btn btn-primary" disabled>
                                ↩️ Process Customer Return
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Return Modal -->
            <div id="sup-return-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div>
                            <h2 class="text-lg font-bold">Issue Supplier Return Dispatch (RMA)</h2>
                            <p class="text-xs text-muted">Return defective, damaged, or overstock items to vendor</p>
                        </div>
                        <button id="close-sup-ret-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="sup-return-form" class="mt-3">
                        <div id="sup-ret-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="form-group mb-3">
                            <label for="sr-supplier-select" class="text-xs font-semibold text-muted block mb-1">Target Vendor / Supplier *</label>
                            <select id="sr-supplier-select" class="form-control" required>
                                <option value="">Select supplier...</option>
                            </select>
                        </div>

                        <!-- Dynamic Return Items -->
                        <div class="border-top pt-3 mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-muted">Items to Dispatch</h3>
                                <button type="button" id="add-sup-line-btn" class="btn btn-secondary btn-sm">+ Add Product</button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table text-xs">
                                    <thead>
                                        <tr>
                                            <th style="width: 45%;">Product / SKU</th>
                                            <th style="width: 15%;" class="text-center">Quantity</th>
                                            <th style="width: 25%;">Reason</th>
                                            <th style="width: 15%;" class="text-center">Consigned?</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="sup-ret-items-tbody">
                                        <!-- Rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="sr-notes" class="text-xs font-semibold text-muted block mb-1">RMA Reference / Shipping Notes</label>
                            <input type="text" id="sr-notes" class="form-control" placeholder="e.g. RMA #98124, Shipped via Lalamove van delivery">
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-sup-ret-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="submit-sup-ret-btn" class="btn btn-primary">
                                📤 Commit Supplier Return
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
            this.loadCustomerReturns(),
            this.loadSupplierReturns(),
            this.loadSuppliers(),
            this.loadProducts()
        ]);
        this.renderActiveTabContent();
    },

    bindEvents() {
        // Tab switching
        document.getElementById('tab-cust-returns-btn')?.addEventListener('click', () => {
            this.state.activeTab = 'customer';
            document.getElementById('tab-cust-returns-btn')?.classList.add('btn-primary');
            document.getElementById('tab-cust-returns-btn')?.classList.remove('btn-secondary');
            document.getElementById('tab-sup-returns-btn')?.classList.remove('btn-primary');
            document.getElementById('tab-sup-returns-btn')?.classList.add('btn-secondary');
            this.renderActiveTabContent();
        });

        document.getElementById('tab-sup-returns-btn')?.addEventListener('click', () => {
            this.state.activeTab = 'supplier';
            document.getElementById('tab-sup-returns-btn')?.classList.add('btn-primary');
            document.getElementById('tab-sup-returns-btn')?.classList.remove('btn-secondary');
            document.getElementById('tab-cust-returns-btn')?.classList.remove('btn-primary');
            document.getElementById('tab-cust-returns-btn')?.classList.add('btn-secondary');
            this.renderActiveTabContent();
        });

        // Open Modals
        document.getElementById('open-cust-return-btn')?.addEventListener('click', () => {
            this.openCustomerReturnModal();
        });
        document.getElementById('open-sup-return-btn')?.addEventListener('click', () => {
            this.openSupplierReturnModal();
        });

        // Close Customer Return Modal
        document.getElementById('close-cust-ret-modal-btn')?.addEventListener('click', () => {
            document.getElementById('cust-return-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-cust-ret-btn')?.addEventListener('click', () => {
            document.getElementById('cust-return-modal')?.classList.add('hidden');
        });

        // Close Supplier Return Modal
        document.getElementById('close-sup-ret-modal-btn')?.addEventListener('click', () => {
            document.getElementById('sup-return-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-sup-ret-btn')?.addEventListener('click', () => {
            document.getElementById('sup-return-modal')?.classList.add('hidden');
        });

        // Lookup Sale Ticket
        document.getElementById('btn-lookup-sale')?.addEventListener('click', () => {
            this.lookupSaleTicket();
        });
        document.getElementById('lookup-sale-id')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.lookupSaleTicket();
            }
        });

        // Recompute Customer Refund on input
        document.getElementById('ticket-items-tbody')?.addEventListener('input', () => {
            this.recomputeCustomerRefund();
        });

        // Submit Customer Return
        document.getElementById('submit-cust-ret-btn')?.addEventListener('click', () => {
            this.handleCommitCustomerReturn();
        });

        // Supplier Return dynamic lines
        document.getElementById('add-sup-line-btn')?.addEventListener('click', () => {
            this.state.supplierReturnLines.push({
                product_id: '',
                quantity: 1,
                reason: 'Defective',
                was_consigned: false
            });
            this.renderSupplierReturnLines();
        });

        document.getElementById('sup-ret-items-tbody')?.addEventListener('change', (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const index = parseInt(row.dataset.index, 10);

            if (e.target.classList.contains('sr-product-select')) {
                this.state.supplierReturnLines[index].product_id = parseInt(e.target.value, 10) || '';
            }
            if (e.target.classList.contains('sr-reason-select')) {
                this.state.supplierReturnLines[index].reason = e.target.value;
            }
            if (e.target.classList.contains('sr-consigned-check')) {
                this.state.supplierReturnLines[index].was_consigned = e.target.checked;
            }
        });

        document.getElementById('sup-ret-items-tbody')?.addEventListener('input', (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const index = parseInt(row.dataset.index, 10);
            if (e.target.classList.contains('sr-qty-input')) {
                this.state.supplierReturnLines[index].quantity = parseInt(e.target.value, 10) || 0;
            }
        });

        document.getElementById('sup-ret-items-tbody')?.addEventListener('click', (e) => {
            const delBtn = e.target.closest('.delete-sr-line-btn');
            if (delBtn) {
                const row = delBtn.closest('tr');
                const index = parseInt(row.dataset.index, 10);
                if (this.state.supplierReturnLines.length > 1) {
                    this.state.supplierReturnLines.splice(index, 1);
                    this.renderSupplierReturnLines();
                } else {
                    State.setAlert('At least one item is required.', 'warning', 2500);
                }
            }
        });

        // Submit Supplier Return
        document.getElementById('sup-return-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleCommitSupplierReturn();
        });
    },

    async loadCustomerReturns() {
        try {
            const res = await apiClient.get('returns/customer');
            if (res.success) {
                this.state.customerReturns = res.data || [];
            }
        } catch (err) {
            console.error('Failed to load customer returns', err);
        }
    },

    async loadSupplierReturns() {
        try {
            const res = await apiClient.get('returns/supplier');
            if (res.success) {
                this.state.supplierReturns = res.data || [];
            }
        } catch (err) {
            console.error('Failed to load supplier returns', err);
        }
    },

    async loadSuppliers() {
        try {
            const res = await apiClient.get('suppliers', { status: 'Approved' });
            if (res.success) {
                this.state.suppliers = res.data || [];
                const select = document.getElementById('sr-supplier-select');
                if (select) {
                    const opts = this.state.suppliers.map(s => `
                        <option value="${s.id}">${s.name}</option>
                    `).join('');
                    select.innerHTML = '<option value="">Select supplier...</option>' + opts;
                }
            }
        } catch (err) {
            console.error('Failed to load suppliers', err);
        }
    },

    async loadProducts() {
        try {
            const res = await apiClient.get('products', { limit: 100 });
            if (res.success) {
                this.state.products = res.data || [];
            }
        } catch (err) {
            console.error('Failed to load products', err);
        }
    },

    renderActiveTabContent() {
        const outlet = document.getElementById('returns-content-outlet');
        if (!outlet) return;

        if (this.state.activeTab === 'customer') {
            this.renderCustomerReturnsTab(outlet);
        } else {
            this.renderSupplierReturnsTab(outlet);
        }
    },

    renderCustomerReturnsTab(container) {
        const totalReturns = this.state.customerReturns.length;
        const totalRefundVal = this.state.customerReturns.reduce((sum, r) => sum + parseFloat(r.total_refund_amount || 0), 0);
        const restockedCount = this.state.customerReturns.filter(r => r.outcome === 'Restocked').length;

        container.innerHTML = `
            <!-- Metric Cards -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="metric-card">
                    <span class="metric-label">Customer Return Tickets</span>
                    <div class="metric-value font-mono">${totalReturns}</div>
                </div>
                <div class="metric-card border-danger">
                    <span class="metric-label">Total Refunded Value</span>
                    <div class="metric-value font-mono text-danger">₱${totalRefundVal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
                </div>
                <div class="metric-card border-success">
                    <span class="metric-label">Restocked Outcomes</span>
                    <div class="metric-value font-mono text-success">${restockedCount}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="card p-0 table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Return ID</th>
                                <th>Original Sale Ticket</th>
                                <th>Customer</th>
                                <th>Date Processed</th>
                                <th class="text-center">Disposition Outcome</th>
                                <th class="text-right">Total Refund</th>
                                <th>Reason</th>
                                <th class="text-right">Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${this.state.customerReturns.length === 0 ? `
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">No customer return tickets on record.</td>
                                </tr>
                            ` : this.state.customerReturns.map(r => {
                                const dateStr = r.created_at ? new Date(r.created_at).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' }) : '—';
                                const refundFmt = parseFloat(r.total_refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                                const badgeClass = r.outcome === 'Restocked' ? 'badge-success' : (r.outcome === 'Refunded' ? 'badge-warning' : 'badge-secondary');

                                return `
                                    <tr>
                                        <td class="font-mono font-bold text-xs text-primary">RET-${r.id}</td>
                                        <td class="font-mono font-semibold text-xs">#SALE-${r.original_sale_id}</td>
                                        <td class="text-sm font-medium">${r.customer_name || 'Walk-in'}</td>
                                        <td class="text-xs text-muted font-mono">${dateStr}</td>
                                        <td class="text-center"><span class="badge ${badgeClass} text-xs">${r.outcome}</span></td>
                                        <td class="text-right font-mono font-bold text-danger">₱${refundFmt}</td>
                                        <td class="text-xs text-muted max-w-xs truncate" title="${r.reason}">${r.reason}</td>
                                        <td class="text-right text-xs">${r.recorded_by_name || 'Staff'}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    renderSupplierReturnsTab(container) {
        const totalReturns = this.state.supplierReturns.length;
        const totalUnits = this.state.supplierReturns.reduce((sum, r) => sum + parseInt(r.total_units_returned || 0, 10), 0);

        container.innerHTML = `
            <!-- Metric Cards -->
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="metric-card">
                    <span class="metric-label">Supplier Return Shipments (RMA)</span>
                    <div class="metric-value font-mono">${totalReturns}</div>
                </div>
                <div class="metric-card border-warning">
                    <span class="metric-label">Total Units Dispatched to Vendors</span>
                    <div class="metric-value font-mono text-warning">${totalUnits.toLocaleString()}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="card p-0 table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>RMA ID</th>
                                <th>Vendor / Supplier</th>
                                <th>Dispatch Date</th>
                                <th class="text-center">Units Returned</th>
                                <th>Notes / Tracking Reference</th>
                                <th class="text-right">Authorized By</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${this.state.supplierReturns.length === 0 ? `
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">No supplier return dispatches recorded.</td>
                                </tr>
                            ` : this.state.supplierReturns.map(sr => {
                                const dateStr = sr.return_date ? new Date(sr.return_date).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' }) : '—';
                                return `
                                    <tr>
                                        <td class="font-mono font-bold text-xs text-primary">SRET-${sr.id}</td>
                                        <td class="font-bold text-sm">${sr.supplier_name}</td>
                                        <td class="text-xs text-muted font-mono">${dateStr}</td>
                                        <td class="text-center font-mono font-bold text-warning">${sr.total_units_returned || 0}</td>
                                        <td class="text-xs text-muted max-w-sm truncate" title="${sr.notes || ''}">${sr.notes || '—'}</td>
                                        <td class="text-right text-xs">${sr.recorded_by_name || 'Staff'}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    openCustomerReturnModal() {
        document.getElementById('cust-ret-error').style.display = 'none';
        document.getElementById('lookup-sale-id').value = '';
        document.getElementById('lookup-ticket-info').classList.add('hidden');
        document.getElementById('lookup-items-section').classList.add('hidden');
        document.getElementById('return-details-section').classList.add('hidden');
        document.getElementById('submit-cust-ret-btn').disabled = true;
        this.state.lookupSale = null;
        document.getElementById('cust-return-modal')?.classList.remove('hidden');
    },

    async lookupSaleTicket() {
        const saleId = parseInt(document.getElementById('lookup-sale-id').value, 10);
        const errEl = document.getElementById('cust-ret-error');
        errEl.style.display = 'none';

        if (!saleId) {
            errEl.textContent = 'Please enter a valid numeric sale ticket ID.';
            errEl.style.display = 'block';
            return;
        }

        try {
            const res = await apiClient.get(`returns/sale/${saleId}`);
            if (!res.success || !res.data) {
                throw new Error('Sale ticket not found.');
            }

            const sale = res.data;
            this.state.lookupSale = sale;

            document.getElementById('ticket-customer-name').textContent = `Customer: ${sale.customer_name || 'Walk-in'}`;
            document.getElementById('ticket-vehicle-plate').textContent = sale.plate_number ? `🏍️ ${sale.plate_number} (${sale.make || ''} ${sale.model || ''})` : 'No vehicle linked';
            document.getElementById('ticket-total-amt').textContent = `₱${parseFloat(sale.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
            document.getElementById('ticket-date').textContent = `Date: ${new Date(sale.created_at).toLocaleDateString('en-PH')}`;
            document.getElementById('lookup-ticket-info').classList.remove('hidden');

            // Render purchased items
            const itemsTbody = document.getElementById('ticket-items-tbody');
            if (!sale.items || sale.items.length === 0) {
                itemsTbody.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-muted">No parts/products on this ticket.</td></tr>`;
            } else {
                itemsTbody.innerHTML = sale.items.map(item => {
                    const priceFmt = parseFloat(item.price_at_time).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                    const avail = parseInt(item.quantity_available_for_return, 10);

                    return `
                        <tr data-product-id="${item.product_id}" data-price="${item.price_at_time}">
                            <td>
                                <div class="font-bold">${item.product_name}</div>
                                <div class="text-dim font-mono">${item.item_code}</div>
                            </td>
                            <td class="text-center font-mono">${item.quantity_sold}</td>
                            <td class="text-center font-mono">${item.quantity_already_returned}</td>
                            <td class="text-center font-mono font-bold ${avail > 0 ? 'text-success' : 'text-muted'}">${avail}</td>
                            <td class="text-right font-mono">₱${priceFmt}</td>
                            <td class="text-center">
                                <input type="number" 
                                       class="form-control form-control-sm text-center font-mono cust-ret-qty-input" 
                                       min="0" 
                                       max="${avail}" 
                                       value="0" 
                                       ${avail <= 0 ? 'disabled' : ''}>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            document.getElementById('lookup-items-section').classList.remove('hidden');
            document.getElementById('return-details-section').classList.remove('hidden');
            this.recomputeCustomerRefund();

        } catch (err) {
            errEl.textContent = err.message || 'Sale ticket lookup failed.';
            errEl.style.display = 'block';
        }
    },

    recomputeCustomerRefund() {
        let totalRefund = 0;
        let hasAnyReturn = false;

        document.querySelectorAll('#ticket-items-tbody tr').forEach(row => {
            const price = parseFloat(row.dataset.price) || 0;
            const input = row.querySelector('.cust-ret-qty-input');
            if (input) {
                const qty = parseInt(input.value, 10) || 0;
                if (qty > 0) {
                    hasAnyReturn = true;
                    totalRefund += (price * qty);
                }
            }
        });

        document.getElementById('ret-calculated-refund').textContent = `₱${totalRefund.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('submit-cust-ret-btn').disabled = !hasAnyReturn;
    },

    async handleCommitCustomerReturn() {
        if (!this.state.lookupSale) return;

        const errEl = document.getElementById('cust-ret-error');
        const submitBtn = document.getElementById('submit-cust-ret-btn');
        errEl.style.display = 'none';

        const saleId = this.state.lookupSale.id;
        const outcome = document.getElementById('ret-outcome-select').value;
        const reason = document.getElementById('ret-reason-text').value.trim();

        if (reason === '') {
            errEl.textContent = 'Please enter a diagnostic return reason.';
            errEl.style.display = 'block';
            return;
        }

        const items = [];
        document.querySelectorAll('#ticket-items-tbody tr').forEach(row => {
            const prodId = parseInt(row.dataset.productId, 10);
            const input = row.querySelector('.cust-ret-qty-input');
            if (input) {
                const qty = parseInt(input.value, 10) || 0;
                if (qty > 0) {
                    items.push({
                        product_id: prodId,
                        quantity: qty
                    });
                }
            }
        });

        if (items.length === 0) {
            errEl.textContent = 'Please specify at least one item quantity to return.';
            errEl.style.display = 'block';
            return;
        }

        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Return...';

            const payload = {
                sale_id: saleId,
                outcome: outcome,
                reason: reason,
                items: items
            };

            const response = await apiClient.post('returns', payload);

            if (response.success) {
                document.getElementById('cust-return-modal')?.classList.add('hidden');
                State.setAlert(`Return processed successfully! Total refund: ₱${parseFloat(response.data?.total_refund_amount || 0).toFixed(2)}`, 'success');
                await this.loadCustomerReturns();
                this.renderActiveTabContent();
            }
        } catch (err) {
            errEl.textContent = err.message || 'Failed to process return.';
            errEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '↩️ Process Customer Return';
        }
    },

    openSupplierReturnModal() {
        document.getElementById('sup-ret-error').style.display = 'none';
        document.getElementById('sup-return-form').reset();
        this.state.supplierReturnLines = [
            { product_id: '', quantity: 1, reason: 'Defective', was_consigned: false }
        ];
        this.renderSupplierReturnLines();
        document.getElementById('sup-return-modal')?.classList.remove('hidden');
    },

    renderSupplierReturnLines() {
        const tbody = document.getElementById('sup-ret-items-tbody');
        if (!tbody) return;

        tbody.innerHTML = this.state.supplierReturnLines.map((line, idx) => {
            return `
                <tr data-index="${idx}">
                    <td>
                        <select class="form-control form-control-sm sr-product-select" required>
                            <option value="">Select product...</option>
                            ${this.state.products.map(p => `
                                <option value="${p.id}" ${p.id === line.product_id ? 'selected' : ''}>
                                    ${p.item_code} - ${p.name} (Stock: ${p.quantity})
                                </option>
                            `).join('')}
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="number" step="1" min="1" class="form-control form-control-sm text-center font-mono sr-qty-input" value="${line.quantity}" required>
                    </td>
                    <td>
                        <select class="form-control form-control-sm sr-reason-select" required>
                            <option value="Defective" ${line.reason === 'Defective' ? 'selected' : ''}>Defective</option>
                            <option value="Wrong Item" ${line.reason === 'Wrong Item' ? 'selected' : ''}>Wrong Item</option>
                            <option value="Overstock" ${line.reason === 'Overstock' ? 'selected' : ''}>Overstock</option>
                            <option value="Other" ${line.reason === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="sr-consigned-check" ${line.was_consigned ? 'checked' : ''}>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-secondary btn-icon delete-sr-line-btn text-danger">✕</button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    async handleCommitSupplierReturn() {
        const errEl = document.getElementById('sup-ret-error');
        const submitBtn = document.getElementById('submit-sup-ret-btn');
        errEl.style.display = 'none';

        const supplierId = parseInt(document.getElementById('sr-supplier-select').value, 10);
        const notes = document.getElementById('sr-notes').value.trim();

        if (!supplierId) {
            errEl.textContent = 'Please select a vendor/supplier.';
            errEl.style.display = 'block';
            return;
        }

        const validLines = this.state.supplierReturnLines.filter(l => l.product_id && l.quantity > 0);
        if (validLines.length === 0) {
            errEl.textContent = 'Please specify at least one product with quantity greater than zero.';
            errEl.style.display = 'block';
            return;
        }

        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Committing Dispatch...';

            const payload = {
                supplier_id: supplierId,
                notes: notes,
                items: validLines
            };

            const response = await apiClient.post('returns/supplier', payload);

            if (response.success) {
                document.getElementById('sup-return-modal')?.classList.add('hidden');
                State.setAlert('Supplier return dispatch committed and logged into inventory ledger.', 'success');
                await this.loadSupplierReturns();
                this.renderActiveTabContent();
            }
        } catch (err) {
            errEl.textContent = err.message || 'Failed to dispatch supplier return.';
            errEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '📤 Commit Supplier Return';
        }
    }
};
