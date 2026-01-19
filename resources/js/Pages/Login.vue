<template>
    <Head title="Sign In" />
    <div class="min-h-screen">
        <main class="relative overflow-hidden">
            <section class="mesh-gradient relative flex min-h-screen items-center overflow-hidden pb-20 pt-10 sm:pt-16 lg:pb-28 lg:pt-16">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">
                        <div class="text-center lg:col-span-6 lg:text-left">
                            <div class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-base-100/70 px-3 py-1 text-xs font-semibold text-primary shadow-sm animate-fade-up">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary/60 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                </span>
                                NEW v2.0 RELEASE
                            </div>
                            <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-base-content md:text-5xl lg:text-6xl animate-fade-up animate-delay-150">
                                Sign documents with a <span class="text-gradient">trusted</span> flow.
                            </h1>
                            <p class="mt-6 text-base text-base-content/70 sm:text-lg animate-fade-up animate-delay-300">
                                The secure, legally binding way to manage your digital agreements. Streamline your workflow
                                with bank-grade encryption and seamless integration.
                            </p>
                            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start animate-fade-up animate-delay-300">
                                <div class="flex items-center gap-2 rounded-full border border-base-200 bg-base-100/70 px-4 py-2 text-xs font-semibold text-base-content/70">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    Enterprise-ready security
                                </div>
                                <div class="flex items-center gap-2 rounded-full border border-base-200 bg-base-100/70 px-4 py-2 text-xs font-semibold text-base-content/70">
                                    <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                    Audit-ready workflows
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-6">
                            <div class="relative mx-auto max-w-md">
                                <div class="absolute -top-10 -right-10 h-56 w-56 rounded-full bg-primary/30 blur-3xl animate-blob"></div>
                                <div class="absolute -bottom-10 -left-10 h-56 w-56 rounded-full bg-indigo-200/70 blur-3xl animate-blob animation-delay-2000"></div>
                                <div class="card border border-base-200 bg-base-100/90 shadow-2xl">
                                    <div class="card-body gap-6">
                                        <div class="text-center">
                                            <h2 class="text-2xl font-bold">Welcome back</h2>
                                            <p class="text-sm text-base-content/60">Log in to your secure dashboard</p>
                                        </div>

                                        <div v-if="isInvite" class="rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-base-content/70">
                                            <p class="font-semibold text-primary">Invitation received</p>
                                            <p class="text-xs text-base-content/60">You've been invited to sign a document. Continue with Google to access it.</p>
                                        </div>

                                        <div v-if="loading" class="flex items-center gap-3 rounded-2xl border border-base-200 bg-base-200/60 px-4 py-3">
                                            <span class="loading loading-spinner loading-md text-primary"></span>
                                            <div>
                                                <p class="text-sm font-semibold">Verifying Identity</p>
                                                <p class="text-xs text-base-content/60">Please wait a moment.</p>
                                            </div>
                                        </div>

                                        <button v-else @click="googleLogin" class="btn btn-primary btn-block shadow-lg shadow-primary/20">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white">
                                                <img class="h-4 w-4" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google Logo" />
                                            </span>
                                            Continue with Google
                                        </button>

                                        <div class="divider text-xs uppercase tracking-[0.2em] text-base-content/40">Protected by</div>
                                        <div class="grid grid-cols-1 gap-2 text-[10px] sm:grid-cols-3">
                                            <div class="flex flex-col items-center gap-2 rounded-xl border border-base-200 bg-base-100/80 px-2 py-3 font-semibold text-base-content/70">
                                                <span class="rounded-lg bg-emerald-100 p-2 text-emerald-600">
                                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 3l7 4v5c0 5-3 9-7 9s-7-4-7-9V7l7-4z"></path>
                                                        <path d="M9 12l2 2 4-4"></path>
                                                    </svg>
                                                </span>
                                                <span class="text-center leading-tight">Legally<br />Binding</span>
                                            </div>
                                            <div class="flex flex-col items-center gap-2 rounded-xl border border-base-200 bg-base-100/80 px-2 py-3 font-semibold text-base-content/70">
                                                <span class="rounded-lg bg-primary/10 p-2 text-primary">
                                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                                                        <path d="M7 11V8a5 5 0 0 1 10 0v3"></path>
                                                    </svg>
                                                </span>
                                                <span class="text-center leading-tight">256-bit<br />Encryption</span>
                                            </div>
                                            <div class="flex flex-col items-center gap-2 rounded-xl border border-base-200 bg-base-100/80 px-2 py-3 font-semibold text-base-content/70">
                                                <span class="rounded-lg bg-sky-100 p-2 text-sky-600">
                                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 3l7 4v5c0 5-3 9-7 9s-7-4-7-9V7l7-4z"></path>
                                                        <path d="M12 11v4"></path>
                                                    </svg>
                                                </span>
                                                <span class="text-center leading-tight">ID<br />Verified</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-base-100 py-16 sm:py-20">
                <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="group card border border-base-200 bg-base-100/90 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="card-body gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition duration-300 group-hover:scale-110">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 7h6l3-3 3 3h6v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7z"></path>
                                        <path d="M12 9v6"></path>
                                        <path d="M9 12h6"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold">Legally Compliant</h3>
                                <p class="text-sm text-base-content/60 leading-relaxed">
                                    Fully compliant with eIDAS, ESIGN, and UETA regulations worldwide ensuring your documents hold up in court.
                                </p>
                            </div>
                        </div>
                        <div class="group card border border-base-200 bg-base-100/90 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="card-body gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition duration-300 group-hover:scale-110">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 3l7 4v5c0 5-3 9-7 9s-7-4-7-9V7l7-4z"></path>
                                        <path d="M12 11v4"></path>
                                        <path d="M12 8h.01"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold">Bank-Grade Security</h3>
                                <p class="text-sm text-base-content/60 leading-relaxed">
                                    256-bit SSL encryption and secure ISO 27001 certified data centers keep your sensitive information safe.
                                </p>
                            </div>
                        </div>
                        <div class="group card border border-base-200 bg-base-100/90 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="card-body gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition duration-300 group-hover:scale-110">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M4 19h16"></path>
                                        <path d="M8 17V7l8 3v7"></path>
                                        <path d="M6 7l6-4 6 4"></path>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold">Audit Trails</h3>
                                <p class="text-sm text-base-content/60 leading-relaxed">
                                    Comprehensive logs tracking every action, IP address, and timestamp for complete transparency and accountability.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { formatApiError } from '../utils/errors';

const loading = ref(false);
const authStore = useAuthStore();
const toastStore = useToastStore();
const isInvite = ref(false);
const inviteEmail = ref('');
const inviteToken = ref('');
const inviteCode = ref('');
const inviteMismatch = ref(null);

const clearInviteState = () => {
    isInvite.value = false;
    inviteEmail.value = '';
    inviteToken.value = '';
    inviteCode.value = '';
    inviteMismatch.value = null;
};

const setInviteMismatch = (expectedEmail, currentEmail) => {
    inviteMismatch.value = {
        expectedEmail,
        currentEmail,
    };
    toastStore.error(
        `This invitation is for ${expectedEmail}, but you are signed in as ${currentEmail}. Please switch accounts.`
    );
};

const acceptStoredInvite = async () => {
    const storedInviteCode = sessionStorage.getItem('invite_code');
    const storedInviteEmail = sessionStorage.getItem('invite_email');
    const storedInviteToken = sessionStorage.getItem('invite_token');

    try {
        if (storedInviteCode) {
            await axios.post('/api/invitations/accept', { code: storedInviteCode });
            sessionStorage.removeItem('invite_code');
        } else if (storedInviteEmail && storedInviteToken) {
            await axios.post('/api/invitations/accept', { email: storedInviteEmail, token: storedInviteToken });
            sessionStorage.removeItem('invite_email');
            sessionStorage.removeItem('invite_token');
        }
    } catch (error) {
        toastStore.error(formatApiError('Failed to accept invitation', error));
    }
};

const handleInviteCode = async (code) => {
    try {
        const validateResp = await axios.get('/api/invitations/validate', {
            params: { code },
        });

        isInvite.value = true;
        inviteCode.value = code;
        const validatePayload = validateResp.data?.data ?? validateResp.data;
        inviteEmail.value = validatePayload?.email ?? '';
        sessionStorage.setItem('invite_code', code);
        if (inviteEmail.value) {
            sessionStorage.setItem('invite_email', inviteEmail.value);
        }
        sessionStorage.removeItem('invite_token');

        if (authStore.isAuthenticated) {
            await authStore.fetchUser();
            const currentEmail = authStore.user?.email;
            if (currentEmail && inviteEmail.value && currentEmail.toLowerCase() === inviteEmail.value.toLowerCase()) {
                await axios.post('/api/invitations/accept', { code });
                sessionStorage.removeItem('invite_code');
                router.visit('/dashboard');
                return true;
            }
            if (inviteEmail.value && currentEmail) {
                setInviteMismatch(inviteEmail.value, currentEmail);
            }
            authStore.logout();
        }
    } catch (error) {
        clearInviteState();
        sessionStorage.removeItem('invite_code');
        sessionStorage.removeItem('invite_email');
        toastStore.error(formatApiError('Invalid invitation', error));
    }
    return false;
};

const handleInviteLegacy = async (email, token) => {
    try {
        await axios.get('/api/invitations/validate', {
            params: { email, token },
        });

        isInvite.value = true;
        inviteEmail.value = email;
        inviteToken.value = token;

        sessionStorage.setItem('invite_email', email);
        sessionStorage.setItem('invite_token', token);
        sessionStorage.removeItem('invite_code');

        if (authStore.isAuthenticated) {
            await authStore.fetchUser();
            const currentEmail = authStore.user?.email;
            if (currentEmail && currentEmail.toLowerCase() === email.toLowerCase()) {
                await axios.post('/api/invitations/accept', { email, token });
                sessionStorage.removeItem('invite_email');
                sessionStorage.removeItem('invite_token');
                router.visit('/dashboard');
                return true;
            }
            if (currentEmail) {
                setInviteMismatch(email, currentEmail);
            }
            authStore.logout();
        }
    } catch (error) {
        clearInviteState();
        sessionStorage.removeItem('invite_email');
        sessionStorage.removeItem('invite_token');
        toastStore.error(formatApiError('Invalid invitation', error));
    }
    return false;
};

const handleAuthToken = async (token) => {
    loading.value = true;
    try {
        await authStore.setAuth(token, {});
        await authStore.fetchUser();
        await acceptStoredInvite();
        router.visit('/dashboard');
    } catch (error) {
        console.error('Login Error', error);
        authStore.logout();
        toastStore.error(formatApiError('Authentication failed', error));
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const inviteCodeParam = params.get('code');
    const inviteEmailParam = params.get('email');
    const inviteTokenParam = params.get('token');
    const authCode = params.get('auth_code');
    const authToken = !inviteEmailParam && !inviteCodeParam ? params.get('token') : null;

    if (inviteCodeParam) {
        const accepted = await handleInviteCode(String(inviteCodeParam));
        if (accepted) return;
    } else if (inviteEmailParam && inviteTokenParam) {
        const accepted = await handleInviteLegacy(String(inviteEmailParam), String(inviteTokenParam));
        if (accepted) return;
    }

    if (authCode) {
        try {
            const exchangeResponse = await axios.get('/api/auth/exchange', {
                params: { code: String(authCode) },
            });
            const exchangePayload = exchangeResponse.data?.data ?? exchangeResponse.data;
            const exchangedToken = exchangePayload?.token;
            if (!exchangedToken) {
                throw new Error('Missing token from exchange');
            }
            await handleAuthToken(exchangedToken);
        } catch (error) {
            console.error('Login Error', error);
            authStore.logout();
            toastStore.error(formatApiError('Authentication failed', error));
        }
        return;
    }

    if (authToken) {
        await handleAuthToken(String(authToken));
        return;
    }

    if (authStore.isAuthenticated) {
        router.visit('/dashboard');
    }
});

const googleLogin = () => {
    window.location.href = '/api/auth/google/redirect';
};
</script>
