<template>
    <div class="login-container">
        <div class="glass login-box">
            <h1>Digital Signature</h1>
            <p class="subtitle">Secure, Fast, & Legal</p>
            
            <form @submit.prevent="handleLogin">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" v-model="email" placeholder="you@example.com" required>
                </div>

                <div class="input-group">
                    <label>Password (New or Existing)</label>
                    <input type="password" v-model="password" placeholder="********" required>
                </div>

                <button type="submit" :disabled="loading">
                    <span v-if="!loading">Login / Register</span>
                    <span v-else>Processing...</span>
                </button>

                <!-- Google Login Button -->
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <button type="button" @click="googleLogin" class="btn-google" :disabled="loading">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="G" />
                    Sign in with Google
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';

const email = ref('');
const password = ref('');
const loading = ref(false);
const router = useRouter();
const route = useRoute();

onMounted(async () => {
    // Check for token in URL (from Google Callback)
    const token = route.query.token;
    if (token) {
        loading.value = true;
        try {
            // Save token
            localStorage.setItem('token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            
            // FOR DEBUGGING: Log token to console so user can copy it
            console.log('🔥 YOUR AUTH TOKEN:', token);

            // Fetch User Details to store in local storage (needed for Dashboard)
            const userRes = await axios.get('/api/user');
            localStorage.setItem('user', JSON.stringify(userRes.data));

            // Redirect to Dashboard
            router.push('/dashboard');
        } catch (error) {
            console.error('Google Auth Failed to fetch User', error);
            alert('Google Login Error: Could not fetch user data.');
            localStorage.removeItem('token');
        } finally {
            loading.value = false;
        }
    }
});

const handleLogin = async () => {
    loading.value = true;
    try {
        const response = await axios.post('/api/auth/login', {
            email: email.value,
            password: password.value
        });
        
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        router.push('/dashboard');
    } catch (error) {
        alert('Login Failed: ' + (error.response?.data?.message || error.message));
    } finally {
        loading.value = false;
    }
};

const googleLogin = () => {
    // Redirect to backend Google Auth route
    window.location.href = '/api/auth/google/redirect';
};
</script>

<style scoped>
.login-container {
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: radial-gradient(circle at top right, #1e293b, #0f172a);
}

.login-box {
    width: 100%;
    max-width: 400px;
    padding: 3rem;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    background: linear-gradient(to right, #38bdf8, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.subtitle {
    color: #94a3b8;
    margin-bottom: 2rem;
}

.input-group {
    text-align: left;
    margin-bottom: 1.5rem;
}

label {
    display: block;
    font-size: 0.875rem;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
}

input {
    width: 100%;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
    color: white;
    font-size: 1rem;
    outline: none;
    transition: all 0.3s;
}

input:focus {
    border-color: #38bdf8;
    background: rgba(0, 0, 0, 0.3);
}

button {
    width: 100%;
    padding: 1rem;
    border-radius: 8px;
    border: none;
    background: linear-gradient(to right, #38bdf8, #818cf8);
    color: white;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.2s;
}

button:hover {
    transform: translateY(-2px);
    opacity: 0.9;
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 1.5rem 0;
    color: #64748b;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.divider span {
    padding: 0 10px;
    font-size: 0.8rem;
}

.btn-google {
    background: white;
    color: #1e293b;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-google img {
    width: 20px;
    height: 20px;
}

.btn-google:hover {
    background: #f1f5f9;
    color: #0f172a;
    transform: translateY(-2px);
}
</style>
