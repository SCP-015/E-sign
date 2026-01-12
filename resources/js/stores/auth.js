import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('user')) || null,
        token: localStorage.getItem('token') || null,
        loading: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        initializeAuth() {
            if (this.token) {
                axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
            }
        },

        async login(credentials) {
            this.loading = true;
            try {
                // If it's a social login redirect, we handle it in component
                // This is for manual login
                const response = await axios.post('/api/auth/login', credentials);
                const payload = response.data?.data ?? response.data;
                const token = payload?.token ?? payload?.access_token ?? null;
                const user = payload?.user?.data ?? payload?.user ?? null;

                if (token) {
                    this.setAuth(token, user);
                }
                return response;
            } catch (error) {
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchUser() {
            if (!this.token) return;
            try {
                const response = await axios.get('/api/user');
                this.user = response.data?.data ?? response.data;
                localStorage.setItem('user', JSON.stringify(this.user));
            } catch (error) {
                if (error.response && error.response.status === 401) {
                    this.logout();
                }
                throw error;
            }
        },

        setAuth(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('token', token);
            localStorage.setItem('user', JSON.stringify(user));
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },

        logout() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            delete axios.defaults.headers.common['Authorization'];
            // Router redirect handle in component or global watcher
        }
    }
});
