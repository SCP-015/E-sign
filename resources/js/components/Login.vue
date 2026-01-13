<template>
    <div class="login-wrapper">
        <div class="login-container glass">
            <div class="brand-section">
                <div class="logo-circle">
                    <span class="icon">✍️</span>
                </div>
                <h1>{{ isInvite ? 'Join E-Sign' : 'E-Sign Secure' }}</h1>
                <p v-if="isInvite" class="invite-msg">
                    You've been invited to sign a document. Please continue with Google to access it.
                </p>
                <p v-else class="tagline">The most secure way to sign and manage your documents digitally.</p>
            </div>

            <div class="action-section">
                <div class="features">
                    <div class="feature-item">
                        <span class="check">✓</span> Legally Binding
                    </div>
                    <div class="feature-item">
                        <span class="check">✓</span> Encrypted Storage
                    </div>
                    <div class="feature-item">
                        <span class="check">✓</span> Identity Verified
                    </div>
                </div>

                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div>
                    <p>Verifying Identity...</p>
                </div>

                <button v-else @click="googleLogin" class="btn-google-large">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google Logo" />
                    <span>Continue with Google</span>
                </button>

                <p class="terms">
                    By continuing, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const loading = ref(false);
const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const isInvite = ref(false);
const inviteEmail = ref('');
const inviteToken = ref('');
const inviteCode = ref('');

onMounted(async () => {
    // Check for invitation details (new code-based link)
    if (route.query.code) {
        const code = String(route.query.code);
        try {
            const validateResp = await axios.get('/api/invitations/validate', {
                params: { code },
            });
            const validatePayload = validateResp.data?.data ?? validateResp.data;

            isInvite.value = true;
            inviteCode.value = code;
            inviteEmail.value = validatePayload?.email ?? '';

            // Store in sessionStorage so we can use it after Google OAuth redirect
            sessionStorage.setItem('invite_code', code);
            if (inviteEmail.value) {
                sessionStorage.setItem('invite_email', inviteEmail.value);
            }
            sessionStorage.removeItem('invite_token');

            // If already logged in, either accept immediately (same email) or logout (different email)
            if (authStore.isAuthenticated) {
                await authStore.fetchUser();
                const currentEmail = authStore.user?.email;
                if (currentEmail && inviteEmail.value && String(currentEmail).toLowerCase() === String(inviteEmail.value).toLowerCase()) {
                    await axios.post('/api/invitations/accept', { code });
                    sessionStorage.removeItem('invite_code');
                    router.push('/dashboard');
                    return;
                }
                authStore.logout();
            }
        } catch (e) {
            isInvite.value = false;
            inviteCode.value = '';
            sessionStorage.removeItem('invite_code');
        }
    }

    // Legacy invitation link support (email + token)
    if (!isInvite.value && route.query.email && route.query.token) {
        const email = String(route.query.email);
        const token = String(route.query.token);
        try {
            await axios.get('/api/invitations/validate', {
                params: { email, token },
            });

            isInvite.value = true;
            inviteEmail.value = email;
            inviteToken.value = token;

            // Store in sessionStorage so we can use it after Google OAuth redirect
            sessionStorage.setItem('invite_email', email);
            sessionStorage.setItem('invite_token', token);
            sessionStorage.removeItem('invite_code');

            if (authStore.isAuthenticated) {
                await authStore.fetchUser();
                const currentEmail = authStore.user?.email;
                if (currentEmail && String(currentEmail).toLowerCase() === String(email).toLowerCase()) {
                    await axios.post('/api/invitations/accept', { email, token });
                    sessionStorage.removeItem('invite_email');
                    sessionStorage.removeItem('invite_token');
                    router.push('/dashboard');
                    return;
                }
                authStore.logout();
            }
        } catch (e) {
            isInvite.value = false;
            inviteEmail.value = '';
            inviteToken.value = '';
            sessionStorage.removeItem('invite_email');
            sessionStorage.removeItem('invite_token');
        }
    }

    // Google Callback now returns auth_code (NOT bearer token)
    const authCode = route.query.auth_code;
    if (authCode) {
        loading.value = true;
        try {
            const exchangeResponse = await axios.get('/api/auth/exchange', {
                params: { code: String(authCode) },
            });
            const exchangePayload = exchangeResponse.data?.data ?? exchangeResponse.data;
            const authToken = exchangePayload?.token;
            if (!authToken) {
                throw new Error('Missing token from exchange');
            }

            await authStore.setAuth(authToken, {});
            await authStore.fetchUser();
            
            // Check if there was an invitation in sessionStorage
            const storedInviteCode = sessionStorage.getItem('invite_code');
            const storedInviteEmail = sessionStorage.getItem('invite_email');
            const storedInviteToken = sessionStorage.getItem('invite_token');

            if (storedInviteCode) {
                await axios.post('/api/invitations/accept', {
                    code: storedInviteCode,
                });
                sessionStorage.removeItem('invite_code');
            } else if (storedInviteEmail && storedInviteToken) {
                await axios.post('/api/invitations/accept', {
                    email: storedInviteEmail,
                    token: storedInviteToken,
                });
                sessionStorage.removeItem('invite_email');
                sessionStorage.removeItem('invite_token');
            }
            
            router.push('/dashboard');
        } catch (error) {
            console.error('Login Error', error);
            authStore.logout();
            alert('Authentication Failed. Please try again.');
        } finally {
            loading.value = false;
        }
    }
});

const googleLogin = () => {
    // If this is an invitation flow, the invite details are already in sessionStorage
    window.location.href = '/api/auth/google/redirect';
};
</script>

<style scoped>
.login-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
    padding: 2rem;
}

.login-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 480px;
    width: 100%;
    padding: 3rem;
    border-radius: 24px;
    text-align: center;
    box-shadow: 0 20px 60px -10px rgba(0, 0, 0, 0.5);
    background: rgba(30, 41, 59, 0.7);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.logo-circle {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #38bdf8, #2563eb);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    box-shadow: 0 10px 30px -5px rgba(37, 99, 235, 0.5);
}

.icon {
    font-size: 2.5rem;
}

h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: white;
    letter-spacing: -0.5px;
}

.invite-msg {
    color: #fbbf24;
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 2rem;
    background: rgba(251, 191, 36, 0.1);
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(251, 191, 36, 0.2);
}

.tagline {
    color: #94a3b8;
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 2rem;
}

.features {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

.feature-item {
    font-size: 0.85rem;
    color: #cbd5e1;
    display: flex;
    align-items: center;
    gap: 6px;
}

.check {
    color: #34d399;
    font-weight: bold;
}

.btn-google-large {
    width: 100%;
    background: white;
    color: #1e293b;
    border: none;
    padding: 1rem;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.btn-google-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    background: #f8fafc;
}

.btn-google-large img {
    width: 24px;
    height: 24px;
}

.terms {
    margin-top: 2rem;
    font-size: 0.75rem;
    color: #64748b;
}

.terms a {
    color: #38bdf8;
    text-decoration: none;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
}

.spinner {
    width: 30px;
    height: 30px;
    border: 3px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    border-top-color: #38bdf8;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Glassmorphism utility */
.glass {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.05);
}
</style>
