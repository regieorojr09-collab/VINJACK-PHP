import { ApiClient } from '../api/client.js';

export const CustomersPage = {
    render() {
        return `
            <div class="flex justify-between items-center mb-4">
                <h2>Customers Management</h2>
                <button class="btn btn-primary" id="add-customer-btn">Add Customer</button>
            </div>
            
            <div class="card">
                <table id="customers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Motorcycles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        `;
    },

    async init() {
        try {
            const customers = await ApiClient.get('customers');
            const tbody = document.querySelector('#customers-table tbody');
            tbody.innerHTML = '';
            
            if (customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">No customers found.</td></tr>';
                return;
            }

            customers.forEach(c => {
                const tr = document.createElement('tr');
                const motoCount = c.motorcycles ? c.motorcycles.length : 0;
                tr.innerHTML = `
                    <td>${c.name}</td>
                    <td>${c.email || '-'}</td>
                    <td>${c.phone || '-'}</td>
                    <td>${motoCount}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error(error);
        }
    }
};
