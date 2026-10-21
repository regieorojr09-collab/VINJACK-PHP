import { ApiClient } from '../api/client.js';

export const SalesPage = {
    render() {
        return `
            <div id="pos-container">
                <div class="flex justify-between items-center mb-4">
                    <h2>Point of Sale</h2>
                </div>
                
                <div class="grid gap-4" style="grid-template-columns: 2fr 1fr;">
                    <!-- Left: Products -->
                    <div class="card">
                        <h3>Inventory</h3>
                        <div style="margin: 1rem 0;">
                            <input type="text" id="product-search" class="form-control" placeholder="Search product or scan barcode...">
                        </div>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <table id="products-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" class="text-center">Loading products...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Cart -->
                    <div class="card">
                        <h3>Current Sale</h3>
                        <div style="min-height: 300px; margin: 1rem 0;">
                            <table id="cart-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="border-top: 2px solid var(--border); padding-top: 1rem; font-size: 1.25rem; font-weight: bold; display: flex; justify-content: space-between;">
                            <span>Total:</span>
                            <span id="cart-total">$0.00</span>
                        </div>
                        
                        <button id="checkout-btn" class="btn btn-success btn-block mt-4" disabled>Process Sale</button>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal / Print Area -->
            <div id="receipt-container" style="display: none;"></div>
        `;
    },

    cart: [],
    products: [],

    async init() {
        this.cart = [];
        this.products = [];
        this.loadProducts();

        document.getElementById('checkout-btn').addEventListener('click', () => this.processSale());
    },

    async loadProducts() {
        try {
            this.products = await ApiClient.get('products');
            this.renderProducts();
        } catch (error) {
            console.error(error);
        }
    },

    renderProducts() {
        const tbody = document.querySelector('#products-table tbody');
        tbody.innerHTML = '';
        
        this.products.forEach(p => {
            if (p.quantity > 0) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.item_code}</td>
                    <td>${p.name}</td>
                    <td>$${parseFloat(p.price).toFixed(2)}</td>
                    <td>${p.quantity}</td>
                    <td>
                        <button class="btn btn-primary add-to-cart-btn" data-id="${p.id}">Add</button>
                    </td>
                `;
                tbody.appendChild(tr);
            }
        });

        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                this.addToCart(id);
            });
        });
    },

    addToCart(productId) {
        const product = this.products.find(p => p.id == productId);
        if (!product) return;

        const existing = this.cart.find(item => item.product_id == productId);
        if (existing) {
            if (existing.quantity < product.quantity) {
                existing.quantity++;
            } else {
                alert('Not enough stock!');
            }
        } else {
            this.cart.push({
                product_id: product.id,
                name: product.name,
                price_at_time: product.price,
                cost_of_goods_sold: product.default_cost,
                quantity: 1
            });
        }
        
        this.renderCart();
    },

    renderCart() {
        const tbody = document.querySelector('#cart-table tbody');
        tbody.innerHTML = '';
        let total = 0;
        
        this.cart.forEach(item => {
            const subtotal = item.quantity * item.price_at_time;
            total += subtotal;
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.name}</td>
                <td>${item.quantity}</td>
                <td>$${subtotal.toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('cart-total').textContent = '$' + total.toFixed(2);
        document.getElementById('checkout-btn').disabled = this.cart.length === 0;
    },

    async processSale() {
        if (this.cart.length === 0) return;
        
        try {
            const saleData = {
                is_manual_entry: false,
                items: this.cart,
                services: []
            };
            
            const sale = await ApiClient.post('sales', saleData);
            alert('Sale processed successfully!');
            
            // Generate and parse XML receipt
            await this.fetchAndPrintReceipt(sale.id);
            
            // Reset
            this.cart = [];
            this.renderCart();
            this.loadProducts();
            
        } catch (error) {
            alert('Error processing sale: ' + error.message);
        }
    },
    
    async fetchAndPrintReceipt(saleId) {
        try {
            // Fetch XML string
            const xmlString = await ApiClient.get(`sales/${saleId}/receipt`, {
                headers: { 'Accept': 'application/xml' }
            });
            
            // Parse XML
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlString, "application/xml");
            
            // Extract data using DOM methods
            const receipt = xmlDoc.getElementsByTagName("receipt")[0];
            const transId = receipt.getElementsByTagName("transaction_id")[0].textContent;
            const date = receipt.getElementsByTagName("date")[0].textContent;
            const total = parseFloat(receipt.getElementsByTagName("total_amount")[0].textContent).toFixed(2);
            
            let html = `
                <div style="font-family: monospace; max-width: 300px; margin: 0 auto; text-align: center;">
                    <h2>VinJack Motorworks</h2>
                    <p>Transaction #${transId}</p>
                    <p>Date: ${date}</p>
                    <hr>
                    <table style="width: 100%; text-align: left;">
            `;
            
            const items = receipt.getElementsByTagName("item");
            for (let i = 0; i < items.length; i++) {
                const name = items[i].getElementsByTagName("name")[0].textContent;
                const qty = items[i].getElementsByTagName("quantity")[0].textContent;
                const sub = parseFloat(items[i].getElementsByTagName("subtotal")[0].textContent).toFixed(2);
                
                html += `<tr>
                    <td>${qty}x ${name}</td>
                    <td style="text-align: right;">$${sub}</td>
                </tr>`;
            }
            
            html += `
                    </table>
                    <hr>
                    <h3 style="text-align: right;">Total: $${total}</h3>
                    <p>Thank you for your business!</p>
                </div>
            `;
            
            const container = document.getElementById('receipt-container');
            container.innerHTML = html;
            
            // Print
            window.print();
            
            // Hide after printing
            setTimeout(() => { container.innerHTML = ''; }, 1000);
            
        } catch (error) {
            console.error('Failed to generate receipt:', error);
            alert('Failed to generate XML receipt');
        }
    }
};
