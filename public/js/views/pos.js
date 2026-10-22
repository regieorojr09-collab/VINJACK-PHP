import { apiClient } from '../client.js';
import { State } from '../state.js';

export const POSPage = {
    state: {
        products: [],
        categories: [],
        services: [],
        customers: [],
        activeTab: 'products', // 'products' or 'services'
        selectedCategoryId: '',
        searchQuery: '',
        cart: {
            items: [],    // { product_id, item_code, name, price, quantity, max_quantity, unit }
            services: []  // { service_id, name, charge }
        },
        selectedCustomer: null,
        selectedMotorcycle: null,
        tenderedAmount: 0,
        currentReceiptXml: null
    },

    render() {
        return `
            <div class="pos-layout">
                <!-- Left: Catalog & Services Selection -->
                <div class="pos-catalog-panel">
                    <!-- Catalog Header with Tab Switcher & Search -->
                    <div class="card p-3 mb-3">
                        <div class="flex justify-between items-center mb-3">
                            <div class="tab-pills flex gap-2">
                                <button class="btn btn-sm ${this.state.activeTab === 'products' ? 'btn-primary' : 'btn-secondary'}" id="tab-products-btn">
                                    📦 Products Catalog
                                </button>
                                <button class="btn btn-sm ${this.state.activeTab === 'services' ? 'btn-primary' : 'btn-secondary'}" id="tab-services-btn">
                                    🔧 Labor & Services
                                </button>
                            </div>
                            <div class="pos-search-wrapper" style="width: 260px;">
                                <input type="text" id="pos-search" class="form-control form-control-sm" placeholder="🔍 Search items or services..." value="${this.state.searchQuery}">
                            </div>
                        </div>

                        <!-- Category Filter Pills (Horizontal Scroll) -->
                        <div id="pos-category-pills" class="pos-category-pills flex flex-nowrap gap-1 overflow-x-auto whitespace-nowrap pb-1 ${this.state.activeTab === 'services' ? 'hidden' : ''}" style="overflow-x: auto; white-space: nowrap;">
                            <button class="pill-btn ${this.state.selectedCategoryId === '' ? 'active' : ''}" data-category-id="">All Categories</button>
                            <!-- Category pills rendered dynamically -->
                        </div>
                    </div>

                    <!-- Products / Services Grid -->
                    <div id="pos-grid" class="pos-items-grid">
                        <div class="text-center py-5 text-muted col-span-3">Loading catalog items...</div>
                    </div>
                </div>

                <!-- Right: Active Transaction Cart & Register -->
                <div class="pos-cart-panel">
                    <div class="card p-3 h-full flex flex-col justify-between">
                        <!-- Customer Selection Bar -->
                        <div class="border-bottom pb-3 mb-3">
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs uppercase font-bold text-muted">Customer & Vehicle</label>
                                <button id="change-customer-btn" class="text-xs text-primary font-semibold btn-link cursor-pointer">
                                    ${this.state.selectedCustomer ? 'Change' : '+ Select Customer'}
                                </button>
                            </div>
                            <div class="customer-info-box p-2 bg-surface rounded flex justify-between items-center">
                                <div>
                                    <div class="font-semibold text-sm" id="customer-name-display">
                                        ${this.state.selectedCustomer ? this.state.selectedCustomer.name : 'Walk-in Customer'}
                                    </div>
                                    <div class="text-xs text-muted" id="vehicle-plate-display">
                                        ${this.state.selectedMotorcycle ? `🏍️ ${this.state.selectedMotorcycle.plate_number} (${this.state.selectedMotorcycle.make} ${this.state.selectedMotorcycle.model})` : 'No vehicle linked'}
                                    </div>
                                </div>
                                <span class="badge badge-secondary text-xs">POS Register</span>
                            </div>
                        </div>

                        <!-- Cart Line Items Container (Scrollable) -->
                        <div class="cart-items-container flex-1 overflow-y-auto mb-3" id="cart-lines">
                            <div class="text-center py-5 text-muted">Cart is currently empty.</div>
                        </div>

                        <!-- Financial Summary & Checkout Action -->
                        <div class="border-top pt-3">
                            <div class="flex justify-between text-sm mb-1 text-muted">
                                <span>Products Subtotal</span>
                                <span id="cart-products-subtotal" class="font-mono">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-sm mb-2 text-muted">
                                <span>Services Subtotal</span>
                                <span id="cart-services-subtotal" class="font-mono">₱0.00</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold border-top pt-2 mb-3">
                                <span>Grand Total</span>
                                <span id="cart-grand-total" class="text-primary font-mono font-bold">₱0.00</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button id="clear-cart-btn" class="btn btn-secondary">Clear Cart</button>
                                <button id="checkout-btn" class="btn btn-primary" disabled>Proceed to Pay</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Picker Modal -->
            <div id="customer-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-2 mb-3">
                        <h2 class="font-bold">Select Customer & Motorcycle</h2>
                        <button id="close-cust-modal-btn" class="close-btn">&times;</button>
                    </div>
                    <div class="form-group mb-3">
                        <input type="text" id="cust-search-input" class="form-control" placeholder="Search customer name or phone...">
                    </div>
                    <div class="customer-list-container overflow-y-auto max-h-64 mb-3" id="customer-picker-list">
                        <!-- Populated dynamically -->
                    </div>
                    <div class="border-top pt-3 flex justify-between">
                        <button id="select-walkin-btn" class="btn btn-secondary btn-sm">Default Walk-in</button>
                        <button id="close-cust-modal-done-btn" class="btn btn-primary btn-sm">Done</button>
                    </div>
                </div>
            </div>

            <!-- Payment / Tender Modal -->
            <div id="checkout-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3 mb-3">
                        <h2 class="font-bold">Payment & Tender</h2>
                        <button id="close-checkout-modal-btn" class="close-btn">&times;</button>
                    </div>

                    <div class="text-center mb-4 p-3 bg-surface rounded">
                        <span class="text-xs uppercase text-muted font-bold block mb-1">Total Amount Payable</span>
                        <span class="text-3xl font-bold text-primary font-mono" id="modal-pay-total">₱0.00</span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="cash-tendered" class="font-semibold text-sm">Cash Tendered (₱) *</label>
                        <input type="number" step="1" min="0" id="cash-tendered" class="form-control text-xl font-mono font-bold" placeholder="0.00" autofocus>
                    </div>

                    <!-- Quick Bills Shortcut Buttons -->
                    <div class="flex gap-2 mb-3">
                        <button type="button" class="btn btn-secondary btn-sm quick-cash-btn" data-amt="exact">Exact</button>
                        <button type="button" class="btn btn-secondary btn-sm quick-cash-btn" data-amt="100">₱100</button>
                        <button type="button" class="btn btn-secondary btn-sm quick-cash-btn" data-amt="500">₱500</button>
                        <button type="button" class="btn btn-secondary btn-sm quick-cash-btn" data-amt="1000">₱1,000</button>
                        <button type="button" class="btn btn-secondary btn-sm quick-cash-btn" data-amt="2000">₱2,000</button>
                    </div>

                    <!-- Change Calculation Display -->
                    <div class="p-3 border rounded mb-4 flex justify-between items-center bg-input">
                        <span class="text-sm font-semibold">Change to Return:</span>
                        <span class="text-xl font-bold font-mono text-success" id="modal-change-display">₱0.00</span>
                    </div>

                    <div class="flex justify-end gap-2 border-top pt-3">
                        <button type="button" id="cancel-checkout-btn" class="btn btn-secondary">Cancel</button>
                        <button type="button" id="confirm-checkout-btn" class="btn btn-primary" disabled>
                            💳 Complete Sale & Generate Receipt
                        </button>
                    </div>
                </div>
            </div>

            <!-- Thermal Receipt Preview Modal (Parsed from XML via DOMParser) -->
            <div id="receipt-modal" class="modal-backdrop hidden">
                <div class="modal-dialog" style="max-width: 480px;">
                    <div class="modal-header flex justify-between items-center border-bottom pb-2 mb-2 no-print">
                        <h2 class="text-sm font-bold">Digital Receipt (XML Generated)</h2>
                        <button id="close-receipt-modal-btn" class="close-btn">&times;</button>
                    </div>

                    <!-- Printable Thermal Receipt Area -->
                    <div id="thermal-receipt-outlet" class="thermal-receipt-paper">
                        <!-- Populated by DOMParser() -->
                    </div>

                    <div class="modal-footer flex justify-between items-center border-top pt-3 mt-3 no-print">
                        <button id="close-receipt-btn" class="btn btn-secondary btn-sm">Close</button>
                        <button id="print-receipt-btn" class="btn btn-primary btn-sm">🖨️ Print Receipt</button>
                    </div>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await Promise.all([
            this.loadCategories(),
            this.loadProducts(),
            this.loadServices(),
            this.loadCustomers()
        ]);
        this.renderGrid();
    },

    bindEvents() {
        // Tab switching
        document.getElementById('tab-products-btn')?.addEventListener('click', () => {
            this.state.activeTab = 'products';
            document.getElementById('tab-products-btn')?.classList.add('btn-primary');
            document.getElementById('tab-products-btn')?.classList.remove('btn-secondary');
            document.getElementById('tab-services-btn')?.classList.remove('btn-primary');
            document.getElementById('tab-services-btn')?.classList.add('btn-secondary');
            document.getElementById('pos-category-pills')?.classList.remove('hidden');
            this.renderGrid();
        });

        document.getElementById('tab-services-btn')?.addEventListener('click', () => {
            this.state.activeTab = 'services';
            document.getElementById('tab-services-btn')?.classList.add('btn-primary');
            document.getElementById('tab-services-btn')?.classList.remove('btn-secondary');
            document.getElementById('tab-products-btn')?.classList.remove('btn-primary');
            document.getElementById('tab-products-btn')?.classList.add('btn-secondary');
            document.getElementById('pos-category-pills')?.classList.add('hidden');
            this.renderGrid();
        });

        // Search
        let searchTimer;
        document.getElementById('pos-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.searchQuery = e.target.value.toLowerCase().trim();
                this.renderGrid();
            }, 200);
        });

        // Category Pills Click (Event delegation)
        document.getElementById('pos-category-pills')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.pill-btn');
            if (btn) {
                document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.state.selectedCategoryId = btn.dataset.categoryId || '';
                this.renderGrid();
            }
        });

        // Add to Cart from Grid (Handles direct card click and event delegation)
        let lastAddTimestamp = 0;
        let lastAddId = null;

        const handleAddProduct = (prodId) => {
            const prod = this.state.products.find(p => p.id === prodId);
            if (prod && parseInt(prod.quantity, 10) <= 0) {
                State.setAlert(`"${prod.name}" is currently out of stock.`, 'warning', 2500);
                return;
            }
            const now = Date.now();
            if (lastAddId === prodId && (now - lastAddTimestamp) < 150) return;
            lastAddTimestamp = now;
            lastAddId = prodId;
            this.addProductToCart(prodId);
        };

        const handleAddService = (svcId) => {
            const now = Date.now();
            if (lastAddId === ('svc_' + svcId) && (now - lastAddTimestamp) < 150) return;
            lastAddTimestamp = now;
            lastAddId = 'svc_' + svcId;
            this.addServiceToCart(svcId);
        };

        window.addToCart = handleAddProduct;
        window.posAddToCart = handleAddProduct;
        window.addServiceToCart = handleAddService;
        window.posAddServiceToCart = handleAddService;

        document.getElementById('pos-grid')?.addEventListener('click', (e) => {
            const card = e.target.closest('.product-card, .pos-card');
            if (!card) return;

            const id = parseInt(card.dataset.id, 10);
            if (!id) return;

            if (card.classList.contains('service-card')) {
                handleAddService(id);
            } else {
                handleAddProduct(id);
            }
        });

        // Cart Actions (Quantity +/- and Remove)
        document.getElementById('cart-lines')?.addEventListener('click', (e) => {
            const decBtn = e.target.closest('.cart-qty-dec');
            if (decBtn) {
                const idx = parseInt(decBtn.dataset.index, 10);
                this.updateProductQty(idx, -1);
                return;
            }

            const incBtn = e.target.closest('.cart-qty-inc');
            if (incBtn) {
                const idx = parseInt(incBtn.dataset.index, 10);
                this.updateProductQty(idx, 1);
                return;
            }

            const delItemBtn = e.target.closest('.cart-remove-item');
            if (delItemBtn) {
                const idx = parseInt(delItemBtn.dataset.index, 10);
                this.removeCartItem(idx);
                return;
            }

            const delSvcBtn = e.target.closest('.cart-remove-service');
            if (delSvcBtn) {
                const idx = parseInt(delSvcBtn.dataset.index, 10);
                this.removeCartService(idx);
                return;
            }
        });

        // Clear Cart
        document.getElementById('clear-cart-btn')?.addEventListener('click', () => {
            if (confirm('Are you sure you want to clear the current cart?')) {
                this.clearCart();
            }
        });

        // Open Checkout Modal
        document.getElementById('checkout-btn')?.addEventListener('click', () => {
            this.openCheckoutModal();
        });

        // Close Checkout Modal
        document.getElementById('close-checkout-modal-btn')?.addEventListener('click', () => {
            document.getElementById('checkout-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-checkout-btn')?.addEventListener('click', () => {
            document.getElementById('checkout-modal')?.classList.add('hidden');
        });

        // Cash Tendered Calculations
        const cashInput = document.getElementById('cash-tendered');
        cashInput?.addEventListener('input', () => {
            this.recomputeChange();
        });

        // Quick Bills
        document.querySelectorAll('.quick-cash-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const amt = e.currentTarget.dataset.amt;
                const grandTotal = this.calculateGrandTotal();
                if (amt === 'exact') {
                    cashInput.value = grandTotal.toFixed(2);
                } else {
                    cashInput.value = parseFloat(amt).toFixed(2);
                }
                this.recomputeChange();
            });
        });

        // Confirm Checkout
        document.getElementById('confirm-checkout-btn')?.addEventListener('click', () => {
            this.executeCheckout();
        });

        // Customer selection modal
        document.getElementById('change-customer-btn')?.addEventListener('click', () => {
            this.openCustomerModal();
        });
        document.getElementById('close-cust-modal-btn')?.addEventListener('click', () => {
            document.getElementById('customer-modal')?.classList.add('hidden');
        });
        document.getElementById('close-cust-modal-done-btn')?.addEventListener('click', () => {
            document.getElementById('customer-modal')?.classList.add('hidden');
        });
        document.getElementById('select-walkin-btn')?.addEventListener('click', () => {
            this.state.selectedCustomer = null;
            this.state.selectedMotorcycle = null;
            document.getElementById('customer-name-display').textContent = 'Walk-in Customer';
            document.getElementById('vehicle-plate-display').textContent = 'No vehicle linked';
            document.getElementById('customer-modal')?.classList.add('hidden');
        });

        // Receipt modal close & print
        document.getElementById('close-receipt-modal-btn')?.addEventListener('click', () => {
            document.getElementById('receipt-modal')?.classList.add('hidden');
        });
        document.getElementById('close-receipt-btn')?.addEventListener('click', () => {
            document.getElementById('receipt-modal')?.classList.add('hidden');
        });
        document.getElementById('print-receipt-btn')?.addEventListener('click', () => {
            window.print();
        });
    },

    async loadCategories() {
        try {
            const res = await apiClient.get('categories');
            if (res.success) {
                this.state.categories = res.data;
                const pillsContainer = document.getElementById('pos-category-pills');
                if (pillsContainer) {
                    const pills = res.data.map(c => `
                        <button class="pill-btn" data-category-id="${c.id}">${c.name}</button>
                    `).join('');
                    pillsContainer.innerHTML = '<button class="pill-btn active" data-category-id="">All Categories</button>' + pills;
                }
            }
        } catch (err) {
            console.error('Failed to load categories', err);
        }
    },

    async loadProducts() {
        try {
            const res = await apiClient.get('products', { limit: 100 });
            if (res.success) {
                this.state.products = res.data;
            }
        } catch (err) {
            console.error('Failed to load products', err);
        }
    },

    async loadServices() {
        try {
            const res = await apiClient.get('services', { status: 'active' });
            if (res.success && Array.isArray(res.data)) {
                this.state.services = res.data.map(s => ({
                    id: parseInt(s.id, 10),
                    name: s.name,
                    charge: parseFloat(s.charge),
                    description: s.description || ''
                }));
            } else {
                this.state.services = [];
            }
        } catch (err) {
            console.error('Failed to load services from backend:', err);
            this.state.services = [];
        }
    },

    async loadCustomers() {
        try {
            const res = await apiClient.get('customers');
            if (res.success) {
                this.state.customers = res.data;
            }
        } catch (err) {
            console.error('Failed to load customers', err);
        }
    },

    renderGrid() {
        const grid = document.getElementById('pos-grid');
        if (!grid) return;

        if (this.state.activeTab === 'products') {
            let filtered = this.state.products;

            if (this.state.selectedCategoryId !== '') {
                filtered = filtered.filter(p => String(p.category_id) === String(this.state.selectedCategoryId));
            }

            if (this.state.searchQuery !== '') {
                const q = this.state.searchQuery;
                filtered = filtered.filter(p => p.name.toLowerCase().includes(q) || p.item_code.toLowerCase().includes(q));
            }

            if (filtered.length === 0) {
                grid.innerHTML = `<div class="text-center py-5 text-muted col-span-3">No matching products found.</div>`;
                return;
            }

            grid.innerHTML = filtered.map(p => {
                const isOutOfStock = parseInt(p.quantity, 10) <= 0;
                const priceFmt = parseFloat(p.price).toLocaleString('en-PH', { minimumFractionDigits: 2 });

                return `
                    <div class="product-card pos-card ${isOutOfStock ? 'out-of-stock' : ''}" 
                         data-id="${p.id}" 
                         onclick="${isOutOfStock ? 'return false;' : `addToCart(${p.id})`}"
                         style="${isOutOfStock ? 'pointer-events: none; opacity: 0.55; cursor: not-allowed;' : ''}"
                         title="${isOutOfStock ? 'Out of stock' : 'Click to add to cart'}">
                        <div class="product-card-top pos-card-top">
                            <span class="product-card-code pos-card-code">${p.item_code}</span>
                            <h4 class="product-card-title pos-card-title">${p.name}</h4>
                        </div>
                        <div class="product-card-bottom pos-card-footer">
                            <div class="product-card-stock">
                                <span class="badge ${isOutOfStock ? 'badge-danger' : 'badge-secondary'}">
                                    ${isOutOfStock ? 'Out of Stock' : `${p.quantity} ${p.unit || 'pc'} in stock`}
                                </span>
                            </div>
                            <span class="product-card-price pos-card-price">₱${priceFmt}</span>
                        </div>
                    </div>
                `;
            }).join('');

        } else {
            // Services Tab
            let filtered = this.state.services;
            if (this.state.searchQuery !== '') {
                const q = this.state.searchQuery;
                filtered = filtered.filter(s => s.name.toLowerCase().includes(q));
            }

            grid.innerHTML = filtered.map(s => {
                const chargeFmt = parseFloat(s.charge).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                return `
                    <div class="product-card pos-card service-card" 
                         data-id="${s.id}" 
                         onclick="addServiceToCart(${s.id})"
                         title="Click to add labor service">
                        <div class="product-card-top pos-card-top">
                            <span class="product-card-code pos-card-code">LABOR SERVICE</span>
                            <h4 class="product-card-title pos-card-title">${s.name}</h4>
                            <div class="text-xs text-muted">${s.description || 'Shop service'}</div>
                        </div>
                        <div class="product-card-bottom pos-card-footer">
                            <span class="badge badge-info">Service</span>
                            <span class="product-card-price pos-card-price text-success">₱${chargeFmt}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }
    },

    addProductToCart(productId) {
        const product = this.state.products.find(p => p.id === productId);
        if (!product) return;

        const availableStock = parseInt(product.quantity, 10);
        if (availableStock <= 0) {
            State.setAlert(`"${product.name}" is currently out of stock.`, 'warning', 2500);
            return;
        }

        const existing = this.state.cart.items.find(i => i.product_id === productId);
        const currentQtyInCart = existing ? existing.quantity : 0;

        if (currentQtyInCart >= availableStock) {
            State.setAlert(`Cannot add more. Only ${availableStock} ${product.name} in stock.`, 'warning', 3000);
            return;
        }

        if (existing) {
            existing.quantity++;
        } else {
            this.state.cart.items.push({
                product_id: product.id,
                item_code: product.item_code,
                name: product.name,
                price: parseFloat(product.price),
                cost_of_goods_sold: parseFloat(product.default_cost || 0),
                quantity: 1,
                max_quantity: availableStock,
                unit: product.unit || 'pc'
            });
        }

        this.renderCart();
    },

    addServiceToCart(serviceId) {
        const svc = this.state.services.find(s => s.id === serviceId);
        if (!svc) return;

        const existing = this.state.cart.services.find(s => s.service_id === serviceId);
        if (existing) {
            State.setAlert(`Service '${svc.name}' is already in cart.`, 'info', 2000);
            return;
        }

        this.state.cart.services.push({
            service_id: svc.id,
            name: svc.name,
            price_at_time: parseFloat(svc.charge)
        });

        this.renderCart();
    },

    updateProductQty(index, delta) {
        const item = this.state.cart.items[index];
        if (!item) return;

        const newQty = item.quantity + delta;
        if (newQty > item.max_quantity) {
            State.setAlert(`Maximum available stock reached (${item.max_quantity}).`, 'warning', 2500);
            return;
        }

        if (newQty <= 0) {
            this.state.cart.items.splice(index, 1);
        } else {
            item.quantity = newQty;
        }

        this.renderCart();
    },

    removeCartItem(index) {
        this.state.cart.items.splice(index, 1);
        this.renderCart();
    },

    removeCartService(index) {
        this.state.cart.services.splice(index, 1);
        this.renderCart();
    },

    clearCart() {
        this.state.cart.items = [];
        this.state.cart.services = [];
        this.renderCart();
    },

    renderCart() {
        const container = document.getElementById('cart-lines');
        const checkoutBtn = document.getElementById('checkout-btn');
        if (!container) return;

        const { items, services } = this.state.cart;

        if (items.length === 0 && services.length === 0) {
            container.innerHTML = `<div class="text-center py-5 text-muted">Cart is currently empty.</div>`;
            document.getElementById('cart-products-subtotal').textContent = '₱0.00';
            document.getElementById('cart-services-subtotal').textContent = '₱0.00';
            document.getElementById('cart-grand-total').textContent = '₱0.00';
            if (checkoutBtn) checkoutBtn.disabled = true;
            return;
        }

        let html = '';

        // Product Items
        items.forEach((item, idx) => {
            const lineTotal = (item.price * item.quantity).toLocaleString('en-PH', { minimumFractionDigits: 2 });
            html += `
                <div class="cart-line-item">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <span class="font-semibold text-sm">${item.name}</span>
                            <div class="text-xs text-muted font-mono">₱${item.price.toFixed(2)} / ${item.unit}</div>
                        </div>
                        <span class="font-mono font-bold text-sm">₱${lineTotal}</span>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <div class="qty-control flex items-center gap-1">
                            <button class="btn btn-secondary btn-icon cart-qty-dec" data-index="${idx}">−</button>
                            <span class="font-bold px-2">${item.quantity}</span>
                            <button class="btn btn-secondary btn-icon cart-qty-inc" data-index="${idx}">+</button>
                        </div>
                        <button class="text-danger btn-link cart-remove-item" data-index="${idx}">Remove</button>
                    </div>
                </div>
            `;
        });

        // Services
        services.forEach((svc, idx) => {
            const chargeFmt = svc.price_at_time.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            html += `
                <div class="cart-line-item service-line">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="badge badge-info text-xs mr-1">Service</span>
                            <span class="font-semibold text-sm">${svc.name}</span>
                        </div>
                        <span class="font-mono font-bold text-sm text-success">₱${chargeFmt}</span>
                    </div>
                    <div class="text-right mt-1">
                        <button class="text-danger btn-link text-xs cart-remove-service" data-index="${idx}">Remove</button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;

        // Calculate Totals
        const prodSubtotal = items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
        const svcSubtotal = services.reduce((sum, s) => sum + s.price_at_time, 0);
        const grandTotal = prodSubtotal + svcSubtotal;

        document.getElementById('cart-products-subtotal').textContent = `₱${prodSubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('cart-services-subtotal').textContent = `₱${svcSubtotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        document.getElementById('cart-grand-total').textContent = `₱${grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

        if (checkoutBtn) checkoutBtn.disabled = grandTotal <= 0;
    },

    calculateGrandTotal() {
        const prodSubtotal = this.state.cart.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
        const svcSubtotal = this.state.cart.services.reduce((sum, s) => sum + s.price_at_time, 0);
        return prodSubtotal + svcSubtotal;
    },

    openCheckoutModal() {
        const grandTotal = this.calculateGrandTotal();
        document.getElementById('modal-pay-total').textContent = `₱${grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
        
        const cashInput = document.getElementById('cash-tendered');
        cashInput.value = '';
        document.getElementById('modal-change-display').textContent = '₱0.00';
        document.getElementById('confirm-checkout-btn').disabled = true;

        document.getElementById('checkout-modal')?.classList.remove('hidden');
        setTimeout(() => cashInput.focus(), 100);
    },

    recomputeChange() {
        const grandTotal = this.calculateGrandTotal();
        const cash = parseFloat(document.getElementById('cash-tendered').value) || 0;
        const confirmBtn = document.getElementById('confirm-checkout-btn');
        const changeDisplay = document.getElementById('modal-change-display');

        const change = cash - grandTotal;
        if (change >= 0) {
            changeDisplay.textContent = `₱${change.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
            changeDisplay.classList.remove('text-danger');
            changeDisplay.classList.add('text-success');
            confirmBtn.disabled = false;
        } else {
            const short = Math.abs(change);
            changeDisplay.textContent = `Short by ₱${short.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
            changeDisplay.classList.remove('text-success');
            changeDisplay.classList.add('text-danger');
            confirmBtn.disabled = true;
        }
    },

    async executeCheckout() {
        const confirmBtn = document.getElementById('confirm-checkout-btn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Processing Transaction...';

        const payload = {
            customer_id: this.state.selectedCustomer ? this.state.selectedCustomer.id : null,
            motorcycle_id: this.state.selectedMotorcycle ? this.state.selectedMotorcycle.id : null,
            items: this.state.cart.items.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity,
                price_at_time: i.price,
                cost_of_goods_sold: i.cost_of_goods_sold
            })),
            services: this.state.cart.services.map(s => ({
                service_id: s.service_id,
                price_at_time: s.price_at_time
            }))
        };

        try {
            const response = await apiClient.post('sales', payload);

            if (response.success && response.sale_id) {
                // Close checkout modal
                document.getElementById('checkout-modal')?.classList.add('hidden');
                State.setAlert('Transaction successful! Generating XML Digital Receipt...', 'success');

                // Clear cart & reload product stock
                this.clearCart();
                await this.loadProducts();
                this.renderGrid();

                // Fetch and Render XML Digital Receipt
                await this.fetchAndRenderXmlReceipt(response.sale_id);
            }
        } catch (err) {
            State.setAlert(err.message || 'Checkout failed.', 'danger');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '💳 Complete Sale & Generate Receipt';
        }
    },

    /**
     * XML Data Interchange: Fetch receipt XML from backend and parse via browser native DOMParser()
     */
    async fetchAndRenderXmlReceipt(saleId) {
        try {
            const xmlString = await apiClient.get(`sales/${saleId}/receipt`);
            this.state.currentReceiptXml = xmlString;

            // Native DOMParser execution
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlString, "application/xml");

            // Extract XML nodes
            const companyName = xmlDoc.querySelector('header > company_name')?.textContent || 'VinJack Motorworks';
            const companyAddress = xmlDoc.querySelector('header > company_address')?.textContent || '';
            const contactPhone = xmlDoc.querySelector('header > contact_phone')?.textContent || '';
            const receiptId = xmlDoc.querySelector('receipt')?.getAttribute('id') || `REC-${saleId}`;
            const timestamp = xmlDoc.querySelector('metadata > transaction_date')?.textContent || '';
            const cashierName = xmlDoc.querySelector('metadata > cashier > name')?.textContent || '';
            const customerName = xmlDoc.querySelector('metadata > customer > name')?.textContent || 'Walk-in';
            const vehiclePlate = xmlDoc.querySelector('metadata > vehicle > plate_number')?.textContent || '';

            const itemNodes = xmlDoc.querySelectorAll('items > item');
            const serviceNodes = xmlDoc.querySelectorAll('services > service');

            const itemsSubtotal = xmlDoc.querySelector('summary > items_subtotal')?.textContent || '0.00';
            const servicesSubtotal = xmlDoc.querySelector('summary > services_subtotal')?.textContent || '0.00';
            const grandTotal = xmlDoc.querySelector('summary > grand_total')?.textContent || '0.00';
            const currency = xmlDoc.querySelector('summary > currency')?.textContent || 'PHP';

            // Construct Thermal Receipt HTML
            let itemsHtml = '';
            itemNodes.forEach(item => {
                const name = item.querySelector('name')?.textContent;
                const qty = item.querySelector('quantity')?.textContent;
                const price = item.querySelector('unit_price')?.textContent;
                const total = item.querySelector('total_price')?.textContent;
                itemsHtml += `
                    <tr>
                        <td>${name}<br><span class="text-dim text-xs">${qty} @ ₱${price}</span></td>
                        <td class="text-right font-mono">₱${total}</td>
                    </tr>
                `;
            });

            let servicesHtml = '';
            serviceNodes.forEach(svc => {
                const name = svc.querySelector('name')?.textContent;
                const charge = svc.querySelector('charge')?.textContent;
                servicesHtml += `
                    <tr>
                        <td>🔧 ${name} (Labor)</td>
                        <td class="text-right font-mono">₱${charge}</td>
                    </tr>
                `;
            });

            const receiptHtml = `
                <div class="receipt-paper font-mono text-xs">
                    <div class="text-center mb-3">
                        <h2 class="text-base font-bold uppercase tracking-wider">${companyName}</h2>
                        <p class="text-dim">${companyAddress}</p>
                        <p class="text-dim">Tel: ${contactPhone}</p>
                        <div class="receipt-divider">================================</div>
                        <div class="font-bold text-sm">OFFICIAL DIGITAL RECEIPT</div>
                        <div class="text-dim">${receiptId}</div>
                        <div>Date: ${timestamp}</div>
                        <div class="receipt-divider">--------------------------------</div>
                    </div>

                    <div class="mb-2">
                        <div><strong>Cashier:</strong> ${cashierName}</div>
                        <div><strong>Customer:</strong> ${customerName}</div>
                        ${vehiclePlate ? `<div><strong>Vehicle Plate:</strong> ${vehiclePlate}</div>` : ''}
                    </div>

                    <div class="receipt-divider">--------------------------------</div>
                    <table class="receipt-table w-full mb-2">
                        <thead>
                            <tr class="border-bottom">
                                <th class="text-left">DESCRIPTION</th>
                                <th class="text-right">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            ${servicesHtml}
                        </tbody>
                    </table>

                    <div class="receipt-divider">--------------------------------</div>
                    <div class="flex justify-between mb-1">
                        <span>Parts Subtotal:</span>
                        <span>₱${itemsSubtotal}</span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span>Labor Subtotal:</span>
                        <span>₱${servicesSubtotal}</span>
                    </div>
                    <div class="flex justify-between font-bold text-sm mt-1 pt-1 border-top">
                        <span>TOTAL (${currency}):</span>
                        <span>₱${grandTotal}</span>
                    </div>

                    <div class="receipt-divider mt-3">================================</div>
                    <div class="text-center text-dim mt-2">
                        <p>Thank you for choosing VinJack!</p>
                        <p>Ride Safe & Have a Great Day!</p>
                    </div>
                </div>
            `;

            document.getElementById('thermal-receipt-outlet').innerHTML = receiptHtml;
            document.getElementById('receipt-modal')?.classList.remove('hidden');

        } catch (err) {
            State.setAlert('Failed to generate XML receipt: ' + err.message, 'danger');
        }
    },

    openCustomerModal() {
        const modal = document.getElementById('customer-modal');
        const list = document.getElementById('customer-picker-list');
        modal.classList.remove('hidden');

        const renderCustList = (custs) => {
            if (custs.length === 0) {
                list.innerHTML = `<div class="text-muted text-center py-3 text-sm">No customers found.</div>`;
                return;
            }
            list.innerHTML = custs.map(c => `
                <div class="p-2 border-bottom hover:bg-card-hover cursor-pointer flex justify-between items-center select-customer-row" data-id="${c.id}">
                    <div>
                        <div class="font-bold text-sm">${c.name}</div>
                        <div class="text-xs text-muted">${c.phone || 'No phone'} | ${c.motorcycles?.length || 0} motorcycle(s)</div>
                    </div>
                    <button class="btn btn-secondary btn-sm select-cust-btn" data-id="${c.id}">Select</button>
                </div>
            `).join('');
        };

        renderCustList(this.state.customers);

        // Search within modal
        document.getElementById('cust-search-input')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            const filtered = this.state.customers.filter(c => c.name.toLowerCase().includes(q) || (c.phone && c.phone.includes(q)));
            renderCustList(filtered);
        });

        list.onclick = (e) => {
            const btn = e.target.closest('.select-cust-btn');
            if (btn) {
                const custId = parseInt(btn.dataset.id, 10);
                const cust = this.state.customers.find(c => c.id === custId);
                if (cust) {
                    this.state.selectedCustomer = cust;
                    this.state.selectedMotorcycle = cust.motorcycles && cust.motorcycles.length > 0 ? cust.motorcycles[0] : null;

                    document.getElementById('customer-name-display').textContent = cust.name;
                    document.getElementById('vehicle-plate-display').textContent = this.state.selectedMotorcycle 
                        ? `🏍️ ${this.state.selectedMotorcycle.plate_number} (${this.state.selectedMotorcycle.make} ${this.state.selectedMotorcycle.model})`
                        : 'No vehicle linked';

                    modal.classList.add('hidden');
                }
            }
        };
    }
};
