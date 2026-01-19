<template>
    <div class="p-6 max-w-6xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold">Billing & Plans</h1>
                <p class="text-base-content/60">Manage your organization's subscription and track usage limits.</p>
            </div>

            <div v-if="loading" class="flex justify-center py-20">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <template v-else>
                <!-- Current Subscription & Usage -->
                <div class="grid gap-6 lg:grid-cols-3 mb-10">
                    <!-- Current Plan -->
                    <div class="card bg-base-100 shadow-xl border border-base-200 lg:col-span-1">
                        <div class="card-body">
                            <h2 class="card-title text-sm uppercase tracking-widest text-base-content/50">Current Plan</h2>
                            <div class="mt-2 text-3xl font-bold text-primary">{{ organization?.plan || 'Free' }}</div>
                            <p class="text-sm mt-1">
                                Status: 
                                <span class="badge badge-success badge-sm">Active</span>
                            </p>
                            
                            <div class="card-action mt-6">
                                <button class="btn btn-outline btn-block btn-sm" disabled>Manage Subscription</button>
                            </div>
                        </div>
                    </div>

                    <!-- Usage Stats -->
                    <div class="card bg-base-100 shadow-xl border border-base-200 lg:col-span-2">
                        <div class="card-body">
                            <h2 class="card-title text-sm uppercase tracking-widest text-base-content/50">Usage Tracking (This Month)</h2>
                            
                            <div class="grid gap-6 md:grid-cols-3 mt-4">
                                <!-- Signatures -->
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Signatures</span>
                                        <span class="font-semibold">{{ usage.signatures }} / {{ formatLimit(limits.signatures) }}</span>
                                    </div>
                                    <progress class="progress progress-primary w-full" :value="usage.signatures" :max="limits.signatures || 100"></progress>
                                </div>

                                <!-- Documents -->
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Documents</span>
                                        <span class="font-semibold">{{ usage.documents }} / {{ formatLimit(limits.documents) }}</span>
                                    </div>
                                    <progress class="progress progress-secondary w-full" :value="usage.documents" :max="limits.documents || 100"></progress>
                                </div>

                                <!-- Members -->
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Members</span>
                                        <span class="font-semibold">{{ usage.members }} / {{ formatLimit(limits.members) }}</span>
                                    </div>
                                    <progress class="progress progress-accent w-full" :value="usage.members" :max="limits.members || 10"></progress>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Plans -->
                <h2 class="text-2xl font-bold mb-6">Compare Plans</h2>
                <div class="grid gap-6 md:grid-cols-3">
                    <div v-for="plan in plans" :key="plan.slug" 
                        class="card bg-base-100 shadow-xl border-2"
                        :class="plan.slug === (organization?.plan || 'free') ? 'border-primary' : 'border-transparent'">
                        
                        <div v-if="plan.slug === (organization?.plan || 'free')" class="bg-primary text-primary-content text-center py-1 text-xs font-bold uppercase tracking-widest">
                            Your Current Plan
                        </div>

                        <div class="card-body">
                            <h3 class="card-title">{{ plan.name }}</h3>
                            <p class="text-sm text-base-content/70 min-h-[40px]">{{ plan.description }}</p>
                            
                            <div class="my-6">
                                <span class="text-4xl font-bold">{{ formatPrice(plan.price) }}</span>
                                <span class="text-base-content/60">/mo</span>
                            </div>

                            <ul class="space-y-3 mb-8">
                                <li class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-success" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    <strong>{{ formatLimit(plan.limits.signatures) }} Signatures</strong>
                                </li>
                                <li class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-success" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    <strong>{{ formatLimit(plan.limits.documents) }} Documents</strong>
                                </li>
                                <li class="flex items-center gap-2 text-sm">
                                    <svg class="h-4 w-4 text-success" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    <strong>{{ formatLimit(plan.limits.members) }} Team Members</strong>
                                </li>
                                <li class="flex items-center gap-2 text-sm" :class="plan.features.customBranding ? 'text-base-content' : 'text-base-content/40 italic'">
                                    <svg class="h-4 w-4" :class="plan.features.customBranding ? 'text-success' : 'text-base-content/20'" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    Custom Branding
                                </li>
                                <li class="flex items-center gap-2 text-sm" :class="plan.features.apiAccess ? 'text-base-content' : 'text-base-content/40 italic'">
                                    <svg class="h-4 w-4" :class="plan.features.apiAccess ? 'text-success' : 'text-base-content/20'" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"></path></svg>
                                    API Access
                                </li>
                            </ul>

                            <div class="card-actions">
                                <button v-if="plan.slug === (organization?.plan || 'free')" class="btn btn-disabled btn-block">Current Plan</button>
                                <button v-else @click="selectPlan(plan)" class="btn btn-primary btn-block">Upgrade to {{ plan.name }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Coming Soon Notice -->
                <div class="alert mt-8">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Payment integration coming soon. For enterprise plans, please contact us at <a href="mailto:sales@esign.com" class="link link-primary">sales@esign.com</a></span>
                </div>
            </template>
        </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';

const toastStore = useToastStore();
const loading = ref(true);
const organization = ref(null);

// Usage tracking
const usage = ref({
    signatures: 0,
    documents: 0,
    members: 0,
});

const limits = ref({
    signatures: 50,
    documents: 20,
    members: 5,
});

const isApiSuccess = (payload) => {
    return payload?.success === true || payload?.status === 'success';
};

// Available plans
const plans = ref([
    {
        slug: 'free',
        name: 'Free',
        description: 'Perfect for individuals and small teams getting started.',
        price: 0,
        limits: { signatures: 50, documents: 20, members: 5 },
        features: { customBranding: false, apiAccess: false },
    },
    {
        slug: 'pro',
        name: 'Professional',
        description: 'For growing teams that need more power and flexibility.',
        price: 299000,
        limits: { signatures: 500, documents: 200, members: 20 },
        features: { customBranding: true, apiAccess: false },
    },
    {
        slug: 'enterprise',
        name: 'Enterprise',
        description: 'For large organizations with advanced needs.',
        price: 999000,
        limits: { signatures: null, documents: null, members: null },
        features: { customBranding: true, apiAccess: true },
    },
]);

const formatPrice = (price) => {
    if (price === 0) return 'Free';
    return 'IDR ' + new Intl.NumberFormat('id-ID').format(price);
};

const formatLimit = (limit) => {
    if (limit === null || limit === 0) return 'Unlimited';
    return limit;
};

const selectPlan = (plan) => {
    toastStore.info(`Upgrade to ${plan.name} will be available soon!`);
};

const fetchData = async () => {
    loading.value = true;
    try {
        // Get current organization
        const orgRes = await axios.get('/api/organizations/current');
        const orgPayload = orgRes?.data;
        if (!isApiSuccess(orgPayload) || !orgPayload?.data) {
            throw new Error(orgPayload?.message || 'Gagal memuat organisasi');
        }

        organization.value = orgPayload.data;

        // Get quota settings to sync limits
        const quotaRes = await axios.get('/api/quota');
        const quotaPayload = quotaRes?.data;
        if (isApiSuccess(quotaPayload)) {
            const quotaSettings = quotaPayload?.data?.quotaSettings ?? null;
            if (quotaSettings) {
                limits.value.signatures = quotaSettings.maxSignaturesPerUser ?? limits.value.signatures;
                limits.value.documents = quotaSettings.maxDocumentsPerUser ?? limits.value.documents;
            }
        }

        // Get member count for usage
        const membersRes = await axios.get(`/api/organizations/${organization.value.id}/members`);
        const membersPayload = membersRes?.data;
        if (isApiSuccess(membersPayload)) {
            const members = membersPayload?.data ?? [];
            usage.value.members = Array.isArray(members) ? members.length : 0;
        }
    } catch (error) {
        console.error('Failed to fetch billing data:', error);
        toastStore.error('Failed to load billing information');
    } finally {
        loading.value = false;
    }
};

const handleQuotaUpdated = () => {
    fetchData();
};

onMounted(() => {
    fetchData();
    window.addEventListener('quota-updated', handleQuotaUpdated);
});

onUnmounted(() => {
    window.removeEventListener('quota-updated', handleQuotaUpdated);
});
</script>
