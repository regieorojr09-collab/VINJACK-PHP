import { State } from './state.js';
import { Router } from './router.js';

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Router
    Router.init();

    // Setup Global Layout Listeners
    const sidebar = document.getElementById('sidebar');
    const userInfo = document.getElementById('user-info');
    const logoutBtn = document.getElementById('logout-btn');

    // React to Auth Changes
    State.subscribe('auth:changed', (isAuthenticated) => {
        if (isAuthenticated) {
            sidebar.classList.remove('hidden');
            userInfo.textContent = `Logged in as: ${State.user.username} (${State.user.role})`;
        } else {
            sidebar.classList.add('hidden');
        }
    });

    // Initial Check
    if (State.isAuthenticated()) {
        sidebar.classList.remove('hidden');
        userInfo.textContent = `Logged in as: ${State.user.username} (${State.user.role})`;
    }

    // Logout
    logoutBtn.addEventListener('click', () => {
        State.clearUser();
        window.location.hash = '#/login';
    });
});
