import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import Login from '../components/Login.vue';
import Dashboard from '../components/Dashboard.vue';
import SignatureSetup from '../components/SignatureSetup.vue';

const routes = [
    {
        path: '/',
        name: 'Login',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/invite',
        name: 'Invite',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/dashboard',
        name: 'Dashboard',
        component: Dashboard,
        meta: { requiresAuth: true }
    },
    {
        path: '/signature-setup',
        name: 'SignatureSetup',
        component: SignatureSetup,
        meta: { requiresAuth: true }
    },
    {
        path: '/register',
        name: 'Register',
        component: Login,
        meta: { guest: true }
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'NotFound',
        component: Login,
        meta: { guest: true }
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();

    const isInviteRoute = to.name === 'Invite' || to.path === '/invite';
    const hasInviteParams = !!to.query.code || (!!to.query.email && !!to.query.token);

    // Allow access if auth_code is present so Login.vue can exchange it
    if (to.query.auth_code) {
        return next();
    }

    // Always allow invitation landing, even if already authenticated
    if (isInviteRoute || hasInviteParams) {
        return next();
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'Login' });
    } else if (to.meta.guest && authStore.isAuthenticated) {
        next({ name: 'Dashboard' });
    } else {
        next();
    }
});

export default router;
