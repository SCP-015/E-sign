<template>
    <Head title="Quota Management" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-5xl space-y-6 px-4 py-6">
            <!-- Back Button -->
            <a href="/dashboard" class="btn btn-ghost btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Dashboard
            </a>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Quota Management</h1>
                    <p class="text-base-content/60">Manage document and signature limits for your organization</p>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- No Permission -->
            <div v-else-if="!hasAccess" class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>You don't have permission to access quota management. Only organization owners can manage quotas.</span>
            </div>

            <template v-else>
                <!-- Quota Settings Card -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <h2 class="card-title text-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Quota Limits (Per User)
                            </h2>
                            <button 
                                v-if="!editing" 
                                class="btn btn-sm btn-outline btn-primary"
                                @click="editing = true"
                            >
                                Edit Limits
                            </button>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2 mt-4">
                            <!-- Max Documents -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-medium">Max Documents per User</span>
                                </label>
                                <div v-if="!editing" class="flex items-center gap-2">
                                    <div class="text-3xl font-bold text-primary">{{ quotaSettings.max_documents_per_user }}</div>
                                    <span class="text-base-content/60">documents</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.max_documents_per_user" 
                                    class="input input-bordered"
                                    min="1"
                                    max="10000"
                                />
                            </div>

                            <!-- Max Signatures -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-medium">Max Signatures per User</span>
                                </label>
                                <div v-if="!editing" class="flex items-center gap-2">
                                    <div class="text-3xl font-bold text-secondary">{{ quotaSettings.max_signatures_per_user }}</div>
                                    <span class="text-base-content/60">signatures</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.max_signatures_per_user" 
                                    class="input input-bordered"
                                    min="1"
                                    max="10000"
                                />
                            </div>

                            <!-- Max Document Size -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-medium">Max Document Size</span>
                                </label>
                                <div v-if="!editing" class="flex items-center gap-2">
                                    <div class="text-3xl font-bold text-accent">{{ quotaSettings.max_document_size_mb }}</div>
                                    <span class="text-base-content/60">MB</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.max_document_size_mb" 
                                    class="input input-bordered"
                                    min="1"
                                    max="100"
                                />
                            </div>

                            <!-- Max Total Storage -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text font-medium">Max Total Storage</span>
                                </label>
                                <div v-if="!editing" class="flex items-center gap-2">
                                    <div class="text-3xl font-bold text-info">{{ quotaSettings.max_total_storage_mb }}</div>
                                    <span class="text-base-content/60">MB</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.max_total_storage_mb" 
                                    class="input input-bordered"
                                    min="100"
                                    max="100000"
                                />
                            </div>
                        </div>

                        <!-- Save/Cancel Buttons -->
                        <div v-if="editing" class="flex justify-end gap-2 mt-4">
                            <button class="btn btn-ghost" @click="cancelEdit">Cancel</button>
                            <button class="btn btn-primary" @click="saveQuota" :disabled="saving">
                                <span v-if="saving" class="loading loading-spinner loading-sm"></span>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Usage by Member -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Member Usage
                        </h2>

                        <div class="overflow-x-auto mt-4">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Role</th>
                                        <th>Documents</th>
                                        <th>Signatures</th>
                                        <th>Storage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="member in usageData" :key="member.user_id">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="avatar">
                                                    <div class="w-10 rounded-full">
                                                        <img 
                                                            :src="member.user?.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(member.user?.name || 'U')}&background=6366f1&color=fff`" 
                                                            :alt="member.user?.name"
                                                        />
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-medium">{{ member.user?.name }}</div>
                                                    <div class="text-sm text-base-content/60">{{ member.user?.email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span :class="getRoleBadgeClass(member.role)">{{ member.role }}</span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ member.documents_uploaded }} / {{ quotaSettings.max_documents_per_user }}</span>
                                                <progress 
                                                    class="progress progress-primary w-20 h-2" 
                                                    :value="member.documents_uploaded" 
                                                    :max="quotaSettings.max_documents_per_user"
                                                ></progress>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ member.signatures_created }} / {{ quotaSettings.max_signatures_per_user }}</span>
                                                <progress 
                                                    class="progress progress-secondary w-20 h-2" 
                                                    :value="member.signatures_created" 
                                                    :max="quotaSettings.max_signatures_per_user"
                                                ></progress>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-medium">{{ member.storage_used_mb }} MB</span>
                                        </td>
                                    </tr>
                                    <tr v-if="usageData.length === 0">
                                        <td colspan="5" class="text-center text-base-content/60 py-8">
                                            No members found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Toast -->
            <div class="toast toast-end">
                <div v-if="toast.show" :class="['alert', toast.type === 'success' ? 'alert-success' : 'alert-error']">
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const loading = ref(true);
const saving = ref(false);
const editing = ref(false);
const hasAccess = ref(true);
const quotaSettings = ref({
    max_documents_per_user: 50,
    max_signatures_per_user: 100,
    max_document_size_mb: 10,
    max_total_storage_mb: 500,
});
const usageData = ref([]);

const form = reactive({
    max_documents_per_user: 50,
    max_signatures_per_user: 100,
    max_document_size_mb: 10,
    max_total_storage_mb: 500,
});

const toast = reactive({
    show: false,
    message: '',
    type: 'success'
});

const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    setTimeout(() => { toast.show = false; }, 3000);
};

const isApiSuccess = (payload) => {
    return payload?.success === true || payload?.status === 'success';
};

const getRoleBadgeClass = (role) => {
    switch (role?.toLowerCase()) {
        case 'owner': return 'badge badge-primary';
        case 'admin': return 'badge badge-secondary';
        case 'manager': return 'badge badge-accent';
        default: return 'badge badge-ghost';
    }
};

const fetchQuota = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/quota');
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal memuat quota');
        }
        const data = payload?.data ?? {};
        quotaSettings.value = data.quota_settings ?? data.quotaSettings ?? quotaSettings.value;
        usageData.value = data.usage ?? [];
        Object.assign(form, quotaSettings.value);
        hasAccess.value = true;
    } catch (error) {
        if (error.response?.status === 403) {
            hasAccess.value = false;
        }
        console.error('Failed to fetch quota:', error.response?.data || error.message);
    } finally {
        loading.value = false;
    }
};

const saveQuota = async () => {
    try {
        saving.value = true;
        const response = await axios.put('/api/quota', form);
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal menyimpan quota');
        }

        quotaSettings.value = payload?.data ?? quotaSettings.value;
        Object.assign(form, quotaSettings.value);
        editing.value = false;
        showToast('Quota settings saved successfully!');

        // Refresh usage agar tabel/progress ikut update
        await fetchQuota();
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Failed to save quota settings';
        showToast(message, 'error');
    } finally {
        saving.value = false;
    }
};

const cancelEdit = () => {
    Object.assign(form, quotaSettings.value);
    editing.value = false;
};

onMounted(() => {
    fetchQuota();
});
</script>
