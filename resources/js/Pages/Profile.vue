<template>
    <Head title="Profile" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
            <!-- Back Button -->
            <a href="/dashboard" class="btn btn-ghost btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Dashboard
            </a>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Profile Content -->
            <template v-else-if="profile">
                <!-- Profile Header Card -->
                <div class="card bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 border border-base-200 shadow-lg">
                    <div class="card-body">
                        <div class="flex flex-col items-center gap-6 md:flex-row md:items-start">
                            <!-- Avatar -->
                            <div class="avatar">
                                <div class="w-28 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2 shadow-lg">
                                    <img 
                                        :src="profile.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(profile.name)}&background=6366f1&color=fff&size=128`" 
                                        :alt="profile.name"
                                    />
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="flex-1 text-center md:text-left">
                                <h1 class="text-2xl font-bold text-base-content">{{ profile.name }}</h1>
                                <p class="text-base-content/60 mt-1">{{ profile.email }}</p>
                                
                                <!-- Badges -->
                                <div class="flex flex-wrap justify-center gap-2 mt-4 md:justify-start">
                                    <div :class="kycBadgeClass">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path v-if="profile.kycStatus === 'verified'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ kycStatusText }}
                                    </div>
                                    <div v-if="profile.hasCertificate" class="badge badge-success gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                        Certificate Active
                                    </div>
                                    <div v-if="profile.currentOrganization" class="badge badge-primary gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        {{ getRoleLabel(profile.currentOrganization.role) }}
                                    </div>
                                </div>

                                <!-- Member Since -->
                                <p class="text-sm text-base-content/50 mt-4">
                                    Member since {{ formatDate(profile.createdAt) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid gap-4 md:grid-cols-3">
                    <!-- Signatures -->
                    <div class="card bg-base-100 border border-base-200 shadow-sm">
                        <div class="card-body">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold">{{ profile.signaturesCount }}</p>
                                    <p class="text-sm text-base-content/60">Signatures</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organizations -->
                    <div class="card bg-base-100 border border-base-200 shadow-sm">
                        <div class="card-body">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-3xl font-bold">{{ profile.organizationsCount }}</p>
                                    <p class="text-sm text-base-content/60">Organizations</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Status -->
                    <div class="card bg-base-100 border border-base-200 shadow-sm">
                        <div class="card-body">
                            <div class="flex items-center gap-4">
                                <div :class="['flex h-14 w-14 items-center justify-center rounded-xl', profile.hasCertificate ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning']">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-lg font-bold">{{ profile.hasCertificate ? 'Active' : 'Not Issued' }}</p>
                                    <p class="text-sm text-base-content/60">Certificate</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Organization -->
                <div v-if="profile.currentOrganization" class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Current Organization
                        </h2>
                        <div class="flex items-center justify-between mt-2">
                            <div>
                                <p class="font-semibold text-lg">{{ profile.currentOrganization.name }}</p>
                                <p class="text-sm text-base-content/60">Role: {{ getRoleLabel(profile.currentOrganization.role) }}</p>
                            </div>
                            <a href="/dashboard" class="btn btn-primary btn-sm">Go to Dashboard</a>
                        </div>
                    </div>
                </div>

                <!-- Permissions (if in organization) -->
                <div v-if="profile.permissions && profile.permissions.length > 0" class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Your Permissions
                        </h2>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span 
                                v-for="permission in profile.permissions" 
                                :key="permission"
                                class="badge badge-outline badge-sm"
                            >
                                {{ formatPermission(permission) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Account Details -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Account Details
                        </h2>
                        <div class="divide-y divide-base-200 mt-2">
                            <div class="flex justify-between py-3">
                                <span class="text-base-content/60">Email</span>
                                <span class="font-medium">{{ profile.email }}</span>
                            </div>
                            <!-- <div class="flex justify-between py-3">
                                <span class="text-base-content/60">Email Verified</span>
                                <span :class="profile.emailVerifiedAt ? 'text-success' : 'text-warning'">
                                    {{ profile.emailVerifiedAt ? 'Yes' : 'No' }}
                                </span>
                            </div> -->
                            <div class="flex justify-between py-3">
                                <span class="text-base-content/60">KYC Status</span>
                                <span :class="kycTextClass">{{ kycStatusText }}</span>
                            </div>
                            <div class="flex justify-between py-3">
                                <span class="text-base-content/60">Member Since</span>
                                <span class="font-medium">{{ formatDate(profile.createdAt) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v11a2 2 0 01-2 2z" />
                            </svg>
                            KYC Details
                        </h2>

                        <div v-if="kycLoading" class="mt-4 text-sm text-base-content/60">Loading KYC details...</div>
                        <div v-else-if="!kycDetails" class="mt-4 text-sm text-base-content/60">No KYC submission found.</div>
                        <div v-else class="mt-4 space-y-3">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Full Name</div>
                                    <div class="font-semibold">{{ kycDetails.fullName || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Status</div>
                                    <div class="font-semibold">{{ kycDetails.status || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">ID Type</div>
                                    <div class="font-semibold">{{ kycDetails.idType || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">ID Number</div>
                                    <div class="font-semibold">{{ kycDetails.idNumber || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Date of Birth</div>
                                    <div class="font-semibold">{{ kycDetails.dateOfBirth || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Address</div>
                                    <div class="font-semibold">{{ kycDetails.address || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">City</div>
                                    <div class="font-semibold">{{ kycDetails.city || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Province</div>
                                    <div class="font-semibold">{{ kycDetails.province || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Postal Code</div>
                                    <div class="font-semibold">{{ kycDetails.postalCode || '-' }}</div>
                                </div>
                                <div class="rounded-xl border border-base-200 p-3">
                                    <div class="text-xs text-base-content/60">Submitted At</div>
                                    <div class="font-semibold">{{ formatDate(kycDetails.createdAt) }}</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button @click="openKycPreview('id')" class="btn btn-outline btn-sm">View ID Card</button>
                                <button @click="openKycPreview('selfie')" class="btn btn-outline btn-sm">View Selfie Photo</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Error State -->
            <div v-else class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Failed to load profile. Please try again.</span>
            </div>

            <div v-if="kycPreviewOpen" class="modal modal-open" @click.self="closeKycPreview">
                <div class="modal-box w-11/12 max-w-3xl">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ kycPreviewTitle }}</h3>
                        <button class="btn btn-ghost btn-sm" @click="closeKycPreview">✕</button>
                    </div>

                    <div class="mt-4">
                        <div v-if="kycPreviewLoading" class="text-sm text-base-content/60">Loading preview...</div>
                        <div v-else-if="kycPreviewError" class="alert alert-error">
                            <span>{{ kycPreviewError }}</span>
                        </div>
                        <div v-else class="flex justify-center">
                            <img
                                v-if="kycPreviewUrl"
                                :src="kycPreviewUrl"
                                :alt="kycPreviewTitle"
                                class="max-h-[70vh] w-auto rounded-xl border border-base-200"
                            >
                        </div>
                    </div>

                    <div class="modal-action">
                        <button class="btn" @click="closeKycPreview">Close</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { isApiSuccess } from '../utils/api';

const page = usePage();
const loading = ref(true);
const profile = ref(null);
const kycLoading = ref(false);
const kycDetails = ref(null);

const kycPreviewOpen = ref(false);
const kycPreviewLoading = ref(false);
const kycPreviewError = ref('');
const kycPreviewUrl = ref('');
const kycPreviewTitle = ref('');

const kycBadgeClass = computed(() => {
    if (!profile.value) return 'badge badge-ghost gap-1';
    switch (profile.value.kycStatus) {
        case 'verified': return 'badge badge-success gap-1';
        case 'pending': return 'badge badge-warning gap-1';
        case 'rejected': return 'badge badge-error gap-1';
        default: return 'badge badge-ghost gap-1';
    }
});

const kycStatusText = computed(() => {
    if (!profile.value) return 'Unknown';
    switch (profile.value.kycStatus) {
        case 'verified': return 'KYC Verified';
        case 'unverified': return 'KYC Unverified';
        case 'rejected': return 'KYC Rejected';
        default: return 'KYC Not Found';
    }
});

const kycTextClass = computed(() => {
    if (!profile.value) return '';
    switch (profile.value.kycStatus) {
        case 'verified': return 'text-success font-medium';
        case 'unverified': return 'text-warning font-medium';
        case 'rejected': return 'text-error font-medium';
        default: return 'text-base-content/60';
    }
});

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const fetchKycDetails = async () => {
    try {
        kycLoading.value = true;
        const response = await axios.get('/api/kyc/me');
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            kycDetails.value = null;
            return;
        }
        kycDetails.value = payload?.data?.kyc ?? null;
    } catch (error) {
        kycDetails.value = null;
    } finally {
        kycLoading.value = false;
    }
};

const closeKycPreview = () => {
    kycPreviewOpen.value = false;
    kycPreviewLoading.value = false;
    kycPreviewError.value = '';
    if (kycPreviewUrl.value) {
        URL.revokeObjectURL(kycPreviewUrl.value);
    }
    kycPreviewUrl.value = '';
    kycPreviewTitle.value = '';
};

const openKycPreview = async (type) => {
    closeKycPreview();
    kycPreviewOpen.value = true;
    kycPreviewLoading.value = true;
    kycPreviewError.value = '';
    kycPreviewTitle.value = type === 'selfie' ? 'Selfie Photo' : 'ID Photo';

    try {
        const res = await axios.get(`/api/kyc/me/file/${type}`, {
            responseType: 'blob',
        });

        const blob = new Blob([res.data], { type: res.data?.type || 'image/png' });
        kycPreviewUrl.value = URL.createObjectURL(blob);
    } catch (e) {
        const payload = e?.response?.data;
        kycPreviewError.value = payload?.message || payload?.error || 'Failed to load KYC preview.';
    } finally {
        kycPreviewLoading.value = false;
    }
};

const formatPermission = (permission) => {
    return permission.replace(/[._]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getRoleLabel = (role) => {
    const labels = {
        owner: 'Owner',
        admin: 'Admin',
        member: 'Member',
    };
    return labels[String(role || '').toLowerCase()] || (role || 'Member');
};

const fetchProfile = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/profile');
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Failed to load profile');
        }
        profile.value = payload?.data;
    } catch (error) {
        console.error('Failed to fetch profile:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchProfile();
    fetchKycDetails();
});
</script>
