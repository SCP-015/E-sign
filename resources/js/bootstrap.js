import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add axios interceptor to automatically include auth token and tenant context
window.axios.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token') || localStorage.getItem('api_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Auto-inject X-Tenant-Id header for tenant mode
        const currentOrgStr = localStorage.getItem('currentOrganization');
        if (currentOrgStr) {
            try {
                const currentOrg = JSON.parse(currentOrgStr);
                if (currentOrg && currentOrg.id) {
                    config.headers['X-Tenant-Id'] = currentOrg.id;
                }
            } catch (e) {
                // Ignore parse errors
            }
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);
