import { apiClient } from '../client.js';
import { State } from '../state.js';

export const ProductsPage = {
    state: {
        products: [],
        categories: [],
        brands: [],
        suppliers: [],
        pagination: { total: 0, page: 1, limit: 15, pages: 1 },
        filters: {
            search: '',
            category_id: '',
            brand_id: '',
            stock_status: ''
        },
        editingProduct: null,
        loading: false
    },

    render() {
        const canManage = State.hasPermission('canManageInventory');

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title">Inventory & Products</h1>
                        <p class="text-muted text-sm">Monitor stock status, pricing, and supplier procurement costs.</p>
                    </div>
                    ${canManage ? `
                        <button id="add-product-btn" class="btn btn-primary">
                            <span>+</span> Add New Product
                        </button>
                    ` : ''}
                </div>

                <!-- Metrics Ribbon -->
                <div class="grid grid-cols-4 gap-3 mb-4" id="inventory-metrics">
                    <div class="metric-card">
                        <span class="metric-label">Total Catalog Items</span>
                        <span class="metric-value" id="metric-total">0</span>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Healthy Stock (>15)</span>
                        <span class="metric-value text-success" id="metric-healthy">0</span>
                    </div>
                    <div class="metric-card border-warning">
                        <span class="metric-label">Low Stock (6–15)</span>
                        <span class="metric-value text-warning" id="metric-low">0</span>
                    </div>
                    <div class="metric-card border-danger">
                        <span class="metric-label">Critical / Out of Stock (≤5)</span>
                        <span class="metric-value text-danger" id="metric-critical">0</span>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-4 gap-3 items-center">
                        <div>
                            <input type="text" id="filter-search" class="form-control" placeholder="🔍 Search name or code..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-category" class="form-control">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                        <div>
                            <select id="filter-brand" class="form-control">
                                <option value="">All Brands</option>
                            </select>
                        </div>
                        <div>
                            <select id="filter-stock-status" class="form-control">
                                <option value="">All Stock Statuses</option>
                                <option value="Healthy">Healthy (>15)</option>
                                <option value="Low">Low (6–15)</option>
                                <option value="Critical">Critical (1–5)</option>
                                <option value="Out of Stock">Out of Stock (0)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Products Table Card -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="products-table">
                            <thead>
                                <tr>
                                    <th>Item Code</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Brand</th>
                                    <th class="text-right">Price (₱)</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="products-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Loading inventory data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pagination-footer flex justify-between items-center p-3 border-top">
                        <span class="text-muted text-sm" id="pagination-summary">Showing 0 of 0 products</span>
                        <div class="pagination-controls flex gap-2">
                            <button id="prev-page-btn" class="btn btn-secondary btn-sm" disabled>« Previous</button>
                            <span id="current-page-display" class="px-2 py-1 text-sm font-semibold">Page 1</span>
                            <button id="next-page-btn" class="btn btn-secondary btn-sm" disabled>Next »</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Product Modal -->
            <div id="product-modal" class="modal-backdrop hidden">
                <div class="modal-dialog modal-lg">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <h2 id="modal-title" class="text-lg font-bold">Add New Product</h2>
                        <button id="close-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="product-form" class="mt-3">
                        <div id="modal-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="prod-code">Item Code / SKU *</label>
                                <input type="text" id="prod-code" class="form-control" placeholder="e.g. TIRE-MICH-001" required>
                            </div>
                            <div class="form-group">
                                <label for="prod-name">Product Name *</label>
                                <input type="text" id="prod-name" class="form-control" placeholder="e.g. Michelin City Grip 2 110/80-14" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="prod-category">Category *</label>
                                <select id="prod-category" class="form-control" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="prod-brand">Brand *</label>
                                <select id="prod-brand" class="form-control" required>
                                    <option value="">Select Brand</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div class="form-group">
                                <label for="prod-price">Selling Price (₱) *</label>
                                <input type="number" step="0.01" min="0" id="prod-price" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="form-group">
                                <label for="prod-cost">Default Unit Cost (₱)</label>
                                <input type="number" step="0.01" min="0" id="prod-cost" class="form-control" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="prod-unit">Unit of Measure</label>
                                <input type="text" id="prod-unit" class="form-control" placeholder="pc, set, bottle, pair" value="pc">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div class="form-group">
                                <label for="prod-quantity">Initial Stock Quantity</label>
                                <input type="number" min="0" id="prod-quantity" class="form-control" placeholder="0" value="0">
                            </div>
                            <div class="form-group">
                                <label for="prod-max-stock">Target Max Stock</label>
                                <input type="number" min="1" id="prod-max-stock" class="form-control" placeholder="100" value="100">
                            </div>
                            <div class="form-group flex items-center pt-4">
                                <label class="checkbox-label flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" id="prod-is-serialized">
                                    <span>Track Serial Numbers</span>
                                </label>
                            </div>
                        </div>

                        <!-- Supplier Costs Mapping Section -->
                        <div class="border-top pt-3 mt-3">
                            <h3 class="text-sm font-semibold mb-2">Supplier Procurement Cost Mapping</h3>
                            <div class="grid grid-cols-3 gap-2 mb-2">
                                <div>
                                    <select id="prod-supplier-select" class="form-control form-control-sm">
                                        <option value="">Select Supplier</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="number" step="0.01" min="0" id="prod-supplier-cost" class="form-control form-control-sm" placeholder="Supplier Cost (₱)">
                                </div>
                                <div>
                                    <input type="text" id="prod-supplier-note" class="form-control form-control-sm" placeholder="Notes (e.g. Primary Vendor)">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3 mt-4">
                            <button type="button" id="cancel-modal-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-product-btn" class="btn btn-primary">Save Product</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await Promise.all([
            this.loadCategories(),
            this.loadBrands(),
            this.loadSuppliers()
        ]);
        await this.loadProducts();
    },

    bindEvents() {
        // Search Filter (Debounced)
        let searchTimer;
        document.getElementById('filter-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.state.pagination.page = 1;
                this.loadProducts();
            }, 300);
        });

        // Dropdown Filters
        document.getElementById('filter-category')?.addEventListener('change', (e) => {
            this.state.filters.category_id = e.target.value;
            this.state.pagination.page = 1;
            this.loadProducts();
        });

        document.getElementById('filter-brand')?.addEventListener('change', (e) => {
            this.state.filters.brand_id = e.target.value;
            this.state.pagination.page = 1;
            this.loadProducts();
        });

        document.getElementById('filter-stock-status')?.addEventListener('change', (e) => {
            this.state.filters.stock_status = e.target.value;
            this.state.pagination.page = 1;
            this.loadProducts();
        });

        // Pagination buttons
        document.getElementById('prev-page-btn')?.addEventListener('click', () => {
            if (this.state.pagination.page > 1) {
                this.state.pagination.page--;
                this.loadProducts();
            }
        });

        document.getElementById('next-page-btn')?.addEventListener('click', () => {
            if (this.state.pagination.page < this.state.pagination.pages) {
                this.state.pagination.page++;
                this.loadProducts();
            }
        });

        // Modal triggers
        document.getElementById('add-product-btn')?.addEventListener('click', () => {
            this.openModal(null);
        });

        document.getElementById('close-modal-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('cancel-modal-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        // Form Submit
        document.getElementById('product-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProduct();
        });

        // Event delegation for Edit and Delete buttons on table rows
        document.getElementById('products-tbody')?.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-prod-btn');
            if (editBtn) {
                const id = parseInt(editBtn.dataset.id, 10);
                this.openModal(id);
                return;
            }

            const deleteBtn = e.target.closest('.delete-prod-btn');
            if (deleteBtn) {
                const id = parseInt(deleteBtn.dataset.id, 10);
                const name = deleteBtn.dataset.name;
                this.confirmDelete(id, name);
                return;
            }
        });
    },

    async loadCategories() {
        try {
            const res = await apiClient.get('categories');
            if (res.success) {
                this.state.categories = res.data;
                const selectFilter = document.getElementById('filter-category');
                const modalSelect = document.getElementById('prod-category');

                const opts = res.data.map(c => `<option value="${c.id}">${c.name} (${c.product_count || 0})</option>`).join('');
                if (selectFilter) selectFilter.innerHTML = '<option value="">All Categories</option>' + opts;
                if (modalSelect) modalSelect.innerHTML = '<option value="">Select Category</option>' + opts;
            }
        } catch (err) {
            console.error('Failed to load categories', err);
        }
    },

    async loadBrands() {
        try {
            const res = await apiClient.get('brands');
            if (res.success) {
                this.state.brands = res.data;
                const selectFilter = document.getElementById('filter-brand');
                const modalSelect = document.getElementById('prod-brand');

                const opts = res.data.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
                if (selectFilter) selectFilter.innerHTML = '<option value="">All Brands</option>' + opts;
                if (modalSelect) modalSelect.innerHTML = '<option value="">Select Brand</option>' + opts;
            }
        } catch (err) {
            console.error('Failed to load brands', err);
        }
    },

    async loadSuppliers() {
        try {
            const res = await apiClient.get('suppliers');
            if (res.success) {
                this.state.suppliers = res.data;
                const supSelect = document.getElementById('prod-supplier-select');
                if (supSelect) {
                    supSelect.innerHTML = '<option value="">Select Supplier</option>' +
                        res.data.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
                }
            }
        } catch (err) {
            console.error('Failed to load suppliers', err);
        }
    },

    async loadProducts() {
        const tbody = document.getElementById('products-tbody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">Refreshing products...</td></tr>`;

        try {
            const params = {
                page: this.state.pagination.page,
                limit: this.state.pagination.limit,
                search: this.state.filters.search,
                category_id: this.state.filters.category_id,
                brand_id: this.state.filters.brand_id,
                stock_status: this.state.filters.stock_status
            };

            const res = await apiClient.get('products', params);
            if (res.success) {
                this.state.products = res.data;
                this.state.pagination = res.pagination;
                this.renderTable(res.data);
                this.updatePaginationUI();
                this.updateMetrics();
            }
        } catch (err) {
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${err.message || 'Error fetching products'}</td></tr>`;
            }
        }
    },

    renderTable(products) {
        const tbody = document.getElementById('products-tbody');
        if (!tbody) return;

        if (products.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No products found matching your filter criteria.</td></tr>`;
            return;
        }

        const canManage = State.hasPermission('canManageInventory');

        tbody.innerHTML = products.map(p => {
            const badgeClass = this.getBadgeClass(p.stock_status);
            const priceFmt = parseFloat(p.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });

            return `
                <tr data-id="${p.id}">
                    <td class="font-mono text-xs font-semibold text-primary">${p.item_code}</td>
                    <td class="font-medium">
                        ${p.name}
                        ${p.is_serialized ? '<span class="badge badge-secondary ml-1" title="Serialized Item">SN</span>' : ''}
                    </td>
                    <td class="text-muted text-sm">${p.category_name || '—'}</td>
                    <td class="text-muted text-sm">${p.brand_name || '—'}</td>
                    <td class="text-right font-mono font-semibold">₱${priceFmt}</td>
                    <td class="text-center font-bold">${p.quantity} <span class="text-xs text-muted font-normal">${p.unit || 'pc'}</span></td>
                    <td class="text-center">
                        <span class="badge ${badgeClass}">${p.stock_status}</span>
                    </td>
                    <td class="text-right actions-cell">
                        ${canManage ? `
                            <button class="btn btn-icon edit-prod-btn" data-id="${p.id}" title="Edit Product">✏️</button>
                            <button class="btn btn-icon text-danger delete-prod-btn" data-id="${p.id}" data-name="${p.name}" title="Delete Product">🗑️</button>
                        ` : '<span class="text-xs text-muted">View Only</span>'}
                    </td>
                </tr>
            `;
        }).join('');
    },

    getBadgeClass(status) {
        switch (status) {
            case 'Healthy': return 'badge-success';
            case 'Low': return 'badge-warning';
            case 'Critical': return 'badge-danger';
            case 'Out of Stock': return 'badge-danger badge-outline';
            default: return 'badge-secondary';
        }
    },

    updatePaginationUI() {
        const { total, page, limit, pages } = this.state.pagination;
        const start = total === 0 ? 0 : (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);

        const summary = document.getElementById('pagination-summary');
        const display = document.getElementById('current-page-display');
        const prevBtn = document.getElementById('prev-page-btn');
        const nextBtn = document.getElementById('next-page-btn');

        if (summary) summary.textContent = `Showing ${start}–${end} of ${total} products`;
        if (display) display.textContent = `Page ${page} of ${pages}`;
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= pages;
    },

    updateMetrics() {
        const prods = this.state.products;
        const total = this.state.pagination.total;
        
        let healthy = 0, low = 0, critical = 0;
        prods.forEach(p => {
            if (p.stock_status === 'Healthy') healthy++;
            else if (p.stock_status === 'Low') low++;
            else critical++;
        });

        document.getElementById('metric-total').textContent = total;
        document.getElementById('metric-healthy').textContent = healthy;
        document.getElementById('metric-low').textContent = low;
        document.getElementById('metric-critical').textContent = critical;
    },

    async openModal(id = null) {
        const modal = document.getElementById('product-modal');
        const title = document.getElementById('modal-title');
        const errorDiv = document.getElementById('modal-error');
        const form = document.getElementById('product-form');

        errorDiv.style.display = 'none';
        form.reset();

        this.state.editingProduct = id;

        if (id) {
            title.textContent = 'Edit Product Catalog Item';
            try {
                const res = await apiClient.get(`products/${id}`);
                if (res.success) {
                    const p = res.data;
                    document.getElementById('prod-code').value = p.item_code;
                    document.getElementById('prod-name').value = p.name;
                    document.getElementById('prod-category').value = p.category_id;
                    document.getElementById('prod-brand').value = p.brand_id;
                    document.getElementById('prod-price').value = p.price;
                    document.getElementById('prod-cost').value = p.default_cost || '';
                    document.getElementById('prod-unit').value = p.unit || 'pc';
                    document.getElementById('prod-quantity').value = p.quantity || 0;
                    document.getElementById('prod-max-stock').value = p.max_stock || 100;
                    document.getElementById('prod-is-serialized').checked = !!p.is_serialized;

                    // Supplier cost
                    if (p.supplier_costs && p.supplier_costs.length > 0) {
                        const firstCost = p.supplier_costs[0];
                        document.getElementById('prod-supplier-select').value = firstCost.supplier_id;
                        document.getElementById('prod-supplier-cost').value = firstCost.cost;
                        document.getElementById('prod-supplier-note').value = firstCost.note || '';
                    }
                }
            } catch (err) {
                State.setAlert('Failed to load product details.', 'danger');
                return;
            }
        } else {
            title.textContent = 'Add New Product';
        }

        modal.classList.remove('hidden');
    },

    closeModal() {
        const modal = document.getElementById('product-modal');
        modal.classList.add('hidden');
        this.state.editingProduct = null;
    },

    async saveProduct() {
        const errorDiv = document.getElementById('modal-error');
        const saveBtn = document.getElementById('save-product-btn');
        errorDiv.style.display = 'none';
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const item_code = document.getElementById('prod-code').value.trim();
        const name = document.getElementById('prod-name').value.trim();
        const category_id = parseInt(document.getElementById('prod-category').value, 10);
        const brand_id = parseInt(document.getElementById('prod-brand').value, 10);
        const price = parseFloat(document.getElementById('prod-price').value);
        const default_cost = parseFloat(document.getElementById('prod-cost').value) || 0.00;
        const unit = document.getElementById('prod-unit').value.trim() || 'pc';
        const quantity = parseInt(document.getElementById('prod-quantity').value, 10) || 0;
        const max_stock = parseInt(document.getElementById('prod-max-stock').value, 10) || 100;
        const is_serialized = document.getElementById('prod-is-serialized').checked ? 1 : 0;

        // Supplier cost mapping
        const supId = document.getElementById('prod-supplier-select').value;
        const supCost = document.getElementById('prod-supplier-cost').value;
        const supNote = document.getElementById('prod-supplier-note').value.trim();

        const supplier_costs = [];
        if (supId && supCost) {
            supplier_costs.push({
                supplier_id: parseInt(supId, 10),
                cost: parseFloat(supCost),
                note: supNote || 'Primary Supplier'
            });
        }

        const payload = {
            item_code,
            name,
            category_id,
            brand_id,
            price,
            default_cost,
            unit,
            quantity,
            max_stock,
            is_serialized,
            supplier_costs
        };

        try {
            if (this.state.editingProduct) {
                await apiClient.put(`products/${this.state.editingProduct}`, payload);
                State.setAlert(`Product "${name}" updated successfully.`, 'success');
            } else {
                await apiClient.post('products', payload);
                State.setAlert(`Product "${name}" created successfully.`, 'success');
            }

            this.closeModal();
            await this.loadProducts();
            await this.loadCategories(); // refresh product count aggregation
        } catch (err) {
            errorDiv.textContent = err.message || 'Failed to save product.';
            errorDiv.style.display = 'block';
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Product';
        }
    },

    async confirmDelete(id, name) {
        if (!confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
            return;
        }

        try {
            await apiClient.delete(`products/${id}`);
            State.setAlert(`Product "${name}" was deleted.`, 'info');
            await this.loadProducts();
            await this.loadCategories();
        } catch (err) {
            State.setAlert(err.message || 'Error deleting product.', 'danger');
        }
    }
};
