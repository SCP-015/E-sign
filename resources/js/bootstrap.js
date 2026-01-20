import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add axios interceptor to automatically include auth token
window.axios.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('token') || localStorage.getItem('api_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        try {
            const url = String(config?.url || '');
            if (url.startsWith('/api/')) {
                const currentOrgStr = localStorage.getItem('currentOrganization');
                if (currentOrgStr) {
                    const currentOrg = JSON.parse(currentOrgStr);
                    if (currentOrg && currentOrg.id) {
                        config.headers['X-Tenant-Id'] = currentOrg.id;
                    }
                }
            }
        } catch (e) {
            // ignore
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);
