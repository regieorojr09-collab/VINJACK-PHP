import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Purchase Orders & Procurement View
 * 
 * Manages supplier procurement orders, status transitions,
 * XML data interchange exports, and ACID-compliant stock receiving.
 */
export const PurchaseOrdersPage = {
    state: {
        orders: [],
        suppliers: [],
        products: [],
        filters: {
            search: '',
            status: ''
        },
        activePoDetail: null,
        receivingPo: null,
        newLineItems: [
            { product_id: '', quantity: 1, cost: 0.00, total: 0.00 }
        ]
    },

    render() {
        const canManage = State.hasPermission('canManagePurchaseOrders');

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Purchase Orders & Procurement</h1>
                        <p class="text-muted text-sm">Issue supplier orders, download XML interchange files, and verify incoming inventory deliveries.</p>
                    </div>
                    ${canManage ? `
                        <button id="create-po-btn" class="btn btn-primary">
                            <span>+</span> New Purchase Order
                        </button>
                    ` : ''}
                </div>

                <!-- Procurement Metric Cards -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card">
                        <span class="metric-label">Total Purchase Orders</span>
                        <div class="metric-value font-mono" id="metric-total-pos">0</div>
                    </div>
                    <div class="metric-card border-warning">
                        <span class="metric-label">Pending Approval</span>
                        <div class="metric-value text-warning font-mono" id="metric-pending-pos">0</div>
                    </div>
                    <div class="metric-card" style="border-left-color: var(--info);">
                        <span class="metric-label">Awaiting Delivery</span>
                        <div class="metric-value font-mono" id="metric-awaiting-pos" style="color: var(--info);">0</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Total Procurement Value</span>
                        <div class="metric-value text-success font-mono" id="metric-total-value">₱0.00</div>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-3 gap-3 items-center">
                        <div class="col-span-2">
                            <input type="text" id="filter-po-search" class="form-control" placeholder="🔍 Search PO number (e.g. PO-2026...) or supplier name..." value="${this.state.filters.search}">
                        </div>
                        <div class="flex gap-2">
                            <select id="filter-po-status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Partially Received">Partially Received</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            <button id="refresh-po-btn" class="btn btn-secondary" title="Refresh List">🔄</button>
                        </div>
                    </div>
                </div>

                <!-- Purchase Orders Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="purchase-orders-table">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th>Type</th>
                                    <th>Order Date</th>
                                    <th>Items / Units</th>
                                    <th class="text-right">Total Amount</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="purchase-orders-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">Loading purchase orders...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- New Purchase Order Modal -->
            <div id="new-po-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div>
                            <h2 class="text-lg font-bold">Issue New Purchase Order</h2>
                            <p class="text-xs text-muted">Create procurement requisition for approved vendor</p>
                        </div>
                        <button id="close-new-po-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="new-po-form" class="mt-3">
                        <div id="new-po-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div>
                                <label for="po-supplier-select" class="text-xs font-semibold text-muted block mb-1">Target Supplier *</label>
                                <select id="po-supplier-select" class="form-control" required>
                                    <option value="">Select an approved supplier...</option>
                                </select>
                            </div>
                            <div>
                                <label for="po-type-select" class="text-xs font-semibold text-muted block mb-1">Order Type *</label>
                                <select id="po-type-select" class="form-control" required>
                                    <option value="Purchase">Standard Purchase</option>
                                    <option value="Consignment">Consigned Inventory</option>
                                </select>
                            </div>
                            <div>
                                <label for="po-consignment-method" class="text-xs font-semibold text-muted block mb-1">Consignment Method</label>
                                <select id="po-consignment-method" class="form-control">
                                    <option value="System">System Automated</option>
                                    <option value="Manual">Manual Settlement</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label for="po-notes" class="text-xs font-semibold text-muted block mb-1">Internal Notes</label>
                                <input type="text" id="po-notes" class="form-control" placeholder="Optional internal requisition notes">
                            </div>
                            <div>
                                <label for="po-terms" class="text-xs font-semibold text-muted block mb-1">Terms & Conditions</label>
                                <input type="text" id="po-terms" class="form-control" placeholder="e.g. Net 30, FOB Destination">
                            </div>
                        </div>

                        <!-- Dynamic Line Items Section -->
                        <div class="border-top pt-3 mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-sm font-bold uppercase tracking-wider">Order Line Items</h3>
                                <button type="button" id="add-line-item-btn" class="btn btn-secondary btn-sm">
                                    <span>+</span> Add Line Item
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table" style="font-size: 0.825rem;">
                                    <thead>
                                        <tr>
                                            <th style="width: 42%;">Product / SKU</th>
                                            <th style="width: 20%;" class="text-right">Unit Cost (₱)</th>
                                            <th style="width: 18%;" class="text-center">Quantity</th>
                                            <th style="width: 15%;" class="text-right">Line Total (₱)</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="po-line-items-tbody">
                                        <!-- Line items rendered dynamically -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex justify-end items-center gap-4 mt-3 border-top pt-2">
                                <span class="text-muted text-sm font-semibold">Total Order Amount:</span>
                                <span id="po-form-grand-total" class="text-xl font-bold font-mono text-primary">₱0.00</span>
                            </div>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-new-po-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-po-btn" class="btn btn-primary">
                                📝 Submit Purchase Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PO Details Modal -->
            <div id="po-detail-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold font-mono" id="modal-po-number">PO-XXXX-XXXX</h2>
                            <span id="modal-po-status-badge" class="badge">Pending</span>
                        </div>
                        <button id="close-po-detail-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <div id="po-detail-content" class="py-3">
                        <!-- Populated dynamically -->
                    </div>

                    <div class="modal-footer flex justify-between items-center border-top pt-3">
                        <div class="flex gap-2">
                            <button type="button" id="modal-export-xml-btn" class="btn btn-secondary btn-sm">
                                📥 Export Official XML
                            </button>
                            <button type="button" id="modal-receive-goods-btn" class="btn btn-primary btn-sm hidden">
                                📦 Receive Goods
                            </button>
                        </div>
                        <button type="button" id="close-po-detail-btn2" class="btn btn-secondary btn-sm">Close</button>
                    </div>
                </div>
            </div>

            <!-- Receive Goods Modal -->
            <div id="receive-goods-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <div>
                            <h2 class="text-lg font-bold">Receive Goods & Update Inventory</h2>
                            <p class="text-xs text-muted" id="receive-modal-subtitle">Incoming delivery verification</p>
                        </div>
                        <button id="close-receive-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="receive-goods-form" class="mt-3">
                        <div class="alert-banner alert-info mb-3 text-xs rounded">
                            <span>ℹ️ Received quantities will be atomically incremented in product inventory, trigger dynamic stock level recalculation, and generate immutable stock movement ledger records.</span>
                        </div>

                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-bold uppercase tracking-wider">Ordered Items Verification</h3>
                            <button type="button" id="receive-all-remaining-btn" class="btn btn-secondary btn-sm">
                                Receive All Remaining
                            </button>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="data-table" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Item / SKU</th>
                                        <th class="text-center">Ordered</th>
                                        <th class="text-center">Already Rec'd</th>
                                        <th class="text-center">Remaining</th>
                                        <th class="text-right" style="width: 140px;">Quantity Receiving</th>
                                    </tr>
                                </thead>
                                <tbody id="receive-items-tbody">
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>

                        <div class="form-group mb-3">
                            <label for="receive-notes" class="text-xs font-semibold text-muted block mb-1">Receipt Notes / Delivery Slip Reference</label>
                            <input type="text" id="receive-notes" class="form-control" placeholder="e.g. Delivered via Apex van driver Juan, DR #9812">
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-receive-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="confirm-receive-btn" class="btn btn-primary">
                                📥 Commit Received Goods to Stock
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
            this.loadOrders(),
            this.loadSuppliers(),
            this.loadProducts()
        ]);
    },

    bindEvents() {
        // Search Filter (Debounced)
        let searchTimer;
        document.getElementById('filter-po-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.loadOrders();
            }, 300);
        });

        // Status Filter
        document.getElementById('filter-po-status')?.addEventListener('change', (e) => {
            this.state.filters.status = e.target.value;
            this.loadOrders();
        });

        // Refresh Button
        document.getElementById('refresh-po-btn')?.addEventListener('click', () => {
            this.loadOrders();
        });

        // Open New PO Modal
        document.getElementById('create-po-btn')?.addEventListener('click', () => {
            this.openNewPoModal();
        });

        // Close New PO Modal
        document.getElementById('close-new-po-btn')?.addEventListener('click', () => {
            document.getElementById('new-po-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-new-po-btn')?.addEventListener('click', () => {
            document.getElementById('new-po-modal')?.classList.add('hidden');
        });

        // Close PO Detail Modal
        document.getElementById('close-po-detail-btn')?.addEventListener('click', () => {
            document.getElementById('po-detail-modal')?.classList.add('hidden');
        });
        document.getElementById('close-po-detail-btn2')?.addEventListener('click', () => {
            document.getElementById('po-detail-modal')?.classList.add('hidden');
        });

        // Close Receive Modal
        document.getElementById('close-receive-modal-btn')?.addEventListener('click', () => {
            document.getElementById('receive-goods-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-receive-btn')?.addEventListener('click', () => {
            document.getElementById('receive-goods-modal')?.classList.add('hidden');
        });

        // Add Line Item in New PO Modal
        document.getElementById('add-line-item-btn')?.addEventListener('click', () => {
            this.state.newLineItems.push({
                product_id: '',
                quantity: 1,
                cost: 0.00,
                total: 0.00
            });
            this.renderPoFormLineItems();
        });

        // Line Item Row Changes (Product select, Quantity, Cost)
        document.getElementById('po-line-items-tbody')?.addEventListener('change', (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const index = parseInt(row.dataset.index, 10);

            if (e.target.classList.contains('po-item-product-select')) {
                const prodId = parseInt(e.target.value, 10);
                const prod = this.state.products.find(p => p.id === prodId);
                if (prod) {
                    this.state.newLineItems[index].product_id = prodId;
                    this.state.newLineItems[index].cost = parseFloat(prod.default_cost || prod.price || 0);
                    this.state.newLineItems[index].total = this.state.newLineItems[index].quantity * this.state.newLineItems[index].cost;
                    this.renderPoFormLineItems();
                }
            }
        });

        document.getElementById('po-line-items-tbody')?.addEventListener('input', (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const index = parseInt(row.dataset.index, 10);

            if (e.target.classList.contains('po-item-qty-input')) {
                const qty = parseInt(e.target.value, 10) || 0;
                this.state.newLineItems[index].quantity = qty;
                this.state.newLineItems[index].total = qty * this.state.newLineItems[index].cost;
                this.updatePoFormTotals();
            }

            if (e.target.classList.contains('po-item-cost-input')) {
                const cost = parseFloat(e.target.value) || 0.00;
                this.state.newLineItems[index].cost = cost;
                this.state.newLineItems[index].total = this.state.newLineItems[index].quantity * cost;
                this.updatePoFormTotals();
            }
        });

        // Remove Line Item
        document.getElementById('po-line-items-tbody')?.addEventListener('click', (e) => {
            const delBtn = e.target.closest('.delete-po-line-btn');
            if (delBtn) {
                const row = delBtn.closest('tr');
                const index = parseInt(row.dataset.index, 10);
                if (this.state.newLineItems.length > 1) {
                    this.state.newLineItems.splice(index, 1);
                    this.renderPoFormLineItems();
                } else {
                    State.setAlert('At least one line item is required.', 'warning', 2500);
                }
            }
        });

        // Submit New PO Form
        document.getElementById('new-po-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleCreatePurchaseOrder();
        });

        // PO Table Action Buttons (View, Receive, Export XML, Status Transition)
        document.getElementById('purchase-orders-tbody')?.addEventListener('click', (e) => {
            const viewBtn = e.target.closest('.view-po-btn');
            if (viewBtn) {
                const poId = parseInt(viewBtn.dataset.id, 10);
                this.openPoDetailModal(poId);
                return;
            }

            const receiveBtn = e.target.closest('.receive-po-btn');
            if (receiveBtn) {
                const poId = parseInt(receiveBtn.dataset.id, 10);
                this.openReceiveGoodsModal(poId);
                return;
            }

            const exportBtn = e.target.closest('.export-po-xml-btn');
            if (exportBtn) {
                const poId = parseInt(exportBtn.dataset.id, 10);
                const poNum = exportBtn.dataset.poNumber || `PO-${poId}`;
                this.exportPoXml(poId, poNum);
                return;
            }

            const approveBtn = e.target.closest('.approve-po-btn');
            if (approveBtn) {
                const poId = parseInt(approveBtn.dataset.id, 10);
                if (confirm('Are you sure you want to approve this purchase order for vendor fulfillment?')) {
                    this.updatePoStatus(poId, 'Approved', 'Purchase order approved by user.');
                }
                return;
            }

            const cancelBtn = e.target.closest('.cancel-po-btn');
            if (cancelBtn) {
                const poId = parseInt(cancelBtn.dataset.id, 10);
                const reason = prompt('Please enter cancellation reason:', 'Procurement requirements changed');
                if (reason) {
                    this.updatePoStatus(poId, 'Cancelled', reason);
                }
                return;
            }
        });

        // Detail Modal Quick Actions (Export XML, Receive)
        document.getElementById('modal-export-xml-btn')?.addEventListener('click', () => {
            if (this.state.activePoDetail) {
                this.exportPoXml(this.state.activePoDetail.id, this.state.activePoDetail.po_number);
            }
        });

        document.getElementById('modal-receive-goods-btn')?.addEventListener('click', () => {
            if (this.state.activePoDetail) {
                document.getElementById('po-detail-modal')?.classList.add('hidden');
                this.openReceiveGoodsModal(this.state.activePoDetail.id);
            }
        });

        // Receive All Remaining Shortcut in Receive Modal
        document.getElementById('receive-all-remaining-btn')?.addEventListener('click', () => {
            document.querySelectorAll('.receive-qty-input').forEach(input => {
                const max = parseInt(input.getAttribute('max'), 10) || 0;
                input.value = max;
            });
        });

        // Submit Receive Goods Form
        document.getElementById('receive-goods-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleCommitReceivedGoods();
        });
    },

    async loadOrders() {
        try {
            const params = {};
            if (this.state.filters.status) params.status = this.state.filters.status;
            if (this.state.filters.search) params.search = this.state.filters.search;

            const res = await apiClient.get('purchase-orders', params);
            if (res.success) {
                this.state.orders = res.data || [];
                this.renderOrdersTable();
                this.updateMetrics();
            }
        } catch (err) {
            console.error('Failed to load purchase orders', err);
            document.getElementById('purchase-orders-tbody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-danger">Failed to load purchase orders: ${err.message}</td>
                </tr>
            `;
        }
    },

    async loadSuppliers() {
        try {
            const res = await apiClient.get('suppliers', { status: 'Approved' });
            if (res.success) {
                this.state.suppliers = res.data || [];
                const select = document.getElementById('po-supplier-select');
                if (select) {
                    const opts = this.state.suppliers.map(s => `
                        <option value="${s.id}">${s.name} (${s.default_payment_terms || 'Cash'})</option>
                    `).join('');
                    select.innerHTML = '<option value="">Select an approved supplier...</option>' + opts;
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

    updateMetrics() {
        const orders = this.state.orders;
        const total = orders.length;
        const pending = orders.filter(o => o.status === 'Pending').length;
        const awaiting = orders.filter(o => o.status === 'Approved' || o.status === 'Partially Received').length;
        const totalVal = orders.reduce((sum, o) => sum + parseFloat(o.total_amount || 0), 0);

        const elTotal = document.getElementById('metric-total-pos');
        const elPending = document.getElementById('metric-pending-pos');
        const elAwaiting = document.getElementById('metric-awaiting-pos');
        const elValue = document.getElementById('metric-total-value');

        if (elTotal) elTotal.textContent = total;
        if (elPending) elPending.textContent = pending;
        if (elAwaiting) elAwaiting.textContent = awaiting;
        if (elValue) elValue.textContent = `₱${totalVal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
    },

    renderOrdersTable() {
        const tbody = document.getElementById('purchase-orders-tbody');
        if (!tbody) return;

        if (this.state.orders.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">No purchase orders found matching the filter criteria.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.orders.map(po => {
            const totalFmt = parseFloat(po.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const dateStr = po.order_date ? new Date(po.order_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
            const badgeClass = this.getStatusBadgeClass(po.status);
            const canReceive = (po.status === 'Approved' || po.status === 'Partially Received');
            const canApprove = (po.status === 'Pending');

            return `
                <tr>
                    <td class="font-mono font-bold text-primary">${po.po_number}</td>
                    <td>
                        <div class="font-semibold">${po.supplier_name}</div>
                        <div class="text-xs text-muted">${po.po_type} Requisition</div>
                    </td>
                    <td>
                        <span class="badge ${po.po_type === 'Consignment' ? 'badge-info' : 'badge-secondary'} text-xs">
                            ${po.po_type}
                        </span>
                    </td>
                    <td class="text-muted text-sm">${dateStr}</td>
                    <td>
                        <span class="font-semibold">${po.item_count} items</span>
                        <div class="text-xs text-muted font-mono">${po.total_units_received || 0} / ${po.total_units_ordered || 0} units rec'd</div>
                    </td>
                    <td class="text-right font-mono font-bold">₱${totalFmt}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass}">${po.status}</span>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <button class="btn btn-secondary btn-sm view-po-btn" data-id="${po.id}" title="View Details">
                                👁️ Details
                            </button>
                            ${canReceive ? `
                                <button class="btn btn-primary btn-sm receive-po-btn" data-id="${po.id}" title="Receive Delivery">
                                    📦 Receive
                                </button>
                            ` : ''}
                            <button class="btn btn-secondary btn-sm export-po-xml-btn" data-id="${po.id}" data-po-number="${po.po_number}" title="Export Supplier XML">
                                📄 XML
                            </button>
                            ${canApprove ? `
                                <button class="btn btn-secondary btn-sm approve-po-btn text-success" data-id="${po.id}" title="Approve PO">
                                    ✓ Approve
                                </button>
                                <button class="btn btn-secondary btn-sm cancel-po-btn text-danger" data-id="${po.id}" title="Cancel PO">
                                    ✕
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getStatusBadgeClass(status) {
        switch (status) {
            case 'Completed': return 'badge-success';
            case 'Approved': return 'badge-info';
            case 'Partially Received': return 'badge-info';
            case 'Pending': return 'badge-warning';
            case 'Cancelled': return 'badge-danger';
            default: return 'badge-secondary';
        }
    },

    openNewPoModal() {
        this.state.newLineItems = [
            { product_id: '', quantity: 1, cost: 0.00, total: 0.00 }
        ];
        document.getElementById('new-po-error').style.display = 'none';
        document.getElementById('new-po-form').reset();
        this.renderPoFormLineItems();
        document.getElementById('new-po-modal')?.classList.remove('hidden');
    },

    renderPoFormLineItems() {
        const tbody = document.getElementById('po-line-items-tbody');
        if (!tbody) return;

        const productOptions = this.state.products.map(p => `
            <option value="${p.id}">${p.item_code} - ${p.name} (Stock: ${p.quantity} ${p.unit})</option>
        `).join('');

        tbody.innerHTML = this.state.newLineItems.map((item, idx) => {
            return `
                <tr data-index="${idx}">
                    <td>
                        <select class="form-control form-control-sm po-item-product-select" required>
                            <option value="">Select product to order...</option>
                            ${this.state.products.map(p => `
                                <option value="${p.id}" ${p.id === item.product_id ? 'selected' : ''}>
                                    ${p.item_code} - ${p.name}
                                </option>
                            `).join('')}
                        </select>
                    </td>
                    <td class="text-right">
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-right font-mono po-item-cost-input" value="${item.cost.toFixed(2)}" required>
                    </td>
                    <td class="text-center">
                        <input type="number" step="1" min="1" class="form-control form-control-sm text-center font-mono po-item-qty-input" value="${item.quantity}" required>
                    </td>
                    <td class="text-right font-mono font-bold po-line-total-cell">
                        ₱${item.total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-secondary btn-icon delete-po-line-btn text-danger" title="Remove Item">✕</button>
                    </td>
                </tr>
            `;
        }).join('');

        this.updatePoFormTotals();
    },

    updatePoFormTotals() {
        let grandTotal = 0;
        this.state.newLineItems.forEach((item, idx) => {
            item.total = item.quantity * item.cost;
            grandTotal += item.total;

            const row = document.querySelector(`tr[data-index="${idx}"]`);
            if (row) {
                const totalCell = row.querySelector('.po-line-total-cell');
                if (totalCell) {
                    totalCell.textContent = `₱${item.total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
                }
            }
        });

        const grandEl = document.getElementById('po-form-grand-total');
        if (grandEl) {
            grandEl.textContent = `₱${grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        }
    },

    async handleCreatePurchaseOrder() {
        const errEl = document.getElementById('new-po-error');
        const submitBtn = document.getElementById('save-po-btn');
        errEl.style.display = 'none';

        const supplierId = parseInt(document.getElementById('po-supplier-select').value, 10);
        if (!supplierId) {
            errEl.textContent = 'Please select a valid supplier.';
            errEl.style.display = 'block';
            return;
        }

        const validItems = this.state.newLineItems.filter(i => i.product_id && i.quantity > 0);
        if (validItems.length === 0) {
            errEl.textContent = 'Please specify at least one product with quantity greater than zero.';
            errEl.style.display = 'block';
            return;
        }

        const payload = {
            supplier_id: supplierId,
            po_type: document.getElementById('po-type-select').value,
            consignment_method: document.getElementById('po-consignment-method').value,
            notes: document.getElementById('po-notes').value.trim(),
            terms_and_conditions: document.getElementById('po-terms').value.trim(),
            items: validItems.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity,
                cost: i.cost
            }))
        };

        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting Requisition...';

            const response = await apiClient.post('purchase-orders', payload);
            if (response.success) {
                document.getElementById('new-po-modal')?.classList.add('hidden');
                State.setAlert(`Purchase Order ${response.data?.po_number || ''} issued successfully!`, 'success');
                await this.loadOrders();
            }
        } catch (err) {
            errEl.textContent = err.message || 'Failed to create purchase order.';
            errEl.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '📝 Submit Purchase Order';
        }
    },

    async openPoDetailModal(poId) {
        try {
            const res = await apiClient.get(`purchase-orders/${poId}`);
            if (!res.success || !res.data) {
                throw new Error(res.message || 'Failed to fetch purchase order.');
            }

            const po = res.data;
            this.state.activePoDetail = po;

            document.getElementById('modal-po-number').textContent = po.po_number;
            const badge = document.getElementById('modal-po-status-badge');
            badge.className = `badge ${this.getStatusBadgeClass(po.status)}`;
            badge.textContent = po.status;

            const receiveGoodsBtn = document.getElementById('modal-receive-goods-btn');
            if (receiveGoodsBtn) {
                if (po.status === 'Approved' || po.status === 'Partially Received') {
                    receiveGoodsBtn.classList.remove('hidden');
                } else {
                    receiveGoodsBtn.classList.add('hidden');
                }
            }

            const totalFmt = parseFloat(po.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const dateStr = po.order_date ? new Date(po.order_date).toLocaleDateString('en-PH', { dateStyle: 'long' }) : 'N/A';

            let itemsHtml = (po.items || []).map(i => {
                const costFmt = parseFloat(i.cost).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                const lineTotalFmt = parseFloat(i.total).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                const isComplete = parseInt(i.quantity_received, 10) >= parseInt(i.quantity, 10);

                return `
                    <tr>
                        <td>
                            <span class="font-bold">${i.product_name}</span>
                            <div class="text-xs text-muted font-mono">${i.item_code}</div>
                        </td>
                        <td class="text-right font-mono">₱${costFmt}</td>
                        <td class="text-center font-mono">${i.quantity} ${i.unit || 'pc'}</td>
                        <td class="text-center font-mono">
                            <span class="${isComplete ? 'text-success font-bold' : ''}">${i.quantity_received}</span>
                        </td>
                        <td class="text-center font-mono">
                            <span class="${i.remaining_quantity > 0 ? 'text-warning font-bold' : 'text-muted'}">
                                ${i.remaining_quantity}
                            </span>
                        </td>
                        <td class="text-right font-mono font-bold">₱${lineTotalFmt}</td>
                    </tr>
                `;
            }).join('');

            let historyHtml = (po.history || []).map(h => `
                <div class="p-2 border-bottom text-xs flex justify-between items-center">
                    <div>
                        <span class="badge ${this.getStatusBadgeClass(h.status)} mr-2">${h.status}</span>
                        <span>${h.notes}</span>
                    </div>
                    <div class="text-right text-muted">
                        <div>${h.updated_by}</div>
                        <div>${new Date(h.timestamp).toLocaleString('en-PH')}</div>
                    </div>
                </div>
            `).join('');

            document.getElementById('po-detail-content').innerHTML = `
                <!-- Supplier & Order Meta -->
                <div class="grid grid-cols-2 gap-4 p-3 bg-surface rounded mb-3 text-sm">
                    <div>
                        <span class="text-xs uppercase font-bold text-muted block mb-1">Vendor Details</span>
                        <div class="font-bold text-base">${po.supplier_name}</div>
                        <div class="text-muted text-xs">${po.supplier_address || 'No registered address'}</div>
                        <div class="text-muted text-xs">Contact: ${po.supplier_contact_person || 'N/A'} | ${po.supplier_contact_number || 'N/A'}</div>
                        <div class="text-muted text-xs">Terms: <strong>${po.supplier_payment_terms || 'Cash'}</strong></div>
                    </div>
                    <div>
                        <span class="text-xs uppercase font-bold text-muted block mb-1">Order Summary</span>
                        <div><strong>Order Date:</strong> ${dateStr}</div>
                        <div><strong>Order Type:</strong> ${po.po_type} (${po.consignment_method})</div>
                        ${po.terms_and_conditions ? `<div><strong>Terms:</strong> ${po.terms_and_conditions}</div>` : ''}
                        ${po.notes ? `<div><strong>Notes:</strong> ${po.notes}</div>` : ''}
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="table-responsive mb-3">
                    <table class="data-table text-xs">
                        <thead>
                            <tr>
                                <th>Item / SKU</th>
                                <th class="text-right">Unit Cost</th>
                                <th class="text-center">Ordered</th>
                                <th class="text-center">Received</th>
                                <th class="text-center">Remaining</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-right font-bold text-sm">Grand Total:</th>
                                <th class="text-right font-bold text-sm text-primary font-mono">₱${totalFmt}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- History Timeline -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted mb-2">Audit & Status History</h3>
                    <div class="border rounded bg-surface overflow-hidden">
                        ${historyHtml || '<div class="p-2 text-muted text-xs">No status history records found.</div>'}
                    </div>
                </div>
            `;

            document.getElementById('po-detail-modal')?.classList.remove('hidden');

        } catch (err) {
            State.setAlert('Error loading purchase order details: ' + err.message, 'danger');
        }
    },

    async openReceiveGoodsModal(poId) {
        try {
            const res = await apiClient.get(`purchase-orders/${poId}`);
            if (!res.success || !res.data) {
                throw new Error('Purchase order not found');
            }

            const po = res.data;
            this.state.receivingPo = po;

            document.getElementById('receive-modal-subtitle').textContent = `Receiving Delivery for ${po.po_number} (${po.supplier_name})`;

            const itemsWithRemaining = (po.items || []).filter(i => parseInt(i.remaining_quantity, 10) > 0);

            if (itemsWithRemaining.length === 0) {
                State.setAlert(`All items for ${po.po_number} have already been fully received!`, 'info');
                return;
            }

            const tbody = document.getElementById('receive-items-tbody');
            tbody.innerHTML = itemsWithRemaining.map(i => {
                return `
                    <tr data-product-id="${i.product_id}">
                        <td>
                            <div class="font-bold">${i.product_name}</div>
                            <div class="text-xs text-muted font-mono">${i.item_code}</div>
                        </td>
                        <td class="text-center font-mono">${i.quantity}</td>
                        <td class="text-center font-mono">${i.quantity_received}</td>
                        <td class="text-center font-mono font-bold text-warning">${i.remaining_quantity}</td>
                        <td class="text-right">
                            <input type="number" 
                                   class="form-control form-control-sm text-center font-mono font-bold receive-qty-input" 
                                   data-product-id="${i.product_id}"
                                   min="0" 
                                   max="${i.remaining_quantity}" 
                                   value="${i.remaining_quantity}">
                        </td>
                    </tr>
                `;
            }).join('');

            document.getElementById('receive-notes').value = '';
            document.getElementById('receive-goods-modal')?.classList.remove('hidden');

        } catch (err) {
            State.setAlert('Failed to open receive dialog: ' + err.message, 'danger');
        }
    },

    async handleCommitReceivedGoods() {
        if (!this.state.receivingPo) return;

        const poId = this.state.receivingPo.id;
        const confirmBtn = document.getElementById('confirm-receive-btn');
        const notes = document.getElementById('receive-notes').value.trim();

        const receivedItems = [];
        document.querySelectorAll('.receive-qty-input').forEach(input => {
            const prodId = parseInt(input.dataset.productId, 10);
            const qty = parseInt(input.value, 10) || 0;
            if (qty > 0) {
                receivedItems.push({
                    product_id: prodId,
                    quantity_received: qty
                });
            }
        });

        if (receivedItems.length === 0) {
            State.setAlert('Please enter a receiving quantity greater than 0 for at least one item.', 'warning');
            return;
        }

        try {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Updating Inventory...';

            const payload = {
                items: receivedItems,
                notes: notes
            };

            const response = await apiClient.post(`purchase-orders/${poId}/receive`, payload);

            if (response.success) {
                document.getElementById('receive-goods-modal')?.classList.add('hidden');
                State.setAlert('Stock delivery committed! Inventory stock and ledger records updated.', 'success');
                await this.loadOrders();
            }
        } catch (err) {
            State.setAlert('Stock receiving error: ' + err.message, 'danger');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '📥 Commit Received Goods to Stock';
        }
    },

    async updatePoStatus(poId, newStatus, notes) {
        try {
            const res = await apiClient.put(`purchase-orders/${poId}/status`, {
                status: newStatus,
                notes: notes
            });

            if (res.success) {
                State.setAlert(`Purchase Order status updated to '${newStatus}'.`, 'success');
                await this.loadOrders();
            }
        } catch (err) {
            State.setAlert('Failed to update status: ' + err.message, 'danger');
        }
    },

    /**
     * XML Data Interchange: Fetch XML export from backend and trigger browser download
     */
    async exportPoXml(poId, poNumber) {
        try {
            State.setAlert(`Generating XML interchange document for ${poNumber}...`, 'info', 2000);
            const xmlContent = await apiClient.get(`purchase-orders/${poId}/export-xml`);

            // Create client-side Blob and trigger download
            const blob = new Blob([xmlContent], { type: 'application/xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = `${poNumber}.xml`;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);

            State.setAlert(`Official Purchase Order XML file downloaded: ${poNumber}.xml`, 'success', 4000);
        } catch (err) {
            State.setAlert('XML Export failed: ' + err.message, 'danger');
        }
    }
};
