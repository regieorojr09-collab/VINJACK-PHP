import { apiClient } from '../client.js';
import { State } from '../state.js';

/**
 * User Management View
 * 
 * Provides administrative controls for system accounts:
 * - User directory listing with role & status badges
 * - Account creation with role assignment (Super Admin, Admin, Salesperson)
 * - Profile and credential updates
 * - Account lock/unlock and status toggling
 */
export const UsersPage = {
    state: {
        users: [],
        filters: {
            search: '',
            role: '',
            status: ''
        },
        editingUser: null
    },

    render() {
        const isSuperAdmin = State.user && State.user.role === 'Super Admin';

        return `
            <div class="view-container">
                <!-- Page Header -->
                <div class="page-header flex justify-between items-center mb-4">
                    <div>
                        <h1 class="page-title text-2xl font-bold">User Management & RBAC</h1>
                        <p class="text-muted text-sm">Manage system user credentials, employee access levels, and account security.</p>
                    </div>
                    ${isSuperAdmin ? `
                        <button id="add-user-btn" class="btn btn-primary">
                            <span>+</span> Create New User
                        </button>
                    ` : ''}
                </div>

                <!-- Metrics Ribbon -->
                <div class="grid grid-cols-4 gap-3 mb-4">
                    <div class="metric-card">
                        <span class="metric-label">Total Accounts</span>
                        <div class="metric-value font-mono" id="metric-total-users">0</div>
                    </div>
                    <div class="metric-card border-purple">
                        <span class="metric-label">Super Admins</span>
                        <div class="metric-value text-purple font-mono" id="metric-super-admins">0</div>
                    </div>
                    <div class="metric-card border-blue">
                        <span class="metric-label">Shop Admins</span>
                        <div class="metric-value font-mono" id="metric-admins" style="color: var(--primary);">0</div>
                    </div>
                    <div class="metric-card border-success">
                        <span class="metric-label">Sales Staff (Active)</span>
                        <div class="metric-value text-success font-mono" id="metric-salespersons">0</div>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="card p-3 mb-4 filter-bar">
                    <div class="grid grid-cols-3 gap-3 items-center">
                        <div>
                            <input type="text" id="filter-user-search" class="form-control" placeholder="🔍 Search name, username, or email..." value="${this.state.filters.search}">
                        </div>
                        <div>
                            <select id="filter-user-role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="Super Admin">Super Admin</option>
                                <option value="Admin">Admin</option>
                                <option value="Salesperson">Salesperson</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <select id="filter-user-status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="locked">Locked</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                            <button id="refresh-users-btn" class="btn btn-secondary" title="Refresh List">🔄</button>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card p-0 table-card">
                    <div class="table-responsive">
                        <table class="data-table" id="users-table">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th class="text-center">Status</th>
                                    <th>Date Created</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-tbody">
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">Loading user accounts...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Create / Edit User Modal -->
            <div id="user-modal" class="modal-backdrop hidden">
                <div class="modal-dialog">
                    <div class="modal-header flex justify-between items-center border-bottom pb-3">
                        <h2 id="user-modal-title" class="text-lg font-bold">Create New User</h2>
                        <button id="close-user-modal-btn" class="close-btn text-muted">&times;</button>
                    </div>

                    <form id="user-form" class="mt-3">
                        <div id="user-modal-error" class="alert alert-danger mb-3" style="display: none;"></div>

                        <div class="form-group mb-3">
                            <label for="user-fullname" class="text-xs font-semibold text-muted block mb-1">Full Name *</label>
                            <input type="text" id="user-fullname" class="form-control" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="user-username" class="text-xs font-semibold text-muted block mb-1">Username *</label>
                                <input type="text" id="user-username" class="form-control" placeholder="e.g. jdoe" required>
                            </div>
                            <div class="form-group">
                                <label for="user-email" class="text-xs font-semibold text-muted block mb-1">Email Address *</label>
                                <input type="email" id="user-email" class="form-control" placeholder="e.g. jdoe@vinjack.com" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="form-group">
                                <label for="user-role" class="text-xs font-semibold text-muted block mb-1">Role *</label>
                                <select id="user-role" class="form-control" required>
                                    <option value="Salesperson">Salesperson (Counter)</option>
                                    <option value="Admin">Admin (Shop Lead)</option>
                                    <option value="Super Admin">Super Admin (Full Access)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="user-status" class="text-xs font-semibold text-muted block mb-1">Account Status</label>
                                <select id="user-status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="locked">Locked</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="user-password" class="text-xs font-semibold text-muted block mb-1" id="user-password-label">Password * (min 6 characters)</label>
                            <input type="password" id="user-password" class="form-control" placeholder="••••••••" minlength="6">
                            <span class="text-xs text-muted block mt-1" id="user-password-help"></span>
                        </div>

                        <div class="modal-footer flex justify-end gap-2 border-top pt-3">
                            <button type="button" id="cancel-user-btn" class="btn btn-secondary">Cancel</button>
                            <button type="submit" id="save-user-btn" class="btn btn-primary">Save User</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    },

    async init() {
        this.bindEvents();
        await this.loadUsers();
    },

    bindEvents() {
        // Search & Filter Events
        document.getElementById('filter-user-search')?.addEventListener('input', (e) => {
            this.state.filters.search = e.target.value.toLowerCase();
            this.renderUsersTable();
        });

        document.getElementById('filter-user-role')?.addEventListener('change', (e) => {
            this.state.filters.role = e.target.value;
            this.renderUsersTable();
        });

        document.getElementById('filter-user-status')?.addEventListener('change', (e) => {
            this.state.filters.status = e.target.value;
            this.renderUsersTable();
        });

        document.getElementById('refresh-users-btn')?.addEventListener('click', () => {
            this.loadUsers();
        });

        // Add User Button
        document.getElementById('add-user-btn')?.addEventListener('click', () => {
            this.openCreateModal();
        });

        // Modal Close Handlers
        document.getElementById('close-user-modal-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('cancel-user-btn')?.addEventListener('click', () => {
            this.closeModal();
        });

        // Form Submit Handler
        document.getElementById('user-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSaveUser();
        });
    },

    async loadUsers() {
        try {
            const res = await apiClient.get('users');
            if (res.success) {
                this.state.users = res.data || [];
                this.renderUsersTable();
                this.updateMetrics();
            }
        } catch (err) {
            console.error('Failed to load users:', err);
            const tbody = document.getElementById('users-tbody');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load user accounts: ${err.message}</td></tr>`;
            }
        }
    },

    updateMetrics() {
        const users = this.state.users;
        const total = users.length;
        const superAdmins = users.filter(u => u.role === 'Super Admin').length;
        const admins = users.filter(u => u.role === 'Admin').length;
        const salespersons = users.filter(u => u.role === 'Salesperson' && u.status === 'active').length;

        const totalEl = document.getElementById('metric-total-users');
        const superEl = document.getElementById('metric-super-admins');
        const adminEl = document.getElementById('metric-admins');
        const salesEl = document.getElementById('metric-salespersons');

        if (totalEl) totalEl.textContent = total;
        if (superEl) superEl.textContent = superAdmins;
        if (adminEl) adminEl.textContent = admins;
        if (salesEl) salesEl.textContent = salespersons;
    },

    renderUsersTable() {
        const tbody = document.getElementById('users-tbody');
        if (!tbody) return;

        let filtered = this.state.users;

        if (this.state.filters.search) {
            const q = this.state.filters.search;
            filtered = filtered.filter(u => 
                (u.full_name && u.full_name.toLowerCase().includes(q)) ||
                (u.username && u.username.toLowerCase().includes(q)) ||
                (u.email && u.email.toLowerCase().includes(q))
            );
        }

        if (this.state.filters.role) {
            filtered = filtered.filter(u => u.role === this.state.filters.role);
        }

        if (this.state.filters.status) {
            filtered = filtered.filter(u => u.status === this.state.filters.status);
        }

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">No user accounts found matching criteria.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map(user => {
            const roleBadgeClass = user.role === 'Super Admin' ? 'badge-purple' : (user.role === 'Admin' ? 'badge-primary' : 'badge-success');
            
            let statusBadgeClass = 'badge-secondary';
            if (user.status === 'active') statusBadgeClass = 'badge-success';
            else if (user.status === 'locked') statusBadgeClass = 'badge-danger';
            else if (user.status === 'pending') statusBadgeClass = 'badge-warning';

            const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString() : '—';
            const isCurrentUser = State.user && State.user.id === user.id;

            return `
                <tr>
                    <td class="font-semibold">
                        ${user.full_name}
                        ${isCurrentUser ? '<span class="badge badge-sm badge-secondary ml-1">You</span>' : ''}
                    </td>
                    <td class="font-mono text-sm">${user.username}</td>
                    <td class="text-sm">${user.email}</td>
                    <td>
                        <span class="badge ${roleBadgeClass}">${user.role}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${statusBadgeClass}">${user.status}</span>
                    </td>
                    <td class="text-sm text-muted">${createdDate}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <button class="btn btn-secondary btn-sm edit-user-btn" data-id="${user.id}" title="Edit User">
                                ✏️ Edit
                            </button>
                            ${user.status === 'locked' ? `
                                <button class="btn btn-warning btn-sm unlock-user-btn" data-id="${user.id}" title="Unlock Account">
                                    🔓 Unlock
                                </button>
                            ` : ''}
                            ${user.id !== 1 && !isCurrentUser ? `
                                <button class="btn ${user.status === 'active' ? 'btn-secondary' : 'btn-primary'} btn-sm toggle-status-btn" data-id="${user.id}" data-status="${user.status === 'active' ? 'inactive' : 'active'}" title="Toggle Active">
                                    ${user.status === 'active' ? 'Deactivate' : 'Activate'}
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        // Bind table action buttons
        tbody.querySelectorAll('.edit-user-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                this.openEditModal(id);
            });
        });

        tbody.querySelectorAll('.unlock-user-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                this.handleStatusToggle(id, 'active');
            });
        });

        tbody.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const nextStatus = btn.dataset.status;
                this.handleStatusToggle(id, nextStatus);
            });
        });
    },

    openCreateModal() {
        this.state.editingUser = null;
        document.getElementById('user-modal-title').textContent = 'Create New User Account';
        document.getElementById('user-modal-error').style.display = 'none';

        document.getElementById('user-fullname').value = '';
        document.getElementById('user-username').value = '';
        document.getElementById('user-email').value = '';
        document.getElementById('user-role').value = 'Salesperson';
        document.getElementById('user-status').value = 'active';

        const pwdInput = document.getElementById('user-password');
        pwdInput.value = '';
        pwdInput.required = true;
        document.getElementById('user-password-label').textContent = 'Password * (min 6 characters)';
        document.getElementById('user-password-help').textContent = 'Required for new accounts.';

        document.getElementById('user-modal')?.classList.remove('hidden');
    },

    openEditModal(id) {
        const user = this.state.users.find(u => u.id === id);
        if (!user) return;

        this.state.editingUser = user;
        document.getElementById('user-modal-title').textContent = `Edit User: ${user.username}`;
        document.getElementById('user-modal-error').style.display = 'none';

        document.getElementById('user-fullname').value = user.full_name || '';
        document.getElementById('user-username').value = user.username || '';
        document.getElementById('user-email').value = user.email || '';
        document.getElementById('user-role').value = user.role || 'Salesperson';
        document.getElementById('user-status').value = user.status || 'active';

        const pwdInput = document.getElementById('user-password');
        pwdInput.value = '';
        pwdInput.required = false;
        document.getElementById('user-password-label').textContent = 'Password (optional)';
        document.getElementById('user-password-help').textContent = 'Leave blank to keep existing password.';

        document.getElementById('user-modal')?.classList.remove('hidden');
    },

    closeModal() {
        document.getElementById('user-modal')?.classList.add('hidden');
    },

    async handleSaveUser() {
        const errEl = document.getElementById('user-modal-error');
        errEl.style.display = 'none';

        const fullName = document.getElementById('user-fullname').value.trim();
        const username = document.getElementById('user-username').value.trim();
        const email = document.getElementById('user-email').value.trim();
        const role = document.getElementById('user-role').value;
        const status = document.getElementById('user-status').value;
        const password = document.getElementById('user-password').value;

        const payload = {
            full_name: fullName,
            username: username,
            email: email,
            role: role,
            status: status
        };

        if (password) {
            payload.password = password;
        }

        try {
            if (this.state.editingUser) {
                // Update
                const res = await apiClient.put(`users/${this.state.editingUser.id}`, payload);
                if (res.success) {
                    State.setAlert('User account updated successfully.', 'success');
                    this.closeModal();
                    await this.loadUsers();
                }
            } else {
                // Create
                const res = await apiClient.post('users', payload);
                if (res.success) {
                    State.setAlert('User account created successfully.', 'success');
                    this.closeModal();
                    await this.loadUsers();
                }
            }
        } catch (err) {
            errEl.textContent = err.message || 'Operation failed.';
            errEl.style.display = 'block';
        }
    },

    async handleStatusToggle(id, nextStatus) {
        try {
            const res = await apiClient.put(`users/${id}/status`, { status: nextStatus });
            if (res.success) {
                State.setAlert(`User status updated to ${nextStatus}.`, 'success');
                await this.loadUsers();
            }
        } catch (err) {
            State.setAlert('Failed to update status: ' + err.message, 'danger');
        }
    }
};
