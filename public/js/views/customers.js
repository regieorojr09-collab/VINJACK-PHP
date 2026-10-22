import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * Customers Directory View
 * 
 * Manages customer accounts, contact directories, and associated motorcycle profiles.
 */
export const CustomersPage = {
    state: {
        customers: [],
        searchQuery: '',
        loading: false,
        editingCustomer: null,
        activeCustomerIdForMoto: null
    },

    render() {
        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">Customer Directory</h1>
                        <p class="text-muted text-sm">Manage customer contact profiles, service histories, and registered motorcycle records.</p>
                    </div>
                    <button id="add-customer-btn" class="btn btn-primary">
                        <span>+</span> Register Customer
                    </button>
                </div>

                <!-- Metrics Ribbon -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="metric-card">
                        <span class="metric-label">Registered Customers</span>
                        <div class="metric-value font-mono" id="metric-total-customers">0</div>
                    </div>
                    <div class="metric-card border-blue">
                        <span class="metric-label">Linked Motorcycles</span>
                        <div class="metric-value font-mono text-primary" id="metric-total-motos">0</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Active POS Buyers</span>
                        <div class="metric-value font-mono text-success" id="metric-active-buyers">0</div>
                    </div>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="flex justify-between items-center gap-3">
                        <div class="w-full" style="max-width: 400px;">
                            <input type="text" id="filter-customer-search" class="form-control" placeholder="🔍 Search customer name, phone, or email..." value="${this.state.searchQuery}">
                        </div>
                        <div class="flex gap-2">
                            <button id="refresh-customers-btn" class="btn btn-secondary" title="Refresh Directory">🔄 Refresh</button>
                        </div>
                    </div>
                </div>

                <!-- Customers Data Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="customers-table">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Contact Information</th>
                                    <th>Address</th>
                                    <th>Linked Motorcycle(s)</th>
                                    <th class="text-center">POS Orders</th>
                                    <th class="text-right" style="width: 140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customers-tbody">
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">Loading customer profiles...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Customer Create / Edit Modal -->
            <div id="customer-form-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3 mb-3">
                        <h2 class="font-bold text-lg" id="customer-modal-title">Register New Customer</h2>
                        <button id="close-customer-form-btn" class="close-btn">&times;</button>
                    </div>
                    <form id="customer-form">
                        <div class="form-group mb-3">
                            <label for="cust-name-input" class="font-semibold text-sm">Full Name *</label>
                            <input type="text" id="cust-name-input" class="form-control" required placeholder="e.g., Juan Dela Cruz">
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="cust-phone-input" class="font-semibold text-sm">Phone Number</label>
                                <input type="text" id="cust-phone-input" class="form-control" placeholder="+63 917 123 4567">
                            </div>
                            <div class="form-group">
                                <label for="cust-email-input" class="font-semibold text-sm">Email Address</label>
                                <input type="email" id="cust-email-input" class="form-control" placeholder="juan@example.com">
                            </div>
                        </div>
                        <div class="form-group mb-4">
                            <label for="cust-address-input" class="font-semibold text-sm">Address</label>
                            <textarea id="cust-address-input" class="form-control" rows="2" placeholder="Street, Barangay, City..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-customer-form-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-customer-btn" class="btn btn-primary">Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Motorcycle Modal -->
            <div id="moto-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3 mb-3">
                        <h2 class="font-bold text-lg">Add Motorcycle to Profile</h2>
                        <button id="close-moto-modal-btn" class="close-btn">&times;</button>
                    </div>
                    <form id="moto-form">
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="moto-make-input" class="font-semibold text-sm">Make *</label>
                                <input type="text" id="moto-make-input" class="form-control" required placeholder="e.g., Yamaha, Honda">
                            </div>
                            <div class="form-group">
                                <label for="moto-model-input" class="font-semibold text-sm">Model *</label>
                                <input type="text" id="moto-model-input" class="form-control" required placeholder="e.g., Aerox 155, Click 125i">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-3">
                            <div class="form-group">
                                <label for="moto-year-input" class="font-semibold text-sm">Year</label>
                                <input type="number" id="moto-year-input" class="form-control" placeholder="2023" min="1980" max="2030">
                            </div>
                            <div class="form-group">
                                <label for="moto-color-input" class="font-semibold text-sm">Color</label>
                                <input type="text" id="moto-color-input" class="form-control" placeholder="Black">
                            </div>
                            <div class="form-group">
                                <label for="moto-plate-input" class="font-semibold text-sm">Plate Number</label>
                                <input type="text" id="moto-plate-input" class="form-control" placeholder="ND-12345">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-moto-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-moto-btn" class="btn btn-primary">Add Vehicle</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await this.loadCustomers();
    },

    bindEvents() {
        // Search filter with debounce
        let searchTimer;
        document.getElementById('filter-customer-search')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                this.state.searchQuery = e.target.value.trim();
                this.loadCustomers();
            }, 250);
        });

        // Refresh button
        document.getElementById('refresh-customers-btn')?.addEventListener('click', () => {
            this.loadCustomers();
        });

        // Add Customer Modal
        document.getElementById('add-customer-btn')?.addEventListener('click', () => {
            this.openCustomerModal();
        });
        document.getElementById('close-customer-form-btn')?.addEventListener('click', () => {
            document.getElementById('customer-form-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-customer-form-btn')?.addEventListener('click', () => {
            document.getElementById('customer-form-modal')?.classList.add('hidden');
        });

        // Save Customer Form Submit
        document.getElementById('customer-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveCustomer();
        });

        // Motorcycle Modal controls
        document.getElementById('close-moto-modal-btn')?.addEventListener('click', () => {
            document.getElementById('moto-modal')?.classList.add('hidden');
        });
        document.getElementById('cancel-moto-btn')?.addEventListener('click', () => {
            document.getElementById('moto-modal')?.classList.add('hidden');
        });
        document.getElementById('moto-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveMotorcycle();
        });

        // Table Action Delegation
        document.getElementById('customers-tbody')?.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-cust-btn');
            if (editBtn) {
                const id = parseInt(editBtn.dataset.id, 10);
                const cust = this.state.customers.find(c => c.id === id);
                if (cust) this.openCustomerModal(cust);
                return;
            }

            const addMotoBtn = e.target.closest('.add-moto-btn');
            if (addMotoBtn) {
                const id = parseInt(addMotoBtn.dataset.id, 10);
                this.openMotoModal(id);
                return;
            }
        });
    },

    async loadCustomers() {
        this.state.loading = true;
        const tbody = document.getElementById('customers-tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">Refreshing customers directory...</td></tr>`;
        }

        try {
            const params = {};
            if (this.state.searchQuery) params.search = this.state.searchQuery;

            const res = await apiClient.get('customers', params);
            if (res.success && Array.isArray(res.data)) {
                this.state.customers = res.data;
            } else {
                this.state.customers = [];
            }
        } catch (err) {
            console.error('Failed to load customers:', err);
            State.setAlert('Unable to load customers directory: ' + (err.message || 'Unknown error'), 'danger', 4000);
            this.state.customers = [];
        } finally {
            this.state.loading = false;
            this.renderTable();
            this.updateMetrics();
        }
    },

    renderTable() {
        const tbody = document.getElementById('customers-tbody');
        if (!tbody) return;

        if (this.state.customers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        No customer profiles found. Click "+ Register Customer" to add one.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = this.state.customers.map(c => {
            const motos = Array.isArray(c.motorcycles) ? c.motorcycles : [];
            const motoHtml = motos.length > 0 
                ? motos.map(m => `
                    <div class="mb-1">
                        <span class="badge badge-secondary text-xs">
                            🏍️ ${this.escapeHtml(m.make)} ${this.escapeHtml(m.model)} 
                            ${m.plate_number ? `(${this.escapeHtml(m.plate_number)})` : ''}
                        </span>
                    </div>
                `).join('')
                : `<span class="text-xs text-muted">None registered</span>`;

            return `
                <tr>
                    <td>
                        <div class="font-semibold text-sm">${this.escapeHtml(c.name)}</div>
                        <div class="text-xs font-mono text-muted">#CUST-${c.id}</div>
                    </td>
                    <td>
                        <div class="text-sm">${c.phone ? `📞 ${this.escapeHtml(c.phone)}` : '<span class="text-muted text-xs">No phone</span>'}</div>
                        <div class="text-xs text-muted">${c.email ? `✉️ ${this.escapeHtml(c.email)}` : ''}</div>
                    </td>
                    <td class="text-sm text-muted">${this.escapeHtml(c.address || '—')}</td>
                    <td>
                        ${motoHtml}
                        <button class="btn btn-sm btn-link text-primary text-xs add-moto-btn p-0 mt-1 cursor-pointer" data-id="${c.id}">
                            + Link Vehicle
                        </button>
                    </td>
                    <td class="text-center">
                        <span class="badge ${parseInt(c.total_purchases || 0, 10) > 0 ? 'badge-success' : 'badge-secondary'}">
                            ${c.total_purchases || 0} Orders
                        </span>
                    </td>
                    <td class="text-right">
                        <button class="btn btn-sm btn-secondary edit-cust-btn" data-id="${c.id}">
                            Edit
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    updateMetrics() {
        const totalCust = this.state.customers.length;
        let totalMotos = 0;
        let activeBuyers = 0;

        this.state.customers.forEach(c => {
            if (Array.isArray(c.motorcycles)) {
                totalMotos += c.motorcycles.length;
            }
            if (parseInt(c.total_purchases || 0, 10) > 0) {
                activeBuyers++;
            }
        });

        const totalEl = document.getElementById('metric-total-customers');
        const motoEl = document.getElementById('metric-total-motos');
        const buyerEl = document.getElementById('metric-active-buyers');

        if (totalEl) totalEl.textContent = totalCust;
        if (motoEl) motoEl.textContent = totalMotos;
        if (buyerEl) buyerEl.textContent = activeBuyers;
    },

    openCustomerModal(cust = null) {
        this.state.editingCustomer = cust;
        const titleEl = document.getElementById('customer-modal-title');
        const nameInput = document.getElementById('cust-name-input');
        const phoneInput = document.getElementById('cust-phone-input');
        const emailInput = document.getElementById('cust-email-input');
        const addrInput = document.getElementById('cust-address-input');

        if (cust) {
            if (titleEl) titleEl.textContent = 'Edit Customer Profile';
            if (nameInput) nameInput.value = cust.name || '';
            if (phoneInput) phoneInput.value = cust.phone || '';
            if (emailInput) emailInput.value = cust.email || '';
            if (addrInput) addrInput.value = cust.address || '';
        } else {
            if (titleEl) titleEl.textContent = 'Register New Customer';
            if (nameInput) nameInput.value = '';
            if (phoneInput) phoneInput.value = '';
            if (emailInput) emailInput.value = '';
            if (addrInput) addrInput.value = '';
        }

        document.getElementById('customer-form-modal')?.classList.remove('hidden');
    },

    openMotoModal(customerId) {
        this.state.activeCustomerIdForMoto = customerId;
        const form = document.getElementById('moto-form');
        if (form) form.reset();
        document.getElementById('moto-modal')?.classList.remove('hidden');
    },

    async saveCustomer() {
        const name = document.getElementById('cust-name-input')?.value.trim();
        const phone = document.getElementById('cust-phone-input')?.value.trim();
        const email = document.getElementById('cust-email-input')?.value.trim();
        const address = document.getElementById('cust-address-input')?.value.trim();

        if (!name) {
            State.setAlert('Customer full name is required.', 'warning');
            return;
        }

        const payload = { name, phone, email, address };

        try {
            if (this.state.editingCustomer) {
                const res = await apiClient.put(`customers/${this.state.editingCustomer.id}`, payload);
                if (res.success) {
                    State.setAlert('Customer profile updated successfully.', 'success');
                } else {
                    State.setAlert(res.message || 'Failed to update customer.', 'danger');
                }
            } else {
                const res = await apiClient.post('customers', payload);
                if (res.success) {
                    State.setAlert('Customer registered successfully.', 'success');
                } else {
                    State.setAlert(res.message || 'Failed to create customer.', 'danger');
                }
            }
            document.getElementById('customer-form-modal')?.classList.add('hidden');
            await this.loadCustomers();
        } catch (err) {
            console.error('Error saving customer:', err);
            State.setAlert('Error saving customer profile: ' + (err.message || 'Unknown error'), 'danger');
        }
    },

    async saveMotorcycle() {
        const customerId = this.state.activeCustomerIdForMoto;
        if (!customerId) return;

        const make = document.getElementById('moto-make-input')?.value.trim();
        const model = document.getElementById('moto-model-input')?.value.trim();
        const year = document.getElementById('moto-year-input')?.value;
        const color = document.getElementById('moto-color-input')?.value.trim();
        const plate_number = document.getElementById('moto-plate-input')?.value.trim();

        if (!make || !model) {
            State.setAlert('Vehicle make and model are required.', 'warning');
            return;
        }

        const payload = {
            make,
            model,
            year: year ? parseInt(year, 10) : null,
            color,
            plate_number: plate_number || null
        };

        try {
            const res = await apiClient.post(`customers/${customerId}/motorcycles`, payload);
            if (res.success) {
                State.setAlert('Motorcycle linked to customer successfully.', 'success');
                document.getElementById('moto-modal')?.classList.add('hidden');
                await this.loadCustomers();
            } else {
                State.setAlert(res.message || 'Failed to link motorcycle.', 'danger');
            }
        } catch (err) {
            console.error('Error adding motorcycle:', err);
            State.setAlert('Error adding motorcycle: ' + (err.message || 'Unknown error'), 'danger');
        }
    },

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};
