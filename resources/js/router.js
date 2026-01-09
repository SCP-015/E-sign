import { createRouter, createWebHistory } from 'vue-router';
import Login from './components/Login.vue';
import Dashboard from './components/Dashboard.vue';
import SignatureSetup from './components/SignatureSetup.vue';

const routes = [
    { path: '/', component: Login, name: 'Login' },
    { path: '/dashboard', component: Dashboard, name: 'Dashboard' },
    { path: '/signature-setup', component: SignatureSetup, name: 'SignatureSetup' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
