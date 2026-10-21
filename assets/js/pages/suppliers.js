import { ApiClient } from '../api/client.js';

export const SuppliersPage = {
    render() {
        return `
            <div class="flex justify-between items-center mb-4">
                <h2>Suppliers Management</h2>
                <button class="btn btn-primary" id="add-supplier-btn">Add Supplier</button>
            </div>
            
            <div class="card">
                <table id="suppliers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        `;
    },

    async init() {
        try {
            const suppliers = await ApiClient.get('suppliers');
            const tbody = document.querySelector('#suppliers-table tbody');
            tbody.innerHTML = '';
            
            if (suppliers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No suppliers found.</td></tr>';
                return;
            }

            suppliers.forEach(s => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${s.name}</td>
                    <td>${s.contact_person || '-'}</td>
                    <td>${s.email || '-'}</td>
                    <td>${s.contact_number || '-'}</td>
                    <td>${s.status}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error(error);
        }
    }
};
