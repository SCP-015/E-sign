<template>
    <div class="flex min-h-screen flex-col">
        <ToastContainer />
        <header v-if="showHeader" class="sticky top-0 z-40 border-b border-base-200 bg-base-100/90 shadow-sm backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:flex-nowrap">
                <Link href="/dashboard" class="flex items-center gap-3 text-base font-semibold">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary shadow-sm">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 20h4l10-10a2.828 2.828 0 1 0-4-4L4 16v4z"></path>
                            <path d="M13 7l4 4"></path>
                        </svg>
                    </span>
                    <span>E-SIGN SECURE</span>
                </Link>

                <nav class="hidden items-center gap-2 md:flex">
                    <Link href="/dashboard" class="btn btn-ghost btn-sm">Dashboard</Link>
                    <Link href="/documents" class="btn btn-ghost btn-sm">Documents</Link>
                    <Link href="/signature-setup" class="btn btn-ghost btn-sm">Signatures</Link>
                    <Link href="/verify" class="btn btn-ghost btn-sm">Verify Page</Link>
                </nav>

                <div class="flex items-center gap-3">
                    <div v-if="isAuthenticated" class="hidden items-center gap-2 text-sm text-base-content/70 sm:flex">
                        <OrganizationSwitcher @organization-changed="handleOrganizationChanged" />
                    </div>

                    <div v-if="isAuthenticated" class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-ghost btn-circle avatar">
                            <div class="w-10 rounded-full ring ring-primary/20 ring-offset-2 ring-offset-base-100">
                                <img v-if="userAvatar" :src="userAvatar" :alt="userName" />
                                <div v-else class="flex h-full w-full items-center justify-center bg-primary/10 text-sm font-semibold text-primary">
                                    {{ userInitial }}
                                </div>
                            </div>
                        </label>
                        <ul tabindex="0" class="menu dropdown-content mt-3 w-48 rounded-box border border-base-200 bg-base-100 p-2 shadow">
                            <li><Link href="/profile">My Profile</Link></li>
                            <li><Link href="/signature-setup">Setup Signature</Link></li>
                            <li class="border-t border-base-200 mt-1 pt-1"><button type="button" @click="logout">Logout</button></li>
                        </ul>
                    </div>

                    <div v-else class="flex items-center gap-2">
                        <Link href="/login" class="btn btn-primary btn-sm">Sign In</Link>
                    </div>

                    <div class="dropdown dropdown-end md:hidden">
                        <label tabindex="0" class="btn btn-ghost btn-circle">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 6h16"></path>
                                <path d="M4 12h16"></path>
                                <path d="M4 18h16"></path>
                            </svg>
                        </label>
                        <ul tabindex="0" class="menu dropdown-content mt-3 w-48 rounded-box border border-base-200 bg-base-100 p-2 shadow">
                            <li><Link href="/dashboard">Dashboard</Link></li>
                            <li><Link href="/documents">Documents</Link></li>
                            <li><Link href="/signature-setup">Signatures</Link></li>
                            <li><Link href="/verify">Verify Page</Link></li>
                            <li v-if="isAuthenticated"><button type="button" @click="logout">Logout</button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-base-200 bg-base-100/80">
            <div class="mx-auto grid w-full max-w-7xl gap-4 px-4 py-6 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_1fr]">
                <div class="space-y-2">
                    <div class="flex items-center gap-3 font-semibold">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 20h4l10-10a2.828 2.828 0 1 0-4-4L4 16v4z"></path>
                            </svg>
                        </span>
                        E-SIGN SECURE
                    </div>
                    <p class="text-sm text-base-content/60">
                        Simplifying digital agreements for everyone, everywhere.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-outline badge-xs">eIDAS</span>
                        <span class="badge badge-outline badge-xs">ESIGN</span>
                        <span class="badge badge-outline badge-xs">UETA</span>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold">Product</h4>
                    <ul class="mt-2 space-y-1 text-xs text-base-content/60">
                        <li><a class="link link-hover" href="#">Features</a></li>
                        <li><a class="link link-hover" href="#">Pricing</a></li>
                        <li><a class="link link-hover" href="#">API</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold">Company</h4>
                    <ul class="mt-2 space-y-1 text-xs text-base-content/60">
                        <li><a class="link link-hover" href="#">About</a></li>
                        <li><a class="link link-hover" href="#">Blog</a></li>
                        <li><a class="link link-hover" href="#">Careers</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold">Legal</h4>
                    <ul class="mt-2 space-y-1 text-xs text-base-content/60">
                        <li><a class="link link-hover" href="#">Privacy</a></li>
                        <li><a class="link link-hover" href="#">Terms</a></li>
                        <li><a class="link link-hover" href="#">Security</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-base-200">
                <div class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-3 text-xs text-base-content/60 sm:flex-row sm:items-center sm:justify-between">
                    <p>&copy; 2026 E-SIGN SECURE. All rights reserved.</p>
                    <div class="flex items-center gap-2">
                        <a class="btn btn-ghost btn-xs btn-circle" href="#" aria-label="LinkedIn">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="currentColor">
                                <path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zm.02 6.9h4v11.1h-4V10.4zm6.4 0h3.8v1.5h.1c.5-.9 1.7-1.9 3.6-1.9 3.9 0 4.6 2.6 4.6 5.9v5.6h-4v-5c0-1.2 0-2.8-1.7-2.8-1.7 0-2 1.3-2 2.7v5.1h-4V10.4z"></path>
                            </svg>
                        </a>
                        <a class="btn btn-ghost btn-xs btn-circle" href="#" aria-label="Twitter">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="currentColor">
                                <path d="M19.9 7.3c.1 4.6-3.2 9.9-9.9 9.9-2 0-3.8-.6-5.4-1.6 1.8.2 3.6-.3 5-1.4-1.5 0-2.7-1-3.1-2.4.5.1 1 .1 1.5-.1-1.6-.3-2.7-1.7-2.7-3.3.5.3 1 .4 1.6.4-1.5-1-1.9-3-.8-4.5 1.7 2 4.2 3.2 7 3.3-.5-2.2 1.2-4.2 3.4-4.2 1 0 1.9.4 2.6 1.1.8-.1 1.6-.4 2.2-.8-.3.8-.8 1.4-1.6 1.8.7-.1 1.4-.3 2-.6-.5.7-1 1.3-1.7 1.8z"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import ToastContainer from '../components/ToastContainer.vue';
import OrganizationSwitcher from '../components/OrganizationSwitcher.vue';
import { useToastStore } from '../stores/toast';

const props = defineProps({
    showHeader: {
        type: Boolean,
        default: true,
    },
});

const authStore = useAuthStore();
const page = usePage();

const isLoginPage = computed(() => page.component === 'Login');
const showHeader = computed(() => props.showHeader && !isLoginPage.value);
const isAuthenticated = computed(() => authStore.isAuthenticated);
const userName = computed(() => authStore.user?.name || 'User');
const userEmail = computed(() => authStore.user?.email || '');
const userAvatar = computed(() => authStore.user?.avatar || '');
const userInitial = computed(() => authStore.user?.name?.charAt(0)?.toUpperCase() || 'U');
const toastStore = useToastStore();

const currentOrganization = ref(null);

const handleOrganizationChanged = (org) => {
    currentOrganization.value = org;
};

const logout = async () => {
    try {
        await axios.post('/api/auth/logout');
        toastStore.success('Logged out successfully.');
    } catch (e) {
        console.error('Logout error:', e);
        toastStore.error('Logout failed. Please try again.');
    }
    authStore.logout();
    router.visit('/login');
};
</script>
