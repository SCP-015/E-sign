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
                                    <div class="text-3xl font-bold text-primary">{{ quotaSettings.maxDocumentsPerUser }}</div>
                                    <span class="text-base-content/60">documents</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.maxDocumentsPerUser" 
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
                                    <div class="text-3xl font-bold text-secondary">{{ quotaSettings.maxSignaturesPerUser }}</div>
                                    <span class="text-base-content/60">signatures</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.maxSignaturesPerUser" 
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
                                    <div class="text-3xl font-bold text-accent">{{ quotaSettings.maxDocumentSizeMb }}</div>
                                    <span class="text-base-content/60">MB</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.maxDocumentSizeMb" 
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
                                    <div class="text-3xl font-bold text-info">{{ quotaSettings.maxTotalStorageMb }}</div>
                                    <span class="text-base-content/60">MB</span>
                                </div>
                                <input 
                                    v-else
                                    type="number" 
                                    v-model.number="form.maxTotalStorageMb" 
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
                                    <tr v-for="member in usageData" :key="member.userId">
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
                                                <span class="font-medium">{{ member.documentsUploaded }} / {{ member.effectiveLimits?.maxDocumentsPerUser ?? quotaSettings.maxDocumentsPerUser }}</span>
                                                <progress 
                                                    class="progress progress-primary w-20 h-2" 
                                                    :value="Number(member.documentsUploaded) || 0" 
                                                    :max="Math.max(1, Number(member.effectiveLimits?.maxDocumentsPerUser ?? quotaSettings.maxDocumentsPerUser) || 1)"
                                                ></progress>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ member.signaturesCreated }} / {{ member.effectiveLimits?.maxSignaturesPerUser ?? quotaSettings.maxSignaturesPerUser }}</span>
                                                <progress 
                                                    class="progress progress-secondary w-20 h-2" 
                                                    :value="Number(member.signaturesCreated) || 0" 
                                                    :max="Math.max(1, Number(member.effectiveLimits?.maxSignaturesPerUser ?? quotaSettings.maxSignaturesPerUser) || 1)"
                                                ></progress>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-medium">{{ member.storageUsedMb }} MB</span>
                                                <button class="btn btn-ghost btn-xs" @click="openUserOverride(member)" title="Atur kuota user">
                                                    Edit
                                                </button>
                                            </div>
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

            <dialog ref="overrideModal" class="modal">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">Quota Override (Per User)</h3>
                    <p class="text-sm text-base-content/60 mt-1">
                        {{ selectedMember?.user?.name }} ({{ selectedMember?.user?.email }})
                    </p>

                    <div class="grid gap-4 mt-4">
                        <div class="form-control">
                            <label class="label"><span class="label-text">Max Documents</span></label>
                            <input type="number" class="input input-bordered" v-model.number="overrideForm.maxDocumentsPerUser" min="1" max="10000" />
                            <label class="label"><span class="label-text-alt text-base-content/60">Kosongkan untuk ikut global</span></label>
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Max Signatures</span></label>
                            <input type="number" class="input input-bordered" v-model.number="overrideForm.maxSignaturesPerUser" min="1" max="10000" />
                            <label class="label"><span class="label-text-alt text-base-content/60">Kosongkan untuk ikut global</span></label>
                        </div>
                        <div class="form-control">
                            <label class="label"><span class="label-text">Max Storage (MB)</span></label>
                            <input type="number" class="input input-bordered" v-model.number="overrideForm.maxTotalStorageMb" min="100" max="100000" />
                            <label class="label"><span class="label-text-alt text-base-content/60">Kosongkan untuk ikut global</span></label>
                        </div>
                    </div>

                    <div class="modal-action">
                        <button class="btn" type="button" @click="closeOverrideModal">Cancel</button>
                        <button class="btn btn-outline" type="button" @click="clearOverride" :disabled="overrideSaving">Reset to Global</button>
                        <button class="btn btn-primary" type="button" @click="saveUserOverride" :disabled="overrideSaving">
                            <span v-if="overrideSaving" class="loading loading-spinner loading-sm"></span>
                            Save
                        </button>
                    </div>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>close</button>
                </form>
            </dialog>

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
    maxDocumentsPerUser: 50,
    maxSignaturesPerUser: 100,
    maxDocumentSizeMb: 10,
    maxTotalStorageMb: 500,
});
const usageData = ref([]);

const overrideModal = ref(null);
const selectedMember = ref(null);
const overrideSaving = ref(false);
const overrideForm = reactive({
    maxDocumentsPerUser: null,
    maxSignaturesPerUser: null,
    maxTotalStorageMb: null,
});

const form = reactive({
    maxDocumentsPerUser: 50,
    maxSignaturesPerUser: 100,
    maxDocumentSizeMb: 10,
    maxTotalStorageMb: 500,
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

const openUserOverride = (member) => {
    selectedMember.value = member;
    overrideForm.maxDocumentsPerUser = member?.override?.maxDocumentsPerUser ?? null;
    overrideForm.maxSignaturesPerUser = member?.override?.maxSignaturesPerUser ?? null;
    overrideForm.maxTotalStorageMb = member?.override?.maxTotalStorageMb ?? null;
    overrideModal.value?.showModal?.();
};

const closeOverrideModal = () => {
    overrideModal.value?.close?.();
    selectedMember.value = null;
};

const saveUserOverride = async () => {
    if (!selectedMember.value?.userId) return;
    try {
        overrideSaving.value = true;
        const payload = {
            max_documents_per_user: overrideForm.maxDocumentsPerUser || null,
            max_signatures_per_user: overrideForm.maxSignaturesPerUser || null,
            max_total_storage_mb: overrideForm.maxTotalStorageMb || null,
        };

        const res = await axios.put(`/api/quota/users/${selectedMember.value.userId}`, payload);
        const api = res?.data;
        if (!(api?.success === true || api?.status === 'success')) {
            throw new Error(api?.message || 'Gagal menyimpan quota user');
        }

        showToast('User quota updated successfully!');
        closeOverrideModal();
        await fetchQuota();
        window.dispatchEvent(new Event('quota-updated'));
    } catch (e) {
        showToast(e?.response?.data?.message || e?.message || 'Failed to save user quota', 'error');
    } finally {
        overrideSaving.value = false;
    }
};

const clearOverride = async () => {
    overrideForm.maxDocumentsPerUser = null;
    overrideForm.maxSignaturesPerUser = null;
    overrideForm.maxTotalStorageMb = null;
    await saveUserOverride();
};

const getRoleBadgeClass = (role) => {
    switch (role?.toLowerCase()) {
        case 'owner': return 'badge badge-primary';
        case 'admin': return 'badge badge-secondary';
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
        const settings = data.quotaSettings ?? null;
        if (settings) {
            quotaSettings.value = settings;
            Object.assign(form, settings);
        }

        const usage = Array.isArray(data.usage) ? data.usage : [];
        usageData.value = usage;
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
        const response = await axios.put('/api/quota', {
            max_documents_per_user: form.maxDocumentsPerUser,
            max_signatures_per_user: form.maxSignaturesPerUser,
            max_document_size_mb: form.maxDocumentSizeMb,
            max_total_storage_mb: form.maxTotalStorageMb,
        });
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal menyimpan quota');
        }

        if (payload?.data) {
            quotaSettings.value = payload.data;
            Object.assign(form, payload.data);
        }
        editing.value = false;
        showToast('Quota settings saved successfully!');

        window.dispatchEvent(new CustomEvent('quota-updated', { detail: quotaSettings.value }));

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
