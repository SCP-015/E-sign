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
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);
