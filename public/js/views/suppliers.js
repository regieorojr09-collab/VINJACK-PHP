import { apiClient } from '../client.js';
import { State } from '../state.js';

export const SuppliersPage = {
    state: {
        suppliers: [],
        filters: {
            search: '',
            status: ''
        },
        editingSupplier: null
    },

    render() {
        const canManage = State.hasPermission('canManageSuppliers');

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title">Supplier Management</h1>
                        <p class="text-muted text-sm">Vendor contacts, agreement terms, and procurement partnerships.</p>
                    </div>
                    ${canManage ? `
                        <button id="add-supplier-btn" class="btn btn-primary">
                            <span>+</span> Register Supplier
                        </button>
                    ` : ''}
                </div>

                <!-- Filters -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-3 gap-3 items-center">
                        <div class="col-span-2">
                            <input type="text" id="filter-sup-search" class="form-control" placeholder="🔍 Search vendor name, contact person, or email..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-sup-status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="Approved">Approved</option>
                                <option value="Pending">Pending Review</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Supplier Grid Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="suppliers-table">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone / Email</th>
                                    <th>Payment Terms</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-center">Catalog Items</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="suppliers-tbody">
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">Loading vendor directory...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Supplier Modal -->
            <div id="supplier-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <h2 id="sup-modal-title" class="text-lg font-bold">Register New Supplier</h2>
                        <button id="close-sup-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="supplier-form" class="mt-3">
                        <div id="sup-modal-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="form-group mb-3">
                            <label for="sup-name">Supplier / Company Name *</label>
                            <input type="text" id="sup-name" class="form-control" placeholder="e.g. Apex Moto Distribution Phils" required>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="sup-contact-person">Contact Person</label>
                                <input type="text" id="sup-contact-person" class="form-control" placeholder="e.g. Marco Santos">
                            </div>
                            <div class="form-group">
                                <label for="sup-contact-number">Contact Phone</label>
                                <input type="text" id="sup-contact-number" class="form-control" placeholder="e.g. +63 917 123 4567">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label for="sup-email">Email Address</label>
                            <input type="email" id="sup-email" class="form-control" placeholder="e.g. orders@apexmotoph.com">
                        </div>

                        <div class="form-group mb-3">
                            <label for="sup-address">Office / Warehouse Address</label>
                            <textarea id="sup-address" class="form-control" rows="2" placeholder="Full delivery and business address"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="sup-terms">Default Payment Terms *</label>
                                <select id="sup-terms" class="form-control" required>
                                    <option value="Cash">Cash on Delivery</option>
                                    <option value="Consignment">Consignment</option>
                                    <option value="Terms">Terms (30/60 Days)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sup-status">Approval Status *</label>
                                <select id="sup-status" class="form-control" required>
                                    <option value="Approved">Approved</option>
                                    <option value="Pending">Pending Review</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3 mt-4">
                            <button type="button" id="cancel-sup-modal-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-sup-btn" class="btn btn-primary">Save Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await this.loadSuppliers();
    },

    bindEvents() {
        let searchTimer;
        document.getElementById('filter-sup-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.filters.search = e.target.value.trim();
                this.loadSuppliers();
            }, 300);
        });

        document.getElementById('filter-sup-status')?.addEventListener('change', (e) => {
            this.state.filters.status = e.target.value;
            this.loadSuppliers();
        });

        document.getElementById('add-supplier-btn')?.addEventListener('click', () => {
            this.openModal(null);
        });

        document.getElementById('close-sup-modal-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('cancel-sup-modal-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('supplier-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveSupplier();
        });

        // Event delegation for actions
        document.getElementById('suppliers-tbody')?.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-sup-btn');
            if (editBtn) {
                const id = parseInt(editBtn.dataset.id, 10);
                this.openModal(id);
                return;
            }

            const deleteBtn = e.target.closest('.delete-sup-btn');
            if (deleteBtn) {
                const id = parseInt(deleteBtn.dataset.id, 10);
                const name = deleteBtn.dataset.name;
                this.confirmDelete(id, name);
                return;
            }
        });
    },

    async loadSuppliers() {
        const tbody = document.getElementById('suppliers-tbody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">Refreshing supplier directory...</td></tr>`;

        try {
            const params = {
                search: this.state.filters.search,
                status: this.state.filters.status
            };

            const res = await apiClient.get('suppliers', params);
            if (res.success) {
                this.state.suppliers = res.data;
                this.renderTable(res.data);
            }
        } catch (err) {
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">${err.message || 'Error fetching suppliers'}</td></tr>`;
            }
        }
    },

    renderTable(suppliers) {
        const tbody = document.getElementById('suppliers-tbody');
        if (!tbody) return;

        if (suppliers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No suppliers found.</td></tr>`;
            return;
        }

        const canManage = State.hasPermission('canManageSuppliers');

        tbody.innerHTML = suppliers.map(s => {
            const statusBadge = this.getStatusBadge(s.status);
            const termsBadge = this.getTermsBadge(s.default_payment_terms);

            return `
                <tr data-id="${s.id}">
                    <td class="font-semibold text-primary">
                        ${s.name}
                        ${s.address ? `<div class="text-xs text-muted font-normal mt-1">${s.address}</div>` : ''}
                    </td>
                    <td>${s.contact_person || '<span class="text-muted">—</span>'}</td>
                    <td class="text-sm">
                        ${s.contact_number ? `<div>📞 ${s.contact_number}</div>` : ''}
                        ${s.email ? `<div class="text-muted text-xs">✉️ ${s.email}</div>` : ''}
                        ${!s.contact_number && !s.email ? '<span class="text-muted">—</span>' : ''}
                    </td>
                    <td>
                        <span class="badge ${termsBadge}">${s.default_payment_terms}</span>
                    </td>
                    <td class="text-center font-mono font-medium">${s.total_orders || 0}</td>
                    <td class="text-center font-mono font-medium">${s.products_supplied || 0}</td>
                    <td class="text-center">
                        <span class="badge ${statusBadge}">${s.status}</span>
                    </td>
                    <td class="text-right actions-cell">
                        ${canManage ? `
                            <button class="btn btn-icon edit-sup-btn" data-id="${s.id}" title="Edit Supplier">✏️</button>
                            <button class="btn btn-icon text-danger delete-sup-btn" data-id="${s.id}" data-name="${s.name}" title="Delete Supplier">🗑️</button>
                        ` : '<span class="text-xs text-muted">View Only</span>'}
                    </td>
                </tr>
            `;
        }).join('');
    },

    getStatusBadge(status) {
        switch (status) {
            case 'Approved': return 'badge-success';
            case 'Pending': return 'badge-warning';
            case 'Rejected': return 'badge-danger';
            default: return 'badge-secondary';
        }
    },

    getTermsBadge(terms) {
        switch (terms) {
            case 'Consignment': return 'badge-info';
            case 'Terms': return 'badge-warning';
            default: return 'badge-secondary';
        }
    },

    async openModal(id = null) {
        const modal = document.getElementById('supplier-modal');
        const title = document.getElementById('sup-modal-title');
        const errorDiv = document.getElementById('sup-modal-error');
        const form = document.getElementById('supplier-form');

        errorDiv.style.display = 'none';
        form.reset();

        this.state.editingSupplier = id;

        if (id) {
            title.textContent = 'Edit Supplier Profile';
            try {
                const res = await apiClient.get(`suppliers/${id}`);
                if (res.success) {
                    const s = res.data;
                    document.getElementById('sup-name').value = s.name;
                    document.getElementById('sup-contact-person').value = s.contact_person || '';
                    document.getElementById('sup-contact-number').value = s.contact_number || '';
                    document.getElementById('sup-email').value = s.email || '';
                    document.getElementById('sup-address').value = s.address || '';
                    document.getElementById('sup-terms').value = s.default_payment_terms || 'Cash';
                    document.getElementById('sup-status').value = s.status || 'Approved';
                }
            } catch (err) {
                State.setAlert('Failed to load supplier details.', 'danger');
                return;
            }
        } else {
            title.textContent = 'Register New Supplier';
        }

        modal.classList.remove('hidden');
    },

    closeModal() {
        const modal = document.getElementById('supplier-modal');
        modal.classList.add('hidden');
        this.state.editingSupplier = null;
    },

    async saveSupplier() {
        const errorDiv = document.getElementById('sup-modal-error');
        const saveBtn = document.getElementById('save-sup-btn');
        errorDiv.style.display = 'none';
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const name = document.getElementById('sup-name').value.trim();
        const contact_person = document.getElementById('sup-contact-person').value.trim();
        const contact_number = document.getElementById('sup-contact-number').value.trim();
        const email = document.getElementById('sup-email').value.trim();
        const address = document.getElementById('sup-address').value.trim();
        const default_payment_terms = document.getElementById('sup-terms').value;
        const status = document.getElementById('sup-status').value;

        const payload = {
            name,
            contact_person,
            contact_number,
            email,
            address,
            default_payment_terms,
            status
        };

        try {
            if (this.state.editingSupplier) {
                await apiClient.put(`suppliers/${this.state.editingSupplier}`, payload);
                State.setAlert(`Supplier "${name}" updated successfully.`, 'success');
            } else {
                await apiClient.post('suppliers', payload);
                State.setAlert(`Supplier "${name}" registered successfully.`, 'success');
            }

            this.closeModal();
            await this.loadSuppliers();
        } catch (err) {
            errorDiv.textContent = err.message || 'Failed to save supplier.';
            errorDiv.style.display = 'block';
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Supplier';
        }
    },

    async confirmDelete(id, name) {
        if (!confirm(`Are you sure you want to delete supplier "${name}"?`)) {
            return;
        }

        try {
            await apiClient.delete(`suppliers/${id}`);
            State.setAlert(`Supplier "${name}" was deleted.`, 'info');
            await this.loadSuppliers();
        } catch (err) {
            State.setAlert(err.message || 'Error deleting supplier.', 'danger');
        }
    }
};
