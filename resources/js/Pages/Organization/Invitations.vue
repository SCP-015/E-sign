<template>
    <div class="mx-auto max-w-4xl px-4 py-8">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Invite Users</h1>
                    <p v-if="organization" class="text-sm text-base-content/60">
                        {{ organization.name }} • Kelola undangan
                    </p>
                </div>
                <Link href="/dashboard" class="btn btn-ghost btn-sm">
                    ← Kembali
                </Link>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center py-12">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="alert alert-error mb-6">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ error }}</span>
            </div>

            <template v-else>
                <!-- Create Invitation Card -->
                <div class="card mb-6 bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Buat Undangan Baru</h2>
                        
                        <form class="mt-4 grid gap-4 sm:grid-cols-3" @submit.prevent="createInvitation">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Role</span>
                                </label>
                                <select v-model="newInvitation.role" class="select select-bordered">
                                    <option value="user">Member</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Expired (hari)</span>
                                </label>
                                <input
                                    v-model.number="newInvitation.expiry_days"
                                    type="number"
                                    class="input input-bordered"
                                    min="1"
                                    max="30"
                                />
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Max Uses</span>
                                    <span class="label-text-alt">Opsional</span>
                                </label>
                                <input
                                    v-model.number="newInvitation.max_uses"
                                    type="number"
                                    class="input input-bordered"
                                    placeholder="Unlimited"
                                    min="1"
                                />
                            </div>

                            <div class="sm:col-span-3">
                                <button type="submit" class="btn btn-primary" :disabled="creating">
                                    <span v-if="creating" class="loading loading-spinner loading-sm"></span>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Buat Kode Undangan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Invitations Table -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Daftar Undangan</h2>

                        <div v-if="invitations.length === 0" class="py-8 text-center text-base-content/60">
                            Belum ada undangan. Buat undangan baru di atas.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Role</th>
                                        <th>Expires</th>
                                        <th>Usage</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="invitation in invitations" :key="invitation.id" class="hover">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <code class="rounded bg-base-200 px-2 py-1 font-mono text-sm">
                                                    {{ invitation.code }}
                                                </code>
                                                <button
                                                    type="button"
                                                    class="btn btn-ghost btn-xs"
                                                    @click="copyCode(invitation.code)"
                                                >
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" :class="getRoleBadgeClass(invitation.role)">
                                                {{ getRoleLabel(invitation.role) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ formatDate(invitation.expires_at) }}
                                        </td>
                                        <td>
                                            {{ invitation.used_count }} / {{ invitation.max_uses || '∞' }}
                                        </td>
                                        <td>
                                            <span v-if="invitation.is_valid" class="badge badge-success badge-sm">Aktif</span>
                                            <span v-else class="badge badge-error badge-sm">Expired</span>
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-sm text-error"
                                                @click="confirmDelete(invitation)"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Delete Confirmation Modal -->
            <dialog ref="deleteModal" class="modal">
                <div class="modal-box">
                    <h3 class="text-lg font-bold">Konfirmasi Hapus Undangan</h3>
                    <p class="py-4">
                        Apakah Anda yakin ingin menghapus kode undangan <strong>{{ invitationToDelete?.code }}</strong>?
                    </p>
                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" @click="closeDeleteModal">Batal</button>
                        <button type="button" class="btn btn-error" :disabled="deleting" @click="deleteInvitation">
                            <span v-if="deleting" class="loading loading-spinner loading-sm"></span>
                            Hapus
                        </button>
                    </div>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button type="button" @click="closeDeleteModal">close</button>
                </form>
            </dialog>
        </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';

const toastStore = useToastStore();

const loading = ref(true);
const error = ref('');
const organization = ref(null);
const invitations = ref([]);
const creating = ref(false);
const deleting = ref(false);
const invitationToDelete = ref(null);
const deleteModal = ref(null);

const newInvitation = ref({
    role: 'user',
    expiry_days: 7,
    max_uses: null,
});

function getRoleLabel(role) {
    const labels = { admin: 'Admin', manager: 'Manager', user: 'Member' };
    return labels[role] || role;
}

function getRoleBadgeClass(role) {
    const classes = {
        admin: 'badge-primary',
        manager: 'badge-secondary',
        user: 'badge-ghost',
    };
    return classes[role] || 'badge-ghost';
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function copyCode(code) {
    navigator.clipboard.writeText(code);
    toastStore.success('Kode berhasil disalin!');
}

async function fetchCurrentOrganization() {
    const response = await axios.get('/api/organizations/current');
    if (response.data.success && response.data.data) {
        organization.value = response.data.data;
        return organization.value.id;
    }
    throw new Error('Anda belum berada dalam organization');
}

async function fetchInvitations(orgId) {
    const response = await axios.get(`/api/organizations/${orgId}/invitations`);
    if (response.data.success) {
        invitations.value = response.data.data;
    }
}

async function createInvitation() {
    creating.value = true;
    try {
        const response = await axios.post(`/api/organizations/${organization.value.id}/invitations`, {
            role: newInvitation.value.role,
            expiry_days: newInvitation.value.expiry_days,
            max_uses: newInvitation.value.max_uses || null,
        });

        if (response.data.success) {
            toastStore.success('Undangan berhasil dibuat');
            await fetchInvitations(organization.value.id);
            // Reset form
            newInvitation.value = { role: 'user', expiry_days: 7, max_uses: null };
            // Copy new code
            copyCode(response.data.data.code);
        }
    } catch (e) {
        toastStore.error(e.response?.data?.message || 'Gagal membuat undangan');
    } finally {
        creating.value = false;
    }
}

function confirmDelete(invitation) {
    invitationToDelete.value = invitation;
    deleteModal.value?.showModal();
}

function closeDeleteModal() {
    deleteModal.value?.close();
    invitationToDelete.value = null;
}

async function deleteInvitation() {
    if (!invitationToDelete.value) return;

    deleting.value = true;
    try {
        await axios.delete(`/api/organizations/${organization.value.id}/invitations/${invitationToDelete.value.id}`);
        toastStore.success('Undangan berhasil dihapus');
        invitations.value = invitations.value.filter(i => i.id !== invitationToDelete.value.id);
        closeDeleteModal();
    } catch (e) {
        toastStore.error(e.response?.data?.message || 'Gagal menghapus undangan');
    } finally {
        deleting.value = false;
    }
}

onMounted(async () => {
    try {
        const orgId = await fetchCurrentOrganization();
        await fetchInvitations(orgId);
    } catch (e) {
        error.value = e.response?.data?.message || e.message || 'Gagal memuat data';
    } finally {
        loading.value = false;
    }
});
</script>
