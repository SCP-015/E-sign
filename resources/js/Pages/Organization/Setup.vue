<template>
    <div class="min-h-screen bg-base-200">
        <div class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="w-full max-w-2xl">
                <!-- Header -->
                <div class="mb-8 text-center">
                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary shadow-lg mb-4">
                        <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold text-base-content">Welcome, {{ user?.name || 'User' }}!</h1>
                    <p class="mt-2 text-base-content/60">Let's set up your organization to get started</p>
                </div>

                <!-- Steps -->
                <div class="card border border-base-300 bg-base-100 shadow-xl">
                    <div class="card-body">
                        <!-- Step Indicator -->
                        <div class="mb-6">
                            <ul class="steps steps-horizontal w-full">
                                <li :class="['step', { 'step-primary': step >= 1 }]">Choose</li>
                                <li :class="['step', { 'step-primary': step >= 2 }]">Setup</li>
                                <li :class="['step', { 'step-primary': step >= 3 }]">Complete</li>
                            </ul>
                        </div>

                        <!-- Step 1: Choose Option -->
                        <div v-if="step === 1" class="space-y-4">
                            <h2 class="text-xl font-bold">How would you like to get started?</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <!-- Create New Organization -->
                                <button
                                    @click="selectOption('create')"
                                    class="group card cursor-pointer border-2 transition-all hover:border-primary hover:shadow-lg"
                                    :class="selectedOption === 'create' ? 'border-primary bg-primary/5' : 'border-base-300'"
                                >
                                    <div class="card-body items-center text-center">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary transition-all group-hover:scale-110">
                                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <path d="M12 8v8"></path>
                                                <path d="M8 12h8"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-bold">Create New Organization</h3>
                                        <p class="text-sm text-base-content/60">Start fresh with your own organization</p>
                                    </div>
                                </button>

                                <!-- Join Existing -->
                                <button
                                    @click="selectOption('join')"
                                    class="group card cursor-pointer border-2 transition-all hover:border-primary hover:shadow-lg"
                                    :class="selectedOption === 'join' ? 'border-primary bg-primary/5' : 'border-base-300'"
                                >
                                    <div class="card-body items-center text-center">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-success/10 text-success transition-all group-hover:scale-110">
                                            <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-bold">Join Organization</h3>
                                        <p class="text-sm text-base-content/60">Join with an invitation code</p>
                                    </div>
                                </button>
                            </div>

                            <div class="divider">OR</div>

                            <Link href="/dashboard" class="btn btn-ghost btn-block">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"></path>
                                </svg>
                                Continue without Organization (Personal Mode)
                            </Link>
                        </div>

                        <!-- Step 2: Create Organization Form -->
                        <div v-if="step === 2 && selectedOption === 'create'" class="space-y-4">
                            <h2 class="text-xl font-bold">Create Your Organization</h2>
                            
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Organization Name</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g., PT Example Indonesia"
                                    class="input input-bordered"
                                    :class="{ 'input-error': errors.name }"
                                />
                                <label v-if="errors.name" class="label">
                                    <span class="label-text-alt text-error">{{ errors.name }}</span>
                                </label>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Description (Optional)</span>
                                </label>
                                <textarea
                                    v-model="form.description"
                                    placeholder="Brief description of your organization"
                                    class="textarea textarea-bordered h-24"
                                ></textarea>
                            </div>

                            <div class="flex gap-3">
                                <button @click="step = 1" class="btn btn-ghost flex-1">Back</button>
                                <button @click="createOrganization" :disabled="loading" class="btn btn-primary flex-1">
                                    <span v-if="loading" class="loading loading-spinner"></span>
                                    {{ loading ? 'Creating...' : 'Create Organization' }}
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Join Organization Form -->
                        <div v-if="step === 2 && selectedOption === 'join'" class="space-y-4">
                            <h2 class="text-xl font-bold">Join Existing Organization</h2>
                            
                            <div class="alert alert-info">
                                <svg viewBox="0 0 24 24" class="h-5 w-5 shrink-0 stroke-current" fill="none" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 16v-4"></path>
                                    <path d="M12 8h.01"></path>
                                </svg>
                                <span class="text-sm">Ask your organization admin for an invitation code</span>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-semibold">Invitation Code</span>
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    placeholder="e.g., ABC123XYZW"
                                    class="input input-bordered uppercase"
                                    :class="{ 'input-error': errors.code }"
                                    maxlength="12"
                                />
                                <label v-if="errors.code" class="label">
                                    <span class="label-text-alt text-error">{{ errors.code }}</span>
                                </label>
                            </div>

                            <div class="flex gap-3">
                                <button @click="step = 1" class="btn btn-ghost flex-1">Back</button>
                                <button @click="joinOrganization" :disabled="loading" class="btn btn-primary flex-1">
                                    <span v-if="loading" class="loading loading-spinner"></span>
                                    {{ loading ? 'Joining...' : 'Join Organization' }}
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Success -->
                        <div v-if="step === 3" class="space-y-4 text-center">
                            <div class="flex justify-center">
                                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-success/10 text-success animate-bounce">
                                    <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <path d="M22 4L12 14.01l-3-3"></path>
                                    </svg>
                                </div>
                            </div>
                            <h2 class="text-2xl font-bold">Setup Complete!</h2>
                            <p class="text-base-content/60">
                                {{ selectedOption === 'create' ? 'Your organization has been created successfully.' : 'You have joined the organization successfully.' }}
                            </p>
                            <div v-if="resultOrganization" class="rounded-xl bg-base-200 p-4 text-left">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-xl font-bold text-primary-content">
                                        {{ resultOrganization.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-semibold">{{ resultOrganization.name }}</div>
                                        <div class="text-sm text-base-content/60">{{ resultOrganization.slug }}</div>
                                    </div>
                                </div>
                            </div>
                            <button @click="goToDashboard" class="btn btn-primary btn-wide">
                                Go to Dashboard
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14"></path>
                                    <path d="M12 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer Help -->
                <div class="mt-6 text-center text-sm text-base-content/60">
                    <p>Need help? <a href="mailto:support@esign.com" class="link link-primary">Contact Support</a></p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../../stores/auth';
import { useToastStore } from '../../stores/toast';

const authStore = useAuthStore();
const toastStore = useToastStore();

const user = computed(() => authStore.user);
const step = ref(1);
const selectedOption = ref(null);
const loading = ref(false);
const resultOrganization = ref(null);
const form = ref({
    name: '',
    description: '',
    code: '',
});
const errors = ref({});

const selectOption = (option) => {
    selectedOption.value = option;
    step.value = 2;
    errors.value = {};
};

const createOrganization = async () => {
    errors.value = {};
    
    if (!form.value.name) {
        errors.value.name = 'Organization name is required';
        return;
    }
    
    loading.value = true;
    try {
        const response = await axios.post('/api/organizations', {
            name: form.value.name,
            description: form.value.description,
        });
        
        if (response.data.success) {
            resultOrganization.value = response.data.data;
            toastStore.success('Organization created successfully!');
            window.dispatchEvent(new Event('organizations-updated'));
            step.value = 3;
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Failed to create organization';
        toastStore.error(message);
        
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    } finally {
        loading.value = false;
    }
};

const joinOrganization = async () => {
    errors.value = {};
    
    if (!form.value.code) {
        errors.value.code = 'Invitation code is required';
        return;
    }
    
    loading.value = true;
    try {
        const response = await axios.post('/api/organizations/join', {
            code: form.value.code.toUpperCase(),
        });
        
        if (response.data.success) {
            resultOrganization.value = response.data.data;
            toastStore.success('Joined organization successfully!');
            window.dispatchEvent(new Event('organizations-updated'));
            step.value = 3;
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Failed to join organization';
        toastStore.error(message);
        
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        }
    } finally {
        loading.value = false;
    }
};

const goToDashboard = async () => {
    if (resultOrganization.value) {
        await axios.post('/api/organizations/switch', {
            organization_id: resultOrganization.value.id,
        });
        window.dispatchEvent(new Event('organizations-updated'));
    }
    router.visit('/dashboard');
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    const code = urlParams.get('code');

    if (mode === 'create') {
        selectOption('create');
    } else if (mode === 'join') {
        selectOption('join');
        if (code) {
            form.value.code = code;
        }
    }
});
</script>
