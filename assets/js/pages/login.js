import { ApiClient } from '../api/client.js';
import { State } from '../state.js';

export const LoginPage = {
    render() {
        return `
            <div style="max-width: 400px; margin: 40px auto;">
                <div class="card">
                    <h2 class="text-center" style="margin-bottom: 1.5rem;">VinJack Motorworks</h2>
                    <div id="login-error" class="alert alert-danger" style="display: none;"></div>
                    
                    <form id="login-form">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" id="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" id="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </form>

                    <div style="margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <p class="text-center" style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem;">Demo Logins</p>
                        <div class="grid grid-cols-2 gap-4">
                            <button class="btn btn-secondary demo-btn" data-role="Super Admin">Admin</button>
                            <button class="btn btn-secondary demo-btn" data-role="Salesperson">Sales</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    init() {
        const form = document.getElementById('login-form');
        const errorDiv = document.getElementById('login-error');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorDiv.style.display = 'none';
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            try {
                const response = await ApiClient.post('auth/login', { username, password });
                State.setUser(response.user, response.token);
                window.location.hash = '#/pos';
            } catch (err) {
                errorDiv.textContent = err.message;
                errorDiv.style.display = 'block';
            }
        });

        document.querySelectorAll('.demo-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                errorDiv.style.display = 'none';
                try {
                    const response = await ApiClient.post('auth/demo-login', { role: e.target.dataset.role });
                    State.setUser(response.user, response.token);
                    window.location.hash = '#/pos';
                } catch (err) {
                    errorDiv.textContent = err.message;
                    errorDiv.style.display = 'block';
                }
            });
        });
    }
};
