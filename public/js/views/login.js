import { apiClient } from '../client.js';
import { State } from '../state.js';
// VinJack Motorworks - User Authentication & Session Gateway (Auto-Sync Active)
export const LoginPage = {
    render() {
        return `
            <div class="login-wrapper">
                <div class="card login-card">
                    <div class="login-brand">
                        <div class="brand-badge">VJ</div>
                        <h1>VinJack Motorworks</h1>
                        <p class="text-muted">Sales & Inventory Management System</p>
                    </div>

                    <div id="login-error" class="alert alert-danger" style="display: none;"></div>

                    <form id="login-form" class="mt-4">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" class="form-control" placeholder="Enter username" required autofocus autocomplete="username">
                        </div>
                        <div class="form-group mt-3">
                            <label for="password">Password</label>
                            <input type="password" id="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                        </div>
                        <button type="submit" id="login-btn" class="btn btn-primary btn-block mt-4">Sign In</button>
                    </form>
                </div>
            </div>
        `;
    },

    init() {
        const form = document.getElementById('login-form');
        const errorDiv = document.getElementById('login-error');
        const submitBtn = document.getElementById('login-btn');

        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (errorDiv) errorDiv.style.display = 'none';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Authenticating...';
            }

            const username = document.getElementById('username')?.value.trim() || '';
            const password = document.getElementById('password')?.value || '';

            try {
                const response = await apiClient.post('auth/login', { username, password });
                if (response.success && response.token) {
                    State.setUser(response.user, response.token);
                    State.setAlert(`Welcome back, ${response.user.full_name || response.user.username}!`, 'success');
                    window.location.hash = typeof State.getDefaultRoute === 'function'
                        ? State.getDefaultRoute()
                        : ((response.user.role === 'Super Admin' || response.user.role === 'Admin') ? '#/dashboard' : '#/pos');
                } else {
                    throw new Error(response.message || 'Login failed.');
                }
            } catch (err) {
                if (errorDiv) {
                    errorDiv.textContent = err.message || 'Invalid username or password.';
                    errorDiv.style.display = 'block';
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Sign In';
                }
            }
        });
    }
};
