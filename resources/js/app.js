import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { createPinia } from 'pinia';
import { useAuthStore } from './stores/auth';

const key = 'auth'; // Optional pinia persistence key check if needed, but not installing plugin yet
const pinia = createPinia();

const app = createApp(App);
app.use(pinia);
app.use(router);

// Initialize auth from localStorage before mounting
const authStore = useAuthStore();
authStore.initializeAuth();

app.mount('#app');
