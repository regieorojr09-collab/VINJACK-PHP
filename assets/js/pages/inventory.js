import { ApiClient } from '../api/client.js';

export const InventoryPage = {
    render() {
        return `
            <div class="flex justify-between items-center mb-4">
                <h2>Inventory Management</h2>
                <button class="btn btn-primary" id="add-product-btn">Add Product</button>
            </div>
            
            <div class="card">
                <table id="inventory-table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        `;
    },

    async init() {
        try {
            const products = await ApiClient.get('products');
            const tbody = document.querySelector('#inventory-table tbody');
            tbody.innerHTML = '';
            
            if (products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No products found.</td></tr>';
                return;
            }

            products.forEach(p => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${p.item_code}</td>
                    <td>${p.name}</td>
                    <td>${p.category_name || '-'}</td>
                    <td>$${parseFloat(p.price).toFixed(2)}</td>
                    <td>${p.quantity}</td>
                    <td>${p.status}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error(error);
        }
    }
};
